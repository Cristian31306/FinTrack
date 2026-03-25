<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\ResponsiblePerson;
use App\Models\CardPayment;
use App\Models\Purchase;
use App\Models\Cut;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\PurchaseService;
use App\Services\Fintrack\CutService;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAssistantService
{
    protected string $baseUrl     = 'https://api.groq.com/openai/v1/chat/completions';
    protected string $textModel   = 'llama-3.3-70b-versatile';
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    private const REJECT_TRIGGERS = [
        'no', 'nopes', 'cancelar', 'nada', 'ninguno', 'borra', 'olvídalo', 'incorrecto',
    ];

    private const CONFIRM_TRIGGERS = [
        'si', 'sí', 'dale', 'ok', 'okay', 'confirmado', 'confirmo',
        'registrar', 'procede', 'págalo', 'hágale', 'adelante',
        'listo', 'perfecto', 'correcto', 'exacto', 'guardar',
    ];

    private const CACHE_TTL_MINUTES = 10;

    public function __construct(
        protected PurchaseService    $purchaseService,
        protected DebtSummaryService $summaryService,
        protected CutService         $cutService,
    ) {}

    private function isRejectionMessage(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if (str_word_count($lower) <= 3) {
            foreach (self::REJECT_TRIGGERS as $trigger) {
                if (str_contains($lower, $trigger)) return true;
            }
        }
        return false;
    }

    private function clearAllPendingCaches(User $user): void
    {
        $keys = [
            $this->cacheKey($user),
            "fintrack_pending_category_{$user->id}",
            "fintrack_pending_responsible_{$user->id}",
            "fintrack_pending_payment_{$user->id}",
            "fintrack_pending_card_{$user->id}",
            "fintrack_pending_edit_purchase_{$user->id}",
            "fintrack_pending_delete_purchase_{$user->id}",
            "fintrack_pending_delete_category_{$user->id}",
            "fintrack_pending_delete_responsible_{$user->id}",
        ];
        foreach ($keys as $key) Cache::forget($key);
    }

    /**
     * @return array|string
     */
    public function chat(User $user, string $message, array $history = [], ?array $image = null, bool $isWhatsApp = false)
    {
        if ($this->isRejectionMessage($message)) {
            $this->clearAllPendingCaches($user);
            return "Entendido, he cancelado la operación pendiente. ¿En qué más puedo ayudarte?";
        }

        $context      = $this->buildUserContext($user);
        $systemPrompt = $this->buildSystemPrompt($user->name, $context);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $messages = array_merge($messages, $this->reconstructHistory($history));

        if ($image) {
            $messages[] = ['role' => 'user', 'content' => $message];
            return $this->chatWithVision($messages, $message, $image, $isWhatsApp);
        }

        $messages[] = ['role' => 'user', 'content' => $message];
        return $this->callGroq($messages, $user, $context, $message, $isWhatsApp);
    }

    private function callGroq(array $messages, User $user, array $context, string $rawMessage, bool $isWhatsApp)
    {
        $isConfirm = $this->isConfirmationMessage($rawMessage);
        $toolChoice = 'auto';

        if ($isConfirm) {
            $keyPurchase    = $this->cacheKey($user);
            $keyCategory    = "fintrack_pending_category_{$user->id}";
            $keyResponsible = "fintrack_pending_responsible_{$user->id}";
            $keyPayment     = "fintrack_pending_payment_{$user->id}";
            $keyCard        = "fintrack_pending_card_{$user->id}";
            $keyEditPurch   = "fintrack_pending_edit_purchase_{$user->id}";
            $keyDelPurch    = "fintrack_pending_delete_purchase_{$user->id}";
            $keyDelCat      = "fintrack_pending_delete_category_{$user->id}";
            $keyDelResp     = "fintrack_pending_delete_responsible_{$user->id}";

            if (Cache::has($keyPurchase))     $toolChoice = ['type' => 'function', 'function' => ['name' => 'create_purchase']];
            elseif (Cache::has($keyCategory)) $toolChoice = ['type' => 'function', 'function' => ['name' => 'create_category']];
            elseif (Cache::has($keyResponsible)) $toolChoice = ['type' => 'function', 'function' => ['name' => 'create_responsible']];
            elseif (Cache::has($keyPayment))  $toolChoice = ['type' => 'function', 'function' => ['name' => 'create_payment']];
            elseif (Cache::has($keyCard))     $toolChoice = ['type' => 'function', 'function' => ['name' => 'create_card']];
            elseif (Cache::has($keyEditPurch)) $toolChoice = ['type' => 'function', 'function' => ['name' => 'edit_purchase']];
            elseif (Cache::has($keyDelPurch)) $toolChoice = ['type' => 'function', 'function' => ['name' => 'delete_purchase']];
            elseif (Cache::has($keyDelCat))   $toolChoice = ['type' => 'function', 'function' => ['name' => 'delete_category']];
            elseif (Cache::has($keyDelResp))  $toolChoice = ['type' => 'function', 'function' => ['name' => 'delete_responsible']];
        }

        $payload = [
            'model'       => $this->textModel,
            'messages'    => $messages,
            'tools'       => $this->getToolsDefinition(),
            'tool_choice' => $toolChoice,
            'temperature' => 0.3,
            'max_tokens'  => 1024,
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . config('services.groq.key')])
                ->post($this->baseUrl, $payload);

            if ($response->failed()) {
                Log::error('[FinTrack AI] Groq HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
                return "Lo siento, tuve un problema de comunicación.";
            }

            $choice = $response->json('choices.0.message');
            if (empty($choice)) return "No obtuve una respuesta válida.";

            if (!empty($choice['tool_calls'])) {
                $call     = $choice['tool_calls'][0];
                $funcName = $call['function']['name'];
                $args     = json_decode($call['function']['arguments'], true) ?? [];

                return match ($funcName) {
                    'prepare_purchase'    => $this->handlePrepare($args, $user, $context, $isWhatsApp),
                    'create_purchase'     => $this->handleExecute($user, $isWhatsApp),
                    'prepare_category'    => $this->handlePrepareCategory($args, $user, $isWhatsApp),
                    'create_category'     => $this->handleExecuteCategory($user, $isWhatsApp),
                    'prepare_responsible' => $this->handlePrepareResponsible($args, $user, $isWhatsApp),
                    'create_responsible'  => $this->handleExecuteResponsible($user, $isWhatsApp),
                    'prepare_payment'     => $this->handlePreparePayment($args, $user, $context, $isWhatsApp),
                    'create_payment'      => $this->handleExecutePayment($user, $isWhatsApp),
                    'prepare_card'        => $this->handlePrepareCard($args, $user, $isWhatsApp),
                    'create_card'         => $this->handleExecuteCard($user, $isWhatsApp),
                    'prepare_edit_purchase'   => $this->handlePrepareEditPurchase($args, $user, $context, $isWhatsApp),
                    'edit_purchase'           => $this->handleExecuteEditPurchase($user, $isWhatsApp),
                    'prepare_delete_purchase' => $this->handlePrepareDeletePurchase($args, $user, $context, $isWhatsApp),
                    'delete_purchase'         => $this->handleExecuteDeletePurchase($user, $isWhatsApp),
                    'prepare_delete_category' => $this->handlePrepareDeleteCategory($args, $user, $context, $isWhatsApp),
                    'delete_category'         => $this->handleExecuteDeleteCategory($user, $isWhatsApp),
                    'prepare_delete_responsible' => $this->handlePrepareDeleteResponsible($args, $user, $context, $isWhatsApp),
                    'delete_responsible'         => $this->handleExecuteDeleteResponsible($user, $isWhatsApp),
                    default               => "Función desconocida: {$funcName}.",
                };
            }

            $respText = $choice['content'] ?? "Entendido.";
            return $isWhatsApp ? $this->formatForWhatsApp($respText) : Str::markdown($respText);

        } catch (\Exception $e) {
            Log::error('[FinTrack AI] Excepción en callGroq', ['error' => $e->getMessage()]);
            return "Ocurrió un error interno.";
        }
    }

    private function handlePrepare(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $name   = trim($args['name'] ?? '');
        $amount = (float) ($args['total_amount'] ?? 0);

        if (strlen($name) < 2) return "Necesito una descripción más clara.";
        if ($amount <= 0) return "Necesito el monto para continuar.";

        $args = $this->resolveCategory($args, $context);
        $args = $this->resolveCard($args, $context);
        $args['purchase_date'] = $this->resolveDate($args['purchase_date'] ?? null);

        Cache::put($this->cacheKey($user), $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $preview = $this->buildPreviewMarkdown($args, $amount, $isWhatsApp);

        if ($isWhatsApp) {
            return ['text' => $preview, 'buttons' => ['✅ Sí, registrar', '❌ No, cancelar']];
        }
        return $preview;
    }

    private function handleExecute(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get($this->cacheKey($user));
        if (!$pending) return "❌ La sesión expiró.";
        try {
            $purchase = $this->purchaseService->create($pending, $user->id);
            Cache::forget($this->cacheKey($user));
            $valor = '$' . number_format($purchase->total_amount, 0, ',', '.');
            $msg = "✅ **Gasto registrado: {$purchase->name} por {$valor}.**";
            return $isWhatsApp ? $this->formatForWhatsApp($msg) : $msg;
        } catch (\Exception $e) { return "❌ Error al guardar."; }
    }

    private function handlePrepareCategory(array $args, User $user, bool $isWhatsApp)
    {
        Cache::put("fintrack_pending_category_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "🛠️ **Crear categoría: {$args['name']}**\n¿Confirmas?";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['✅ Sí, crear', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteCategory(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_category_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            $category = Category::create(array_merge($pending, ['user_id' => $user->id]));
            Cache::forget("fintrack_pending_category_{$user->id}");
            return "✅ Categoría '{$category->name}' creada.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareResponsible(array $args, User $user, bool $isWhatsApp)
    {
        Cache::put("fintrack_pending_responsible_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "👤 **Registrar responsable: {$args['name']}**\n¿Confirmas?";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['✅ Sí, registrar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteResponsible(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_responsible_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            $person = ResponsiblePerson::create(array_merge($pending, ['user_id' => $user->id]));
            Cache::forget("fintrack_pending_responsible_{$user->id}");
            return "✅ Responsible {$person->name} registrado.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePreparePayment(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $cutId = (int) ($args['cut_id'] ?? 0);
        $amount = (float) ($args['amount'] ?? 0);
        $upcomingCuts = collect($context['upcoming_cuts'] ?? []);

        if ($cutId <= 0) {
            $cardName = $args['card_name'] ?? '';
            $focusedCut = $upcomingCuts->filter(function($c) use ($cardName) {
                $name = is_array($c) ? ($c['card_name'] ?? '') : ($c->card_name ?? '');
                return str_contains(strtolower($name), strtolower($cardName));
            })->first();
            if (!$focusedCut) return "No encontré corte para esa tarjeta.";
            
            $cutId = (int) (is_array($focusedCut) ? $focusedCut['cut_id'] : $focusedCut->cut_id);
            $remaining = (float) (is_array($focusedCut) ? $focusedCut['remaining'] : $focusedCut->remaining);
            $periodEnd = is_array($focusedCut) ? $focusedCut['period_end'] : $focusedCut->period_end;
            
            $amount = $amount ?: $remaining;
            $args['card_name'] = is_array($focusedCut) ? $focusedCut['card_name'] : $focusedCut->card_name;
            $args['period'] = CarbonImmutable::parse($periodEnd)->translatedFormat('M Y');
        } else {
             $focusedCut = $upcomingCuts->firstWhere('cut_id', $cutId);
             if ($focusedCut) {
                 $args['card_name'] = $focusedCut['card_name'];
                 $args['period'] = CarbonImmutable::parse($focusedCut['period_end'])->translatedFormat('M Y');
             }
        }
        $args['cut_id'] = $cutId;
        $args['amount'] = $amount;
        Cache::put("fintrack_pending_payment_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $val = '$' . number_format($amount, 0, ',', '.');
        $text = "💳 **Pago de {$val} en {$args['card_name']} ({$args['period']})**\n¿Confirmas?";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['✅ Sí, pagar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecutePayment(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_payment_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            $cut = Cut::findOrFail($pending['cut_id']);
            CardPayment::create(['cut_id' => $cut->id, 'credit_card_id' => $cut->credit_card_id, 'amount' => $pending['amount'], 'payment_date' => now()]);
            $this->cutService->recalculateCutTotals($cut);
            Cache::forget("fintrack_pending_payment_{$user->id}");
            return "✅ Pago registrado.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareCard(array $args, User $user, bool $isWhatsApp)
    {
        Cache::put("fintrack_pending_card_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "💳 **Crear tarjeta: {$args['name']}**\n¿Confirmas?";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['✅ Sí, crear', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteCard(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_card_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            CreditCard::create(array_merge($pending, ['user_id' => $user->id]));
            Cache::forget("fintrack_pending_card_{$user->id}");
            return "✅ Tarjeta creada.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareEditPurchase(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $purchase = Purchase::where('user_id', $user->id)->find($args['purchase_id']);
        if (!$purchase) return "Gasto no encontrado.";
        Cache::put("fintrack_pending_edit_purchase_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "✏️ **Editar '{$purchase->name}'**\n¿Confirmas los cambios?";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['✅ Sí, editar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteEditPurchase(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_edit_purchase_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            $purchase = Purchase::where('user_id', $user->id)->findOrFail($pending['purchase_id']);
            $this->purchaseService->fullUpdate($purchase, array_merge($purchase->toArray(), $pending));
            Cache::forget("fintrack_pending_edit_purchase_{$user->id}");
            return "✅ Gasto actualizado.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareDeletePurchase(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $purchase = Purchase::where('user_id', $user->id)->find($args['purchase_id']);
        if (!$purchase) return "No encontrado.";
        Cache::put("fintrack_pending_delete_purchase_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "⚠️ **¿Eliminar '{$purchase->name}'?**";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['🗑️ Sí, borrar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteDeletePurchase(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_delete_purchase_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            $purchase = Purchase::where('user_id', $user->id)->findOrFail($pending['purchase_id']);
            $this->purchaseService->delete($purchase);
            Cache::forget("fintrack_pending_delete_purchase_{$user->id}");
            return "🗑️ Eliminado.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareDeleteCategory(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $category = Category::where('user_id', $user->id)->find($args['category_id']);
        if (!$category) return "No encontrada.";
        Cache::put("fintrack_pending_delete_category_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "⚠️ **¿Eliminar categoría '{$category->name}'?**";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['🗑️ Sí, borrar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteDeleteCategory(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_delete_category_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            Category::where('user_id', $user->id)->findOrFail($pending['category_id'])->delete();
            Cache::forget("fintrack_pending_delete_category_{$user->id}");
            return "🗑️ Categoría eliminada.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function handlePrepareDeleteResponsible(array $args, User $user, array $context, bool $isWhatsApp)
    {
        $resp = ResponsiblePerson::where('user_id', $user->id)->find($args['responsible_id']);
        if (!$resp) return "No encontrado.";
        Cache::put("fintrack_pending_delete_responsible_{$user->id}", $args, now()->addMinutes(self::CACHE_TTL_MINUTES));
        $text = "⚠️ **¿Eliminar responsable '{$resp->name}'?**";
        if ($isWhatsApp) return ['text' => $text, 'buttons' => ['🗑️ Sí, borrar', '❌ Cancelar']];
        return $text;
    }

    private function handleExecuteDeleteResponsible(User $user, bool $isWhatsApp): string
    {
        $pending = Cache::get("fintrack_pending_delete_responsible_{$user->id}");
        if (!$pending) return "❌ Expiró.";
        try {
            ResponsiblePerson::where('user_id', $user->id)->findOrFail($pending['responsible_id'])->delete();
            Cache::forget("fintrack_pending_delete_responsible_{$user->id}");
            return "🗑️ Responsable eliminado.";
        } catch (\Exception $e) { return "❌ Error."; }
    }

    private function resolveCategory(array $args, array $context): array
    {
        $catId = (int) ($args['category_id'] ?? 0);
        $cats = collect($context['categories'] ?? []);
        $match = $cats->firstWhere('id', $catId);
        if ($match) { $args['category_id'] = $match['id']; $args['category_name'] = $match['name']; return $args; }
        $fallback = $cats->first();
        $args['category_id'] = $fallback['id'] ?? null;
        $args['category_name'] = $fallback['name'] ?? 'Sin categoría';
        $args['confidence'] = 'low';
        return $args;
    }

    private function resolveCard(array $args, array $context): array
    {
        $cardId = (int) ($args['credit_card_id'] ?? 0);
        $cards = collect($context['cards'] ?? []);
        $match = $cards->firstWhere('id', $cardId);
        if ($match) { $args['credit_card_id'] = $match['id']; $args['credit_card_name'] = $match['name']; return $args; }
        $fallback = $cards->first();
        $args['credit_card_id'] = $fallback['id'] ?? null;
        $args['credit_card_name'] = $fallback['name'] ?? 'Tarjeta';
        $args['confidence'] = 'low';
        return $args;
    }

    private function buildPreviewMarkdown(array $args, float $amount, bool $isWhatsApp): string
    {
        $dateLabel = Carbon::parse($args['purchase_date'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $amountFmt = '$' . number_format($amount, 0, ',', '.');
        if ($isWhatsApp) return "📋 *Vista previa:*\n🛒 {$args['name']}\n💰 {$amountFmt}\n💳 {$args['credit_card_name']}\n📅 {$dateLabel}\n¿Confirmas?";
        return "📋 **Vista previa:**\n- Gasto: {$args['name']}\n- Valor: {$amountFmt}\n¿Confirmas?";
    }

    private function reconstructHistory(array $history): array
    {
        $messages = [];
        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            $role = ($msg['role'] ?? 'bot') === 'bot' ? 'assistant' : 'user';
            if (str_contains($content, '✅')) continue;
            $messages[] = ['role' => $role, 'content' => $content];
        }
        return $messages;
    }

    private function getToolsDefinition(): array
    {
        return [
            ['type' => 'function', 'function' => ['name' => 'prepare_purchase', 'description' => 'Paso 1: Prepara un gasto.', 'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'total_amount' => ['type' => 'number'], 'credit_card_id' => ['type' => 'integer'], 'category_id' => ['type' => 'integer'], 'installments_count' => ['type' => 'integer'], 'purchase_date' => ['type' => 'string'], 'confidence' => ['type' => 'string', 'enum' => ['high', 'low']]], 'required' => ['name', 'total_amount', 'credit_card_id', 'category_id', 'confidence']]]],
            ['type' => 'function', 'function' => ['name' => 'create_purchase', 'description' => 'Paso 2: Guarda el gasto.', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_category', 'description' => 'Prepara una nueva categoría. El icono debe ser un nombre de Lucide Icon en PascalCase (ej: Utensils, ShoppingBag, Car, Zap, Home). No uses emojis en el nombre.', 'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'description' => 'Nombre limpio, sin emojis.'], 'icon' => ['type' => 'string', 'description' => 'Nombre del icono en PascalCase.'], 'color' => ['type' => 'string', 'description' => 'Color hexadecimal.']], 'required' => ['name', 'icon', 'color']]]],
            ['type' => 'function', 'function' => ['name' => 'create_category', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_responsible', 'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'email' => ['type' => 'string']], 'required' => ['name']]]],
            ['type' => 'function', 'function' => ['name' => 'create_responsible', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_payment', 'parameters' => ['type' => 'object', 'properties' => ['cut_id' => ['type' => 'integer'], 'amount' => ['type' => 'number'], 'card_name' => ['type' => 'string']], 'required' => ['card_name']]]],
            ['type' => 'function', 'function' => ['name' => 'create_payment', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_card', 'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'franchise' => ['type' => 'string'], 'credit_limit' => ['type' => 'number'], 'statement_day' => ['type' => 'integer'], 'payment_day' => ['type' => 'integer']], 'required' => ['name', 'credit_limit', 'statement_day', 'payment_day']]]],
            ['type' => 'function', 'function' => ['name' => 'create_card', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_edit_purchase', 'parameters' => ['type' => 'object', 'properties' => ['purchase_id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'total_amount' => ['type' => 'number']], 'required' => ['purchase_id']]]],
            ['type' => 'function', 'function' => ['name' => 'edit_purchase', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_delete_purchase', 'parameters' => ['type' => 'object', 'properties' => ['purchase_id' => ['type' => 'integer']], 'required' => ['purchase_id']]]],
            ['type' => 'function', 'function' => ['name' => 'delete_purchase', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_delete_category', 'parameters' => ['type' => 'object', 'properties' => ['category_id' => ['type' => 'integer']], 'required' => ['category_id']]]],
            ['type' => 'function', 'function' => ['name' => 'delete_category', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
            ['type' => 'function', 'function' => ['name' => 'prepare_delete_responsible', 'parameters' => ['type' => 'object', 'properties' => ['responsible_id' => ['type' => 'integer']], 'required' => ['responsible_id']]]],
            ['type' => 'function', 'function' => ['name' => 'delete_responsible', 'parameters' => ['type' => 'object', 'properties' => (object)[]]]],
        ];
    }

    private function buildUserContext(User $user): array
    {
        $dashboardItems = $this->summaryService->dashboard($user);
        $dashboardItems['categories'] = Category::where('user_id', $user->id)->get(['id', 'name', 'icon', 'color'])->toArray();
        $dashboardItems['responsibles'] = ResponsiblePerson::where('user_id', $user->id)->get(['id', 'name', 'email'])->toArray();
        $dashboardItems['recent_purchases'] = Purchase::where('user_id', $user->id)->with(['creditCard', 'category'])->limit(15)->get()->toArray();
        $dashboardItems['recent_payments'] = CardPayment::whereHas('cut.creditCard', fn($q) => $q->where('user_id', $user->id))->limit(10)->get()->toArray();
        $dashboardItems['cards'] = CreditCard::where('user_id', $user->id)->get()->toArray();
        return $dashboardItems;
    }

    private function buildSystemPrompt(string $userName, array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return <<<PROMPT
Eres FinTrack AI, el asistente personal de finanzas de {$userName}. 
Tu objetivo es ayudar a gestionar su dinero de forma inteligente y proactiva.

REGLAS DE ORO:
1. PERSONALIDAD: Responde siempre en Español (Colombia), con un tono amable, profesional y cercano. No uses disclaimers robóticos (como "no soy asesor financiero"). Si el usuario te saluda, sé cordial.
2. CONTEXTO: Tienes acceso total a los datos del usuario: {$contextJson}. Úsalos para dar respuestas específicas.
   - Si te piden consejos, analiza sus gastos recientes o deudas y diles algo útil basado en SUS datos.
   - Si preguntan por categorías o tarjetas, dales el detalle exacto que ves en los datos.
3. FLUJO DE 2 PASOS: Para cualquier creación, edición o borrado, SIEMPRE usa el flujo de 2 pasos:
   - Paso 1: Llama a la función 'prepare_...' para mostrar una vista previa y pedir confirmación.
   - Paso 2: Solo si el usuario confirma (dice "sí", "dale", etc.), llama a la función 'create/edit/delete_...'.
4. RECHAZO: Si el usuario dice "No", "Cancelar" o rechaza la vista previa, detente inmediatamente.
5. CONCISIÓN: En WhatsApp, sé directo y usa negritas (*) para resaltar valores y nombres.

No inventes datos. Si no ves la información en el contexto, pide aclaración.
PROMPT;
    }

    private function chatWithVision(array $messages, string $message, array $image, bool $isWhatsApp): string
    {
        array_pop($messages);
        $messages[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => $message], ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data']]]]];
        try {
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . config('services.groq.key')])->post($this->baseUrl, ['model' => $this->visionModel, 'messages' => $messages, 'temperature' => 0.4]);
            $msg = $response->json('choices.0.message.content') ?? "Error imagen.";
            return $isWhatsApp ? $this->formatForWhatsApp($msg) : $msg;
        } catch (\Exception $e) { return "Error visión."; }
    }

    private function isConfirmationMessage(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if (str_word_count($lower) <= 3) {
            foreach (self::CONFIRM_TRIGGERS as $trigger) { if (str_contains($lower, $trigger)) return true; }
        }
        return false;
    }

    private function resolveDate(?string $rawDate): string
    {
        if (!$rawDate) return now()->toDateString();
        try { return Carbon::parse($rawDate)->toDateString(); } catch (\Exception $e) { return now()->toDateString(); }
    }

    private function cacheKey(User $user): string
    {
        return "fintrack_pending_purchase_{$user->id}";
    }

    private function formatForWhatsApp(string $text): string
    {
        return strip_tags(preg_replace('/\*\*(.*?)\*\*/s', '*$1*', $text));
    }
}