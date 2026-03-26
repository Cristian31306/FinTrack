<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\CreditCard;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppBotService
{
    private const CACHE_PREFIX = 'whatsapp_bot_state_';
    private const TTL = 20; // minutos

    public function __construct(
        private readonly WhatsAppService $whatsappService,
        private readonly AiAssistantService $aiService
    ) {}

    /**
     * Punto de entrada principal para el bot estructurado.
     */
    public function handle(User $user, string $message, string $from): string|array|null
    {
        $state = $this->getState($user);
        $cleanMsg = trim(mb_strtolower($message));

        // Comandos globales
        if ($cleanMsg === 'menu' || $cleanMsg === 'inicio' || $cleanMsg === 'hola' || $cleanMsg === 'ayuda') {
            return $this->showMainMenu($user);
        }

        if ($cleanMsg === 'resumen' || $cleanMsg === 'deuda') {
            $this->clearState($user);
            return $this->handleMenuSelection($user, 'resumen');
        }

        if ($cleanMsg === 'cancelar' || $cleanMsg === '0') {
            $this->clearState($user);
            return "Operación cancelada. ¿En qué más puedo ayudarte? Escribe 'menu' para ver opciones.";
        }

        // Manejar según estado actual
        return match ($state['step'] ?? 'idle') {
            'awaiting_purchase_name'   => $this->handlePurchaseName($user, $message),
            'awaiting_purchase_amount' => $this->handlePurchaseAmount($user, $message),
            'awaiting_purchase_category' => $this->handlePurchaseCategory($user, $message),
            'awaiting_purchase_card'   => $this->handlePurchaseCard($user, $message),
            'awaiting_purchase_installments' => $this->handlePurchaseInstallments($user, $message),
            'idle'                     => $this->handleMenuSelection($user, $cleanMsg),
            default                    => null, 
        };
    }

    private function showMainMenu(User $user): array
    {
        $this->setState($user, ['step' => 'idle']);
        
        return [
            'type' => 'list',
            'text' => "🏦 *Menú Principal FinTrack*\n\n¡Hola! Bienvenido de nuevo. ¿Qué deseas hacer hoy?",
            'buttonText' => 'Ver Opciones',
            'options' => ['Registrar Gasto', 'Mis Tarjetas', 'Resumen Deuda']
        ];
    }

    private function handleMenuSelection(User $user, string $msg): string|array|null
    {
        if (str_contains($msg, '1') || str_contains($msg, 'registrar')) {
            $this->setState($user, ['step' => 'awaiting_purchase_name']);
            return "💸 *Iniciando registro de gasto*\n\n¿Qué compraste? (Ej: Almuerzo, Gasolina, Netflix...)";
        }

        if (str_contains($msg, '2') || str_contains($msg, 'tarjetas')) {
            $cards = CreditCard::where('user_id', $user->id)->get();
            if ($cards->isEmpty()) return "No tienes tarjetas registradas.";
            
            $text = "💳 *Tus Tarjetas:*\n" . $cards->map(fn($c) => "- {$c->name}")->implode("\n");
            return $text . "\n\nEscribe 'menu' para volver.";
        }

        if (str_contains($msg, '3') || str_contains($msg, 'resumen')) {
            $summary = app(\App\Services\Fintrack\DebtSummaryService::class)->dashboard($user);
            $deuda = "$" . number_format($summary['total_debt'] ?? 0, 0, ',', '.');
            $vencido = "$" . number_format($summary['overdue_debt'] ?? 0, 0, ',', '.');
            
            $text = "📉 *Resumen de tu Deuda:*\n\n"
                  . "💰 *Deuda Total:* {$deuda}\n"
                  . "⚠️ *Vencido:* {$vencido}\n"
                  . "📅 *Cortes Próximos:* " . count($summary['upcoming_cuts'] ?? []) . "\n\n"
                  . "Escribe 'menu' para volver.";
            return $text;
        }

        return "No entendí esa opción. Escribe 'menu' para ver las opciones disponibles.";
    }

    // --- FLUJO DE REGISTRO PASO A PASO ---

    private function handlePurchaseName(User $user, string $name): string
    {
        if (strlen($name) < 2) return "Por favor, dime una descripción más clara de tu gasto.";
        
        $this->updateState($user, [
            'step' => 'awaiting_purchase_amount',
            'data' => ['name' => $name]
        ]);
        
        return "💰 ¿Cuánto costó '{$name}'?\n(Solo números, ej: 15000)";
    }

    private function handlePurchaseAmount(User $user, string $amountStr): string|array
    {
        $amount = (float) preg_replace('/[^0-9]/', '', $amountStr);
        if ($amount <= 0) return "Monto no válido. Por favor ingresa solo números (ej: 25000).";

        $state = $this->getState($user);
        $state['data']['total_amount'] = $amount;
        $state['step'] = 'awaiting_purchase_category';
        $this->setState($user, $state);

        $categories = Category::where('user_id', $user->id)->limit(10)->get();
        
        return [
            'type' => 'list',
            'text' => "🏷️ ¿En qué categoría clasificarías este gasto?",
            'buttonText' => 'Elegir Categoría',
            'options' => $categories->pluck('name')->map(fn($n) => Str::limit($n, 20))->toArray()
        ];
    }

    private function handlePurchaseCategory(User $user, string $categoryName): string|array
    {
        $category = Category::where('user_id', $user->id)
            ->whereRaw('LOWER(name) LIKE ?', ["%" . mb_strtolower($categoryName) . "%"])
            ->first() ?? Category::where('user_id', $user->id)->first();

        $state = $this->getState($user);
        $state['data']['category_id'] = $category?->id ?? 1;
        $state['data']['category_name'] = $category?->name ?? 'Gastos';
        $state['step'] = 'awaiting_purchase_card';
        $this->setState($user, $state);

        $cards = CreditCard::where('user_id', $user->id)->get();
        
        return [
            'type' => 'list',
            'text' => "💳 ¿Con qué tarjeta pagaste los $" . number_format($state['data']['total_amount'], 0, ',', '.') . "?",
            'buttonText' => 'Seleccionar Tarjeta',
            'options' => $cards->pluck('name')->toArray()
        ];
    }

    private function handlePurchaseCard(User $user, string $cardName): string|array
    {
        $card = CreditCard::where('user_id', $user->id)
            ->whereRaw('LOWER(name) LIKE ?', ["%" . mb_strtolower($cardName) . "%"])
            ->first();

        if (!$card) return "No encontré esa tarjeta. Elige una de la lista.";

        $state = $this->getState($user);
        $state['data']['credit_card_id'] = $card->id;
        $state['data']['credit_card_name'] = $card->name;
        $state['step'] = 'awaiting_purchase_installments';
        $this->setState($user, $state);

        return [
            'type' => 'buttons',
            'text' => "⚡ ¿A cuántas cuotas?",
            'buttons' => ['1 cuota', '12 cuotas', '24 cuotas']
        ];
    }

    private function handlePurchaseInstallments(User $user, string $msg): string|array
    {
        $installments = (int) preg_replace('/[^0-9]/', '', $msg);
        if ($installments <= 0) $installments = 1;

        $state = $this->getState($user);
        $data = $state['data'];
        $data['installments_count'] = $installments;
        $data['purchase_date'] = now()->toDateString();
        $data['category_id'] = Category::where('user_id', $user->id)->first()?->id ?? 1;
        $data['confidence'] = 'high';

        // Usamos el AiAssistantService para mostrar la confirmación final y manejar el caché
        // Esto integra el Bot con el flujo de persistencia existente.
        $this->clearState($user); // Clear state before calling preparePurchaseManual

        // La confirmación final viene de preparePurchaseManual
        $res = $this->aiService->preparePurchaseManual($user, $data, true);
        
        // Convertir la respuesta de confirmación a botones si es un texto
        $text = is_array($res) ? ($res['text'] ?? '') : $res;
        
        return [
            'type' => 'buttons',
            'text' => $text,
            'buttons' => ['✅ Sí, registrar', '❌ No, cancelar']
        ];
    }

    // --- HELPERS DE ESTADO ---

    private function getState(User $user): array
    {
        return Cache::get(self::CACHE_PREFIX . $user->id, ['step' => 'idle', 'data' => []]);
    }

    private function setState(User $user, array $state): void
    {
        Cache::put(self::CACHE_PREFIX . $user->id, $state, now()->addMinutes(self::TTL));
    }

    private function updateState(User $user, array $newData): void
    {
        $state = array_merge($this->getState($user), $newData);
        $this->setState($user, $state);
    }

    private function clearState(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX . $user->id);
    }
}
