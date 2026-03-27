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

        // Comandos globales (Escritura estricta)
        if (in_array($cleanMsg, ['menu', 'inicio', 'hola', 'ayuda', '?', '/start'])) {
            return $this->showMainMenu($user);
        }

        if (in_array($cleanMsg, ['resumen', 'deuda', 'saldo', '3'])) {
            $this->clearState($user);
            return $this->handleMenuSelection($user, '3');
        }

        if (in_array($cleanMsg, ['cancelar', '0', 'salir', 'no', 'detener'])) {
            $this->clearState($user);
            return "❌ Operación cancelada. ¿En qué más puedo ayudarte? Escribe 'menu' para ver opciones.";
        }

        // Manejar según estado actual
        $response = match ($state['step'] ?? 'idle') {
            'awaiting_purchase_name'   => $this->handlePurchaseName($user, $message),
            'awaiting_purchase_amount' => $this->handlePurchaseAmount($user, $message),
            'awaiting_purchase_date'   => $this->handlePurchaseDate($user, $message),
            'awaiting_purchase_category' => $this->handlePurchaseCategory($user, $message),
            'awaiting_purchase_card'   => $this->handlePurchaseCard($user, $message),
            'awaiting_purchase_installments' => $this->handlePurchaseInstallments($user, $message),
            'awaiting_purchase_split'    => $this->handlePurchaseSplit($user, $message),
            'awaiting_purchase_responsibles' => $this->handlePurchaseResponsibles($user, $message),
            'awaiting_confirmation'    => $this->handleConfirmation($user, $message),
            'idle'                     => $this->handleMenuSelection($user, $cleanMsg),
            default                    => $this->showMainMenu($user), 
        };

        return $response ?? "No entendí ese comando. Escribe 'menu' para ver las opciones disponibles.";
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
        $clean = trim(mb_strtolower($msg));

        if ($clean === '1' || str_contains($clean, 'registrar') || str_contains($clean, 'gasto') || str_contains($clean, 'compra')) {
            $this->setState($user, ['step' => 'awaiting_purchase_name']);
            return "💸 *Iniciando registro de gasto*\n\n¿Qué compraste? (Ej: Almuerzo, Gasolina, Netflix...)";
        }

        if ($clean === '2' || str_contains($clean, 'tarjetas') || str_contains($clean, 'mis tarjetas')) {
            $cards = CreditCard::where('user_id', $user->id)->orderBy('id')->get();
            if ($cards->isEmpty()) return "No tienes tarjetas registradas.";
            
            $text = "💳 *Tus Tarjetas:*\n" . $cards->map(fn($c) => "- {$c->name}")->implode("\n");
            return $text . "\n\nEscribe 'menu' para volver.";
        }

        if ($clean === '3' || str_contains($clean, 'resumen') || str_contains($clean, 'deuda')) {
            $summary = app(\App\Services\Fintrack\DebtSummaryService::class)->dashboard($user);
            $deuda = "$" . number_format($summary['total_debt'] ?? 0, 0, ',', '.');
            $vencido = "$" . number_format($summary['overdue_debt'] ?? 0, 0, ',', '.');
            
            $text = "📉 *Resumen de tu Deuda:*\n\n"
                  . "💰 *Deuda Total:* {$deuda}\n"
                  . "⚠️ *Vencido:* {$vencido}\n";

            if (!empty($summary['upcoming_cuts'])) {
                $next = $summary['upcoming_cuts'][0];
                $payAmount = "$" . number_format($next['remaining'], 0, ',', '.');
                $card = $next['card_name'];
                
                // Intentar construir una fecha de pago amigable
                $periodEnd = \Carbon\Carbon::parse($next['period_end']);
                $paymentDate = $periodEnd->day($next['payment_day']);
                // Si el día de pago es menor al de corte, suele ser del mes siguiente
                if ($next['payment_day'] < $periodEnd->day) {
                    $paymentDate = $paymentDate->addMonth();
                }
                $fechaStr = $paymentDate->translatedFormat('d \d\e M');

                $text .= "🗓️ *Próximo Pago:* {$payAmount}\n"
                       . "💳 *Tarjeta:* {$card}\n"
                       . "📅 *Límite:* {$fechaStr}\n";

                if (!empty($next['summary_by_party'])) {
                    $text .= "\n👥 *Desglose este corte:*\n";
                    foreach ($next['summary_by_party'] as $p) {
                        $amt = "$" . number_format($p['amount'], 0, ',', '.');
                        $text .= "• {$p['label']}: {$amt}\n";
                    }
                }
            }

            if (!empty($summary['debts_by_party'])) {
                $text .= "\n👤 *Deuda Total por Persona:*\n";
                foreach ($summary['debts_by_party'] as $p) {
                    $amt = "$" . number_format($p['amount'], 0, ',', '.');
                    $text .= "• {$p['label']}: {$amt}\n";
                }
            }

            $text .= "\nEscribe 'menu' para volver.";
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
        $state['step'] = 'awaiting_purchase_date';
        $this->setState($user, $state);

        $hoy = now()->translatedFormat('d/m');

        return "📅 *¿Cuándo realizaste este gasto?*\n\n"
             . "1️⃣ Hoy ($hoy)\n"
             . "2️⃣ Otra fecha (Escribe la fecha ej: 25/03)\n\n"
             . "Responde con el número de tu elección.";
    }

    private function handlePurchaseDate(User $user, string $msg): string|array
    {
        $clean = trim(mb_strtolower($msg));
        $date = null;

        if ($clean === '1' || str_contains($clean, 'hoy')) {
            $date = now()->toDateString();
        } else {
            // Intentar parsear fecha dd/mm o dd/mm/yyyy
            try {
                // Si solo mandan dd/mm, asumimos año actual
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $clean, $matches)) {
                    $date = now()->year . '-' . $matches[2] . '-' . $matches[1];
                } else {
                    $date = \Illuminate\Support\Carbon::parse($clean)->toDateString();
                }
            } catch (\Throwable) {
                return "No entendí la fecha. Por favor escribe algo como '25/03' o selecciona '1' para Hoy.";
            }
        }

        $state = $this->getState($user);
        $state['data']['purchase_date'] = $date;
        $state['step'] = 'awaiting_purchase_category';
        $this->setState($user, $state);

        $categories = Category::where('user_id', $user->id)->orderBy('id')->limit(10)->get();
        $list = $categories->map(fn($c, $i) => ($i + 1) . "️⃣ " . $c->name)->implode("\n");
        
        return "🏷️ *¿En qué categoría clasificarías este gasto?*\n\n"
             . "{$list}\n\n"
             . "Responde con el número o nombre de la categoría.";
    }

    private function handlePurchaseCategory(User $user, string $categoryName): string|array
    {
        $categories = Category::where('user_id', $user->id)->orderBy('id')->limit(10)->get();
        $category = null;

        // Soporte para número
        if (is_numeric(trim($categoryName))) {
            $index = (int)trim($categoryName) - 1;
            if (isset($categories[$index])) {
                $category = $categories[$index];
            }
        }

        if (!$category) {
            $category = Category::where('user_id', $user->id)
                ->whereRaw('LOWER(name) LIKE ?', ["%" . mb_strtolower($categoryName) . "%"])
                ->first();
        }

        if (!$category) {
            $list = $categories->map(fn($c, $i) => ($i + 1) . "️⃣ " . $c->name)->implode("\n");
            return "⚠️ No encontré esa categoría. Por favor intenta de nuevo:\n\n{$list}";
        }

        $state = $this->getState($user);
        $state['data']['category_id'] = $category->id;
        $state['data']['category_name'] = $category->name;
        $state['step'] = 'awaiting_purchase_card';
        $this->setState($user, $state);

        $cards = CreditCard::where('user_id', $user->id)->orderBy('id')->get();
        $list = $cards->map(fn($c, $i) => ($i + 1) . "️⃣ " . $c->name)->implode("\n");
        
        $monto = number_format($state['data']['total_amount'], 0, ',', '.');
        return "💳 *¿Con qué tarjeta pagaste los $$monto?*\n\n"
             . "{$list}\n\n"
             . "Responde con el número o nombre de la tarjeta.";
    }

    private function handlePurchaseCard(User $user, string $cardName): string|array
    {
        $cards = CreditCard::where('user_id', $user->id)->orderBy('id')->get();
        $card = null;

        // Soporte para número
        if (is_numeric(trim($cardName))) {
            $index = (int)trim($cardName) - 1;
            if (isset($cards[$index])) {
                $card = $cards[$index];
            }
        }

        if (!$card) {
            $card = CreditCard::where('user_id', $user->id)
                ->whereRaw('LOWER(name) LIKE ?', ["%" . mb_strtolower($cardName) . "%"])
                ->first();
        }

        if (!$card) return "No encontré esa tarjeta. Elige una de la lista.";

        $state = $this->getState($user);
        $state['data']['credit_card_id'] = $card->id;
        $state['data']['credit_card_name'] = $card->name;
        $state['step'] = 'awaiting_purchase_installments';
        $this->setState($user, $state);

        return "⚡ *¿A cuántas cuotas?*\n\n"
             . "Escribe el número de cuotas (ej: 1, 3, 6, 12, 24...)";
    }

    private function handlePurchaseInstallments(User $user, string $msg): string|array
    {
        $installments = (int) preg_replace('/[^0-9]/', '', $msg);
        if ($installments <= 0) $installments = 1;

        $state = $this->getState($user);
        $state['data']['installments_count'] = $installments;
        $state['data']['confidence'] = 'high';
        $state['step'] = 'awaiting_purchase_split';
        $this->setState($user, $state);

        return "👥 *¿Deseas dividir este gasto con alguien más?*\n\n"
             . "1️⃣ No, solo yo\n"
             . "2️⃣ Sí, repartir con otros\n\n"
             . "Responde con el número de tu elección.";
    }

    private function handlePurchaseSplit(User $user, string $msg): string|array
    {
        $clean = trim($msg);
        
        if ($clean === '1' || str_contains(mb_strtolower($clean), 'no')) {
            return $this->goToConfirmation($user);
        }

        if ($clean === '2' || str_contains(mb_strtolower($clean), 'si')) {
            $responsibles = \App\Models\ResponsiblePerson::where('user_id', $user->id)->orderBy('id')->get();
            if ($responsibles->isEmpty()) {
                return "⚠️ No tienes responsables registrados. " . $this->goToConfirmation($user);
            }

            $state = $this->getState($user);
            $state['step'] = 'awaiting_purchase_responsibles';
            $this->setState($user, $state);

            $list = $responsibles->map(fn($r, $i) => ($i + 1) . "️⃣ " . $r->name)->implode("\n");
            return "👥 *Selecciona los responsables (puedes elegir varios separando por coma, ej: 1, 2):*\n\n"
                 . "{$list}\n\n"
                 . "Responde con los números correspondientes.";
        }

        return "No entendí. ¿Deseas dividir el gasto?\n\n1️⃣ No\n2️⃣ Sí";
    }

    private function handlePurchaseResponsibles(User $user, string $msg): string|array
    {
        $responsibles = \App\Models\ResponsiblePerson::where('user_id', $user->id)->orderBy('id')->get();
        // Limpiamos el mensaje para obtener solo números y comas
        $clean = preg_replace('/[^0-9,]/', '', $msg);
        $parts = explode(',', $clean);
        $selectedIds = [];

        foreach ($parts as $part) {
            $index = (int)trim($part) - 1;
            if (isset($responsibles[$index])) {
                $selectedIds[] = $responsibles[$index]->id;
            }
        }

        if (empty($selectedIds)) {
            return "⚠️ No reconocí ningún número de la lista. Por favor intenta de nuevo.";
        }

        $state = $this->getState($user);
        
        // Preparar estructura para PurchaseService
        // Dividimos el 100% entre (yo + responsables seleccionados)
        $totalPeople = count($selectedIds) + 1;
        $percentage = round(100 / $totalPeople, 2);
        
        $respData = [];
        foreach ($selectedIds as $id) {
            $respData[] = [
                'responsible_id' => $id,
                'split_type' => 'porcentaje',
                'split_value' => $percentage
            ];
        }

        $state['data']['responsibles'] = $respData;
        $this->setState($user, $state);

        return $this->goToConfirmation($user);
    }

    private function goToConfirmation(User $user): string|array
    {
        $state = $this->getState($user);
        $state['step'] = 'awaiting_confirmation';
        $this->setState($user, $state);

        // Previsualización manual
        $res = $this->aiService->preparePurchaseManual($user, $state['data'], true);
        
        $text = is_array($res) ? ($res['text'] ?? '') : $res;
        
        return "{$text}\n\n"
             . "1️⃣ Sí, registrar\n"
             . "2️⃣ No, cancelar\n\n"
             . "Responde con el número de tu elección.";
    }

    private function handleConfirmation(User $user, string $msg): string|array
    {
        $clean = trim(mb_strtolower($msg));
        
        // Triggers de confirmación estrictos
        $confirms = ['si', 'sí', '1', 'si, registrar', 'sí, registrar', 'dale', 'ok', 'listo'];
        $rejects = ['no', '2', 'no, cancelar', 'cancelar', '0'];

        if (in_array($clean, $confirms)) {
            $state = $this->getState($user);
            $res = app(AiAssistantService::class)->executePurchase($user, true);
            $this->clearState($user);
            
            return is_array($res) ? ($res['text'] ?? "✅ ¡Gasto registrado!") : $res;
        }

        if (in_array($clean, $rejects)) {
            $this->clearState($user);
            return "❌ Operación cancelada. ¿En qué más puedo ayudarte?";
        }

        return "⚠️ No entendí. ¿Confirmas el registro?\n\n1️⃣ Sí\n2️⃣ No";
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
