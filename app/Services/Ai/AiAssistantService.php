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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * FinTrack AI Assistant Service
 *
 * Gestiona el chat con Gemini usando function-calling en un flujo de 2 pasos:
 *   1. prepare_*  → guarda datos en caché y muestra vista previa al usuario
 *   2. execute_*  → confirma y persiste en BD
 *
 * Cada entidad (purchase, category, responsible, card, payment) sigue exactamente
 * el mismo patrón, eliminando toda duplicación.
 */
class AiAssistantService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Configuración
    // ─────────────────────────────────────────────────────────────────────────

    private const BASE_URL     = 'https://generativelanguage.googleapis.com/v1beta/';

    private const CACHE_TTL    = 10;   // minutos
    private const MAX_TOKENS   = 1024;
    private const TEMPERATURE  = 0.3;
    private const HTTP_RETRIES = 10;
    private const HTTP_RETRY_MS = 3000;
    private const RECENT_PURCHASES_LIMIT = 15;
    private const RECENT_PAYMENTS_LIMIT  = 10;

    // ─────────────────────────────────────────────────────────────────────────
    // Vocabulario de intención
    // ─────────────────────────────────────────────────────────────────────────

    private const REJECT_TRIGGERS = [
        'no', 'nopes', 'cancelar', 'nada', 'ninguno', 'borra',
        'olvídalo', 'incorrecto', 'cancela', 'detente', 'parar',
    ];

    private const CONFIRM_TRIGGERS = [
        'si', 'sí', 'dale', 'ok', 'okay', 'confirmado', 'confirmo',
        'registrar', 'procede', 'págalo', 'hágale', 'adelante',
        'listo', 'perfecto', 'correcto', 'exacto', 'guardar', 'hazlo',
    ];

    private const ALLOWED_TOPICS = [
        'gasto', 'compra', 'pago', 'deuda', 'tarjeta', 'cupo', 'saldo',
        'categoría', 'responsable', 'corte', 'cuota', 'abono', 'banco',
        'presupuesto', 'ahorro', 'finanza', 'dinero', 'factura', 'recibo',
        'registrar', 'borrar', 'editar', 'crear', 'eliminar', 'listar',
        'historial', 'resumen', 'reporte', 'mes', 'fecha', 'interés',
        'hola', 'ayuda', 'qué puedes', 'cómo funciona', 'gracias',
        'cuánto', 'cuándo', 'cuál', 'cuáles', 'total', 'balance',
    ];

    private const OFF_TOPIC_PATTERNS = [
        'depresi', 'ansiedad', 'suicid', 'psicólog', 'terapis', 'autolesion',
        'elecciones', 'partido político', 'dios', 'religión',
        'receta', 'cocina', 'película', 'canción', 'juego', 'deporte',
        'código en python', 'escríbeme un programa', 'dame un script',
        'chiste', 'historia corta', 'poema', 'cuéntame algo',
    ];

    // Mapa función → clave de caché  (usado en prepare / execute / force)
    private const FUNCTION_CACHE_MAP = [
        'purchase'            => 'fintrack_pending_purchase_%d',
        'category'            => 'fintrack_pending_category_%d',
        'responsible'         => 'fintrack_pending_responsible_%d',
        'payment'             => 'fintrack_pending_payment_%d',
        'card'                => 'fintrack_pending_card_%d',
        'edit_purchase'       => 'fintrack_pending_edit_purchase_%d',
        'delete_purchase'     => 'fintrack_pending_delete_purchase_%d',
        'edit_category'       => 'fintrack_pending_edit_category_%d',
        'delete_category'     => 'fintrack_pending_delete_category_%d',
        'edit_responsible'    => 'fintrack_pending_edit_responsible_%d',
        'delete_responsible'  => 'fintrack_pending_delete_responsible_%d',
        'edit_card'           => 'fintrack_pending_edit_card_%d',
        'delete_card'         => 'fintrack_pending_delete_card_%d',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(
        private readonly PurchaseService    $purchaseService,
        private readonly DebtSummaryService $summaryService,
        private readonly CutService         $cutService,
    ) {}

    // =========================================================================
    // PUNTO DE ENTRADA PÚBLICO
    // =========================================================================

    /**
     * Procesa un mensaje del usuario y retorna una respuesta (string o array para WA).
     *
     * @param  User        $user
     * @param  string      $message   Texto del usuario
     * @param  array       $history   [{role, content}]
     * @param  array|null  $image     ['mime_type' => ..., 'data' => base64]
     * @param  bool        $isWhatsApp
     * @return string|array
     */
    public function chat(
        User   $user,
        string $message,
        array  $history = [],
        ?array $image   = null,
        bool   $isWhatsApp = false,
    ): string|array {
        // 1. Cancelación explícita
        if ($this->isRejection($message)) {
            $this->clearAllPending($user);
            return $this->fmt("Entendido, operación cancelada. ¿En qué más puedo ayudarte? 😊", $isWhatsApp);
        }

        // 2. Off-topic (solo texto, no imágenes)
        if (!$image && $this->isOffTopic($message)) {
            return $this->offTopicResponse($isWhatsApp);
        }

        // 3. Construir contexto y prompt
        $context      = $this->buildUserContext($user);
        $systemPrompt = $this->buildSystemPrompt($user->name, $context);
        $contents     = $this->buildContents($history, $message, $image);

        // 4. Forzar función si hay confirmación con caché pendiente
        $toolConfig = $this->resolveToolConfig($user, $message);

        return $this->callGemini($contents, $user, $context, $isWhatsApp, $systemPrompt, $toolConfig);
    }

    // =========================================================================
    // LLAMADA A GEMINI
    // =========================================================================

    private function callGemini(
        array   $contents,
        User    $user,
        array   $context,
        bool    $isWhatsApp,
        string  $systemPrompt,
        ?array  $toolConfig,
    ): string|array {
        set_time_limit(120);
        $payload = $this->buildPayload($contents, $systemPrompt, $toolConfig);
        $model   = config('services.gemini.model');
        if (!str_starts_with($model, 'models/')) {
            $model = 'models/' . $model;
        }
        $url     = self::BASE_URL . $model . ':generateContent';
        Log::info('[FinTrack AI] Llamando a Gemini', ['url' => $url, 'model_config' => config('services.gemini.model')]);



        try {
            $response = Http::withoutVerifying()
                ->retry(self::HTTP_RETRIES, self::HTTP_RETRY_MS)
                ->withHeaders(['X-goog-api-key' => config('services.gemini.key')])
                ->timeout(60)
                ->post($url, $payload);


            if ($response->failed()) {
                Log::error('[FinTrack AI] Gemini HTTP error', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'user_id' => $user->id,
                ]);
                return $this->errorResponse("Error de comunicación con la IA (HTTP {$response->status()}). Intenta de nuevo.", $isWhatsApp);
            }

            $json = $response->json();

            // Verificar bloqueo por safety
            $finishReason = data_get($json, 'candidates.0.finishReason');
            if ($finishReason === 'SAFETY') {
                Log::warning('[FinTrack AI] Respuesta bloqueada por safety', ['user_id' => $user->id]);
                return $this->fmt("No pude procesar esa solicitud. ¿Puedes reformularla?", $isWhatsApp);
            }

            $parts = data_get($json, 'candidates.0.content.parts', []);

            if (empty($parts)) {
                Log::warning('[FinTrack AI] Respuesta vacía de Gemini', ['response' => $json, 'user_id' => $user->id]);
                return $this->fmt("No obtuve una respuesta válida. Intenta de nuevo.", $isWhatsApp);
            }

            // Buscar function_call primero
            foreach ($parts as $part) {
                if (isset($part['function_call'])) {
                    return $this->dispatchFunctionCall(
                        $part['function_call']['name'],
                        $part['function_call']['args'] ?? [],
                        $user,
                        $context,
                        $isWhatsApp,
                    );
                }
            }

            // Solo texto
            $text = collect($parts)
                ->filter(fn($p) => isset($p['text']))
                ->pluck('text')
                ->implode("\n");

            return $isWhatsApp
                ? $this->formatForWhatsApp($text ?: "Entendido.")
                : (Str::markdown($text) ?: "Entendido.");

        } catch (Throwable $e) {
            Log::error('[FinTrack AI] Excepción en callGemini', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);
            return $this->errorResponse("Ocurrió un error interno. Por favor intenta de nuevo.", $isWhatsApp);
        }
    }

    // =========================================================================
    // DISPATCH DE FUNCIONES
    // =========================================================================

    private function dispatchFunctionCall(
        string $name,
        array  $args,
        User   $user,
        array  $context,
        bool   $isWhatsApp,
    ): string|array {
        return match ($name) {
            // ── COMPRAS ──────────────────────────────────────────────────────
            'prepare_purchase'        => $this->preparePurchase($args, $user, $context, $isWhatsApp),
            'create_purchase'         => $this->executePurchase($user, $isWhatsApp),

            // ── EDITAR / BORRAR COMPRAS ───────────────────────────────────
            'prepare_edit_purchase'   => $this->prepareEditPurchase($args, $user, $isWhatsApp),
            'edit_purchase'           => $this->executeGeneric(
                                            $user, 'edit_purchase', $isWhatsApp,
                                            fn($p) => $this->runEditPurchase($p, $user),
                                            "✅ Gasto actualizado correctamente."
                                        ),
            'prepare_delete_purchase' => $this->prepareDeleteEntity(
                                            'delete_purchase', $args['purchase_id'] ?? 0, $user, $isWhatsApp,
                                            fn($id) => Purchase::where('user_id', $user->id)->find($id)?->name,
                                            "gasto"
                                        ),
            'delete_purchase'         => $this->executeGeneric(
                                            $user, 'delete_purchase', $isWhatsApp,
                                            fn($p) => Purchase::where('user_id', $user->id)->findOrFail($p['purchase_id'])->delete(),
                                            "🗑️ Gasto eliminado."
                                        ),

            // ── CATEGORÍAS ───────────────────────────────────────────────────
            'prepare_category'        => $this->prepareSimpleEntity('category', $args, $user, $isWhatsApp,
                                            "🏷️ **Crear categoría: {$args['name']}**\n¿Confirmas?"),
            'create_category'         => $this->executeGeneric(
                                            $user, 'category', $isWhatsApp,
                                            fn($p) => Category::create(array_merge($p, ['user_id' => $user->id])),
                                            "✅ Categoría creada."
                                        ),
            'prepare_edit_category'   => $this->prepareEditSimple(
                                            'edit_category', $args, $user, $isWhatsApp,
                                            fn($id) => Category::where('user_id', $user->id)->find($id),
                                            "categoría"
                                        ),
            'edit_category'           => $this->executeGeneric(
                                            $user, 'edit_category', $isWhatsApp,
                                            fn($p) => Category::where('user_id', $user->id)->findOrFail($p['category_id'])->update($p),
                                            "✅ Categoría actualizada."
                                        ),
            'prepare_delete_category' => $this->prepareDeleteEntity(
                                            'delete_category', $args['category_id'] ?? 0, $user, $isWhatsApp,
                                            fn($id) => Category::where('user_id', $user->id)->find($id)?->name,
                                            "categoría"
                                        ),
            'delete_category'         => $this->executeGeneric(
                                            $user, 'delete_category', $isWhatsApp,
                                            fn($p) => Category::where('user_id', $user->id)->findOrFail($p['category_id'])->delete(),
                                            "🗑️ Categoría eliminada."
                                        ),

            // ── RESPONSABLES ─────────────────────────────────────────────────
            'prepare_responsible'        => $this->prepareSimpleEntity('responsible', $args, $user, $isWhatsApp,
                                                "👤 **Registrar responsable: {$args['name']}**\n¿Confirmas?"),
            'create_responsible'         => $this->executeGeneric(
                                                $user, 'responsible', $isWhatsApp,
                                                fn($p) => ResponsiblePerson::create(array_merge($p, ['user_id' => $user->id])),
                                                "✅ Responsable registrado."
                                            ),
            'prepare_edit_responsible'   => $this->prepareEditSimple(
                                                'edit_responsible', $args, $user, $isWhatsApp,
                                                fn($id) => ResponsiblePerson::where('user_id', $user->id)->find($id),
                                                "responsable"
                                            ),
            'edit_responsible'           => $this->executeGeneric(
                                                $user, 'edit_responsible', $isWhatsApp,
                                                fn($p) => ResponsiblePerson::where('user_id', $user->id)->findOrFail($p['responsible_id'])->update($p),
                                                "✅ Responsable actualizado."
                                            ),
            'prepare_delete_responsible' => $this->prepareDeleteEntity(
                                                'delete_responsible', $args['responsible_id'] ?? 0, $user, $isWhatsApp,
                                                fn($id) => ResponsiblePerson::where('user_id', $user->id)->find($id)?->name,
                                                "responsable"
                                            ),
            'delete_responsible'         => $this->executeGeneric(
                                                $user, 'delete_responsible', $isWhatsApp,
                                                fn($p) => ResponsiblePerson::where('user_id', $user->id)->findOrFail($p['responsible_id'])->delete(),
                                                "🗑️ Responsable eliminado."
                                            ),

            // ── TARJETAS ──────────────────────────────────────────────────────
            'prepare_card'        => $this->prepareCard($args, $user, $isWhatsApp),
            'create_card'         => $this->executeGeneric(
                                        $user, 'card', $isWhatsApp,
                                        fn($p) => $this->runCreateCard($p, $user),
                                        "✅ Tarjeta creada exitosamente."
                                    ),
            'prepare_edit_card'   => $this->prepareEditSimple(
                                        'edit_card', $args, $user, $isWhatsApp,
                                        fn($id) => CreditCard::where('user_id', $user->id)->find($id),
                                        "tarjeta"
                                    ),
            'edit_card'           => $this->executeGeneric(
                                        $user, 'edit_card', $isWhatsApp,
                                        fn($p) => $this->runEditCard($p, $user),
                                        "✅ Tarjeta actualizada."
                                    ),
            'prepare_delete_card' => $this->prepareDeleteEntity(
                                        'delete_card', $args['card_id'] ?? 0, $user, $isWhatsApp,
                                        fn($id) => CreditCard::where('user_id', $user->id)->find($id)?->name,
                                        "tarjeta", "⚠️ Se borrarán TODOS los gastos asociados."
                                    ),
            'delete_card'         => $this->executeGeneric(
                                        $user, 'delete_card', $isWhatsApp,
                                        fn($p) => CreditCard::where('user_id', $user->id)->findOrFail($p['card_id'])->delete(),
                                        "🗑️ Tarjeta y gastos asociados eliminados."
                                    ),

            // ── PAGOS ─────────────────────────────────────────────────────────
            'prepare_payment' => $this->preparePayment($args, $user, $context, $isWhatsApp),
            'create_payment'  => $this->executeGeneric(
                                    $user, 'payment', $isWhatsApp,
                                    fn($p) => $this->runCreatePayment($p),
                                    "✅ Pago registrado exitosamente."
                                ),

            default => $this->fmt("⚠️ Función no reconocida: {$name}. Por favor intenta de nuevo.", $isWhatsApp),
        };
    }

    // =========================================================================
    // HANDLERS GENÉRICOS (eliminan duplicación)
    // =========================================================================

    /**
     * Guarda args en caché y retorna texto de confirmación.
     */
    private function prepareSimpleEntity(
        string $entity,
        array  $args,
        User   $user,
        bool   $isWhatsApp,
        string $preview,
        array  $buttons = ['✅ Confirmar', '❌ Cancelar'],
    ): string|array {
        $this->putCache($entity, $user, $args);
        return $isWhatsApp
            ? ['text' => $preview, 'buttons' => $buttons]
            : $preview;
    }

    /**
     * Ejecuta una operación con datos del caché. Patrón uniforme para todos los entities.
     */
    private function executeGeneric(
        User     $user,
        string   $entity,
        bool     $isWhatsApp,
        callable $action,
        string   $successMsg,
    ): string {
        $pending = $this->getCache($entity, $user);
        if (!$pending) {
            return $this->fmt("❌ La sesión expiró. Por favor vuelve a iniciar la operación.", $isWhatsApp);
        }

        try {
            $action($pending);
            $this->forgetCache($entity, $user);
            return $this->fmt($successMsg, $isWhatsApp);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("[FinTrack AI] Registro no encontrado al ejecutar {$entity}", ['user_id' => $user->id]);
            $this->forgetCache($entity, $user);
            return $this->fmt("❌ No se encontró el registro. Es posible que ya haya sido eliminado.", $isWhatsApp);
        } catch (Throwable $e) {
            Log::error("[FinTrack AI] Error ejecutando {$entity}", [
                'error'   => $e->getMessage(),
                'pending' => $pending,
                'user_id' => $user->id,
            ]);
            return $this->fmt("❌ Ocurrió un error al guardar. Intenta de nuevo.", $isWhatsApp);
        }
    }

    /**
     * Prepara edición de una entidad simple (card, category, responsible).
     */
    private function prepareEditSimple(
        string   $entity,
        array    $args,
        User     $user,
        bool     $isWhatsApp,
        callable $finder,       // fn(int $id): ?Model
        string   $entityLabel,
    ): string|array {
        $idKey = array_key_first(array_filter($args, fn($v, $k) => str_ends_with($k, '_id'), ARRAY_FILTER_USE_BOTH));
        $id    = (int) ($args[$idKey] ?? 0);

        if (!$id || !($record = $finder($id))) {
            return $this->fmt("❌ {$entityLabel} no encontrada. Verifica el ID.", $isWhatsApp);
        }

        $this->putCache($entity, $user, $args);
        $preview = "✏️ **Editar {$entityLabel}: '{$record->name}'**\n¿Confirmas los cambios?";
        return $isWhatsApp
            ? ['text' => $preview, 'buttons' => ['✅ Sí, editar', '❌ Cancelar']]
            : $preview;
    }

    /**
     * Prepara eliminación de cualquier entidad.
     */
    private function prepareDeleteEntity(
        string   $entity,
        int      $id,
        User     $user,
        bool     $isWhatsApp,
        callable $nameFinder,    // fn(int $id): ?string
        string   $entityLabel,
        string   $extraWarning = '',
    ): string|array {
        if (!$id) {
            return $this->fmt("❌ Debes especificar qué {$entityLabel} deseas eliminar.", $isWhatsApp);
        }

        $name = $nameFinder($id);
        if (!$name) {
            return $this->fmt("❌ {$entityLabel} no encontrada.", $isWhatsApp);
        }

        $this->putCache($entity, $user, [$this->entityIdKey($entity) => $id]);

        $warn = $extraWarning ? "\n{$extraWarning}" : '';
        $text = "⚠️ **¿Eliminar {$entityLabel} '{$name}'?**{$warn}";

        return $isWhatsApp
            ? ['text' => $text, 'buttons' => ['🗑️ Sí, eliminar', '❌ Cancelar']]
            : $text;
    }

    // =========================================================================
    // HANDLERS ESPECÍFICOS (lógica propia)
    // =========================================================================

    private function preparePurchase(array $args, User $user, array $context, bool $isWhatsApp): string|array
    {
        $name   = trim($args['name'] ?? '');
        $amount = (float) ($args['total_amount'] ?? 0);

        if (strlen($name) < 2) {
            return $this->fmt("Necesito una descripción más clara del gasto.", $isWhatsApp);
        }
        if ($amount <= 0) {
            return $this->fmt("Necesito el monto del gasto para continuar.", $isWhatsApp);
        }

        $args = $this->resolveCategory($args, $context);
        $args = $this->resolveCard($args, $context);
        $args['purchase_date'] = $this->resolveDate($args['purchase_date'] ?? null);

        // Validar responsables si vienen
        if (!empty($args['responsibles'])) {
            $args['responsibles'] = $this->validateResponsibles($args['responsibles'], $context);
        }

        $this->putCache('purchase', $user, $args);

        $preview = $this->buildPurchasePreview($args, $amount, $isWhatsApp);

        return $isWhatsApp
            ? ['text' => $preview, 'buttons' => ['✅ Sí, registrar', '❌ No, cancelar']]
            : $preview;
    }

    private function executePurchase(User $user, bool $isWhatsApp): string
    {
        return $this->executeGeneric(
            $user, 'purchase', $isWhatsApp,
            function (array $p) use ($user): void {
                $purchase = $this->purchaseService->create($p, $user->id, $p['responsibles'] ?? null);
                // Guardamos el nombre para el mensaje de éxito
                Cache::put("fintrack_last_purchase_name_{$user->id}", $purchase->name, now()->addMinutes(1));
                Cache::put("fintrack_last_purchase_amount_{$user->id}", $purchase->total_amount, now()->addMinutes(1));
            },
            "✅ Gasto registrado exitosamente.",
        );
        // Enriquecemos el mensaje si tenemos el nombre
    }

    private function prepareEditPurchase(array $args, User $user, bool $isWhatsApp): string|array
    {
        $id = (int) ($args['purchase_id'] ?? 0);
        if (!$id) {
            return $this->fmt("❌ Debes indicar qué gasto deseas editar.", $isWhatsApp);
        }

        $purchase = Purchase::where('user_id', $user->id)->find($id);
        if (!$purchase) {
            return $this->fmt("❌ Gasto no encontrado.", $isWhatsApp);
        }

        $this->putCache('edit_purchase', $user, $args);

        $changes = collect($args)
            ->except(['purchase_id'])
            ->map(fn($v, $k) => "- {$k}: {$v}")
            ->implode("\n");

        $text = "✏️ **Editar '{$purchase->name}'**\nCambios:\n{$changes}\n¿Confirmas?";

        return $isWhatsApp
            ? ['text' => $text, 'buttons' => ['✅ Sí, editar', '❌ Cancelar']]
            : $text;
    }

    private function runEditPurchase(array $pending, User $user): void
    {
        $purchase = Purchase::where('user_id', $user->id)->findOrFail($pending['purchase_id']);
        $this->purchaseService->fullUpdate($purchase, array_merge($purchase->toArray(), $pending));
    }

    private function preparePayment(array $args, User $user, array $context, bool $isWhatsApp): string|array
    {
        $upcomingCuts = collect($context['upcoming_cuts'] ?? []);

        if ($upcomingCuts->isEmpty()) {
            return $this->fmt("❌ No tienes cortes próximos registrados. Asegúrate de tener tarjetas activas.", $isWhatsApp);
        }

        $cutId  = (int) ($args['cut_id'] ?? 0);
        $amount = (float) ($args['amount'] ?? 0);

        // Resolver corte por tarjeta si no viene cut_id
        if ($cutId <= 0) {
            $cardName   = $args['card_name'] ?? '';
            $focusedCut = $cardName
                ? $upcomingCuts->first(fn($c) => str_contains(
                    strtolower(data_get($c, 'card_name', '')),
                    strtolower($cardName)
                ))
                : $upcomingCuts->first();

            if (!$focusedCut) {
                return $this->fmt("❌ No encontré un corte para esa tarjeta.", $isWhatsApp);
            }

            $cutId     = (int) data_get($focusedCut, 'cut_id');
            $remaining = (float) data_get($focusedCut, 'remaining', 0);
            $amount    = $amount ?: $remaining;

            $args['card_name'] = data_get($focusedCut, 'card_name');
            $args['period']    = Carbon::parse(data_get($focusedCut, 'period_end'))->translatedFormat('M Y');
        } else {
            $focusedCut = $upcomingCuts->first(fn($c) => (int) data_get($c, 'cut_id') === $cutId);
            if ($focusedCut) {
                $args['card_name'] = data_get($focusedCut, 'card_name');
                $args['period']    = Carbon::parse(data_get($focusedCut, 'period_end'))->translatedFormat('M Y');
            }
        }

        if ($amount <= 0) {
            return $this->fmt("❌ El monto del pago debe ser mayor a cero.", $isWhatsApp);
        }

        $args['cut_id'] = $cutId;
        $args['amount'] = $amount;
        $this->putCache('payment', $user, $args);

        $val  = $this->formatMoney($amount);
        $text = "💳 **Pago de {$val} para {$args['card_name']} ({$args['period']})**\n¿Confirmas?";

        return $isWhatsApp
            ? ['text' => $text, 'buttons' => ['✅ Sí, pagar', '❌ Cancelar']]
            : $text;
    }

    private function runCreatePayment(array $pending): void
    {
        $cut = Cut::findOrFail($pending['cut_id']);
        CardPayment::create([
            'cut_id'         => $cut->id,
            'credit_card_id' => $cut->credit_card_id,
            'amount'         => $pending['amount'],
            'payment_date'   => now(),
        ]);
        $this->cutService->recalculateCutTotals($cut);
    }

    private function prepareCard(array $args, User $user, bool $isWhatsApp): string|array
    {
        $required = ['name', 'franchise', 'credit_limit', 'statement_day', 'payment_day'];
        foreach ($required as $field) {
            if (empty($args[$field])) {
                return $this->fmt("❌ Falta el campo requerido: **{$field}**. ¿Puedes proporcionarlo?", $isWhatsApp);
            }
        }

        $this->putCache('card', $user, $args);

        $limit = $this->formatMoney((float) $args['credit_limit']);
        $text  = "💳 **Nueva tarjeta:**\n"
               . "- Nombre: {$args['name']}\n"
               . "- Franquicia: {$args['franchise']}\n"
               . "- Cupo: {$limit}\n"
               . "- Corte día: {$args['statement_day']} / Pago día: {$args['payment_day']}\n"
               . "¿Confirmas?";

        return $isWhatsApp
            ? ['text' => $text, 'buttons' => ['✅ Sí, crear', '❌ Cancelar']]
            : $text;
    }

    private function runCreateCard(array $pending, User $user): void
    {
        $data = array_merge($pending, [
            'user_id'           => $user->id,
            'annual_interest_ea' => $pending['interest_rate'] ?? $pending['annual_interest_ea'] ?? 0,
        ]);
        unset($data['interest_rate']);
        CreditCard::create($data);
    }

    private function runEditCard(array $pending, User $user): void
    {
        $card = CreditCard::where('user_id', $user->id)->findOrFail($pending['card_id']);
        if (isset($pending['interest_rate'])) {
            $pending['annual_interest_ea'] = $pending['interest_rate'];
            unset($pending['interest_rate']);
        }
        $card->update($pending);
    }

    // =========================================================================
    // CONTEXTO Y SYSTEM PROMPT
    // =========================================================================

    private function buildUserContext(User $user): array
    {
        $dashboard = $this->summaryService->dashboard($user);

        return array_merge($dashboard, [
            'categories'       => Category::where('user_id', $user->id)
                                    ->get(['id', 'name', 'icon', 'color'])
                                    ->toArray(),
            'responsibles'     => ResponsiblePerson::where('user_id', $user->id)
                                    ->get(['id', 'name', 'email'])
                                    ->toArray(),
            'recent_purchases' => Purchase::where('user_id', $user->id)
                                    ->with(['creditCard:id,name', 'category:id,name'])
                                    ->orderByDesc('purchase_date')
                                    ->limit(self::RECENT_PURCHASES_LIMIT)
                                    ->get()
                                    ->toArray(),
            'recent_payments'  => CardPayment::whereHas(
                                    'cut.creditCard',
                                    fn($q) => $q->where('user_id', $user->id)
                                  )
                                    ->orderByDesc('payment_date')
                                    ->limit(self::RECENT_PAYMENTS_LIMIT)
                                    ->get()
                                    ->toArray(),
            'cards'            => CreditCard::where('user_id', $user->id)
                                    ->get()
                                    ->toArray(),
        ]);
    }

    private function buildSystemPrompt(string $userName, array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now         = now()->timezone('America/Bogota')->translatedFormat('l, j \d\e F \d\e Y, g:i a');

        return <<<PROMPT
Eres FinTrack AI, el asistente personal de finanzas de {$userName}.
Hoy es {$now} (Hora de Colombia).

━━━━━━━━━━━ DATOS DEL USUARIO ━━━━━━━━━━━
{$contextJson}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PERSONALIDAD Y TONO
- Responde SIEMPRE en Español (Colombia), tono amable, profesional y cercano.
- Usa emojis con moderación para hacer las respuestas más visuales.
- No uses disclaimers robóticos ni frases como "soy una IA".
- Si el usuario saluda, sé cordial. Si está frustrado, muéstrate empático.

INTELIGENCIA FINANCIERA
- Usa los datos del usuario para dar consejos ESPECÍFICOS (no genéricos).
- Detecta patrones: si gasta mucho en una categoría, mencíonalo proactivamente.
- Si consultan deuda, desglosa: capital, intereses, cuotas pendientes.
- Para consultas de saldo/cupo, calcula disponible = límite − saldo actual.
- Maneja correctamente las fechas en formato colombiano (dd/MM/yyyy).

FLUJO DE 2 PASOS (OBLIGATORIO para crear/editar/borrar)
1. Llama a `prepare_*` → muestra vista previa → pide confirmación.
2. Solo si el usuario confirma, llama a `create/edit/delete_*`.
3. Si rechaza, NO hagas nada. El sistema ya limpiará la sesión.

VALIDACIONES ANTES DE LLAMAR `prepare_*`
- Nunca inventes nombres, montos, IDs ni datos faltantes.
- Si falta información esencial, pregunta amablemente ANTES de llamar la función.
- Usa los IDs exactos del contexto para tarjetas y categorías.
- Si el usuario menciona una tarjeta o categoría por nombre, busca el ID en el contexto.

REGLAS DE DISPLAY
- Nunca muestres nombres de iconos Lucide (PascalCase) ni códigos hex al usuario.
- Usa emojis representativos en su lugar.
- Formatea los montos en pesos colombianos: $1.500.000
- Las fechas siempre en formato legible: "15 de enero de 2025"

ALCANCE
- Eres EXCLUSIVAMENTE un asistente de finanzas personales para FinTrack.
- SÍ puedes responder preguntas educativas de finanzas (interés EA, amortización, etc.)
- NO actúes como psicólogo, médico, abogado, chef, ni asistente general.
- Si el tema no es financiero, declina cordialmente y redirige.

ERRORES Y CASOS ESPECIALES
- Si no encuentras un dato en el contexto, dilo claramente en lugar de inventar.
- Si el usuario da un ID incorrecto, díselo y pide que verifique.
- Si hay ambigüedad (ej: dos tarjetas con nombre similar), pregunta cuál.
PROMPT;
    }

    // =========================================================================
    // TOOLS DEFINITION
    // =========================================================================

    private function getToolsDefinition(): array
    {
        return [
            // ── PURCHASE ──────────────────────────────────────────────────────
            $this->tool('prepare_purchase', 'Paso 1: Prepara y previsualiza un nuevo gasto antes de guardarlo.', [
                'name'             => ['type' => 'string', 'description' => 'Descripción del gasto'],
                'total_amount'     => ['type' => 'number', 'description' => 'Monto total en pesos'],
                'credit_card_id'   => ['type' => 'integer', 'description' => 'ID de la tarjeta de crédito'],
                'category_id'      => ['type' => 'integer', 'description' => 'ID de la categoría'],
                'installments_count' => ['type' => 'integer', 'description' => 'Número de cuotas (1 = contado)'],
                'purchase_date'    => ['type' => 'string', 'description' => 'Fecha ISO 8601 (YYYY-MM-DD)'],
                'responsibles'     => [
                    'type'  => 'array',
                    'items' => ['type' => 'object', 'properties' => [
                        'responsible_id' => ['type' => 'integer'],
                        'percentage'     => ['type' => 'number'],
                        'amount'         => ['type' => 'number'],
                    ]],
                ],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'low']],
            ], ['name', 'total_amount', 'credit_card_id', 'category_id', 'confidence']),

            $this->tool('create_purchase', 'Paso 2: Guarda el gasto previamente preparado.', []),

            // ── EDIT / DELETE PURCHASE ────────────────────────────────────────
            $this->tool('prepare_edit_purchase', 'Prepara la edición de un gasto existente.', [
                'purchase_id'  => ['type' => 'integer'],
                'name'         => ['type' => 'string'],
                'total_amount' => ['type' => 'number'],
                'category_id'  => ['type' => 'integer'],
                'purchase_date' => ['type' => 'string'],
            ], ['purchase_id']),

            $this->tool('edit_purchase', 'Confirma y guarda los cambios del gasto.', []),

            $this->tool('prepare_delete_purchase', 'Solicita confirmación para eliminar un gasto.', [
                'purchase_id' => ['type' => 'integer'],
            ], ['purchase_id']),

            $this->tool('delete_purchase', 'Elimina el gasto confirmado.', []),

            // ── CATEGORY ──────────────────────────────────────────────────────
            $this->tool('prepare_category', 'Prepara la creación de una nueva categoría.', [
                'name'  => ['type' => 'string'],
                'icon'  => ['type' => 'string', 'description' => 'Nombre Lucide en PascalCase (ej: ShoppingBag)'],
                'color' => ['type' => 'string', 'description' => 'Color hexadecimal'],
            ], ['name', 'icon', 'color']),

            $this->tool('create_category', 'Guarda la nueva categoría.', []),

            $this->tool('prepare_edit_category', 'Prepara la edición de una categoría.', [
                'category_id' => ['type' => 'integer'],
                'name'        => ['type' => 'string'],
                'icon'        => ['type' => 'string'],
                'color'       => ['type' => 'string'],
            ], ['category_id']),

            $this->tool('edit_category', 'Guarda los cambios de la categoría.', []),

            $this->tool('prepare_delete_category', 'Solicita confirmación para eliminar una categoría.', [
                'category_id' => ['type' => 'integer'],
            ], ['category_id']),

            $this->tool('delete_category', 'Elimina la categoría confirmada.', []),

            // ── RESPONSIBLE ───────────────────────────────────────────────────
            $this->tool('prepare_responsible', 'Prepara el registro de un nuevo responsable.', [
                'name'  => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ], ['name']),

            $this->tool('create_responsible', 'Guarda el nuevo responsable.', []),

            $this->tool('prepare_edit_responsible', 'Prepara la edición de un responsable.', [
                'responsible_id' => ['type' => 'integer'],
                'name'           => ['type' => 'string'],
                'email'          => ['type' => 'string'],
            ], ['responsible_id']),

            $this->tool('edit_responsible', 'Guarda los cambios del responsable.', []),

            $this->tool('prepare_delete_responsible', 'Solicita confirmación para eliminar un responsable.', [
                'responsible_id' => ['type' => 'integer'],
            ], ['responsible_id']),

            $this->tool('delete_responsible', 'Elimina el responsable confirmado.', []),

            // ── CARD ──────────────────────────────────────────────────────────
            $this->tool('prepare_card', 'Prepara la creación de una nueva tarjeta de crédito.', [
                'name'          => ['type' => 'string'],
                'franchise'     => ['type' => 'string', 'enum' => ['Visa', 'Mastercard', 'American Express', 'Diners Club', 'Otro']],
                'last_4_digits' => ['type' => 'string'],
                'color'         => ['type' => 'string', 'description' => 'Hexadecimal'],
                'credit_limit'  => ['type' => 'number'],
                'interest_rate' => ['type' => 'number', 'description' => 'Tasa EA en porcentaje'],
                'statement_day' => ['type' => 'integer', 'description' => 'Día de corte (1-31)'],
                'payment_day'   => ['type' => 'integer', 'description' => 'Día de pago (1-31)'],
            ], ['name', 'franchise', 'credit_limit', 'statement_day', 'payment_day']),

            $this->tool('create_card', 'Guarda la nueva tarjeta.', []),

            $this->tool('prepare_edit_card', 'Prepara la edición de una tarjeta.', [
                'card_id'       => ['type' => 'integer'],
                'name'          => ['type' => 'string'],
                'credit_limit'  => ['type' => 'number'],
                'interest_rate' => ['type' => 'number'],
                'statement_day' => ['type' => 'integer'],
                'payment_day'   => ['type' => 'integer'],
            ], ['card_id']),

            $this->tool('edit_card', 'Guarda los cambios de la tarjeta.', []),

            $this->tool('prepare_delete_card', 'Solicita confirmación para eliminar una tarjeta.', [
                'card_id' => ['type' => 'integer'],
            ], ['card_id']),

            $this->tool('delete_card', 'Elimina la tarjeta y todos sus gastos.', []),

            // ── PAYMENT ───────────────────────────────────────────────────────
            $this->tool('prepare_payment', 'Prepara el registro de un pago a una tarjeta.', [
                'cut_id'    => ['type' => 'integer', 'description' => 'ID del corte (opcional si se especifica card_name)'],
                'card_name' => ['type' => 'string', 'description' => 'Nombre parcial de la tarjeta'],
                'amount'    => ['type' => 'number', 'description' => 'Monto a pagar (0 = pago total del corte)'],
            ], []),

            $this->tool('create_payment', 'Registra el pago confirmado.', []),
        ];
    }

    /**
     * Builder de definición de herramienta (reduce boilerplate).
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        $schema = ['type' => 'object'];

        if (!empty($properties)) {
            $schema['properties'] = $properties;
        } else {
            $schema['properties'] = (object) [];
        }

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return [
            'name'        => $name,
            'description' => $description,
            'parameters'  => $schema,
        ];
    }

    // =========================================================================
    // RESOLVERS Y HELPERS
    // =========================================================================

    private function resolveCategory(array $args, array $context): array
    {
        $catId = (int) ($args['category_id'] ?? 0);
        $cats  = collect($context['categories'] ?? []);

        $match = $cats->firstWhere('id', $catId);
        if ($match) {
            $args['category_id']   = $match['id'];
            $args['category_name'] = $match['name'];
            return $args;
        }

        // Fallback a primera categoría disponible
        $fallback = $cats->first();
        $args['category_id']   = $fallback['id'] ?? null;
        $args['category_name'] = $fallback['name'] ?? 'Sin categoría';
        $args['confidence']    = 'low';
        return $args;
    }

    private function resolveCard(array $args, array $context): array
    {
        $cardId = (int) ($args['credit_card_id'] ?? 0);
        $cards  = collect($context['cards'] ?? []);

        $match = $cards->firstWhere('id', $cardId);
        if ($match) {
            $args['credit_card_id']   = $match['id'];
            $args['credit_card_name'] = $match['name'];
            return $args;
        }

        $fallback = $cards->first();
        $args['credit_card_id']   = $fallback['id'] ?? null;
        $args['credit_card_name'] = $fallback['name'] ?? 'Tarjeta';
        $args['confidence']       = 'low';
        return $args;
    }

    private function validateResponsibles(array $responsibles, array $context): array
    {
        $validIds = collect($context['responsibles'] ?? [])->pluck('id')->toArray();
        return collect($responsibles)
            ->filter(fn($r) => in_array($r['responsible_id'] ?? 0, $validIds, true))
            ->values()
            ->toArray();
    }

    private function resolveDate(?string $rawDate): string
    {
        if (!$rawDate) return now()->toDateString();
        try {
            return Carbon::parse($rawDate)->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function buildPurchasePreview(array $args, float $amount, bool $isWhatsApp): string
    {
        $dateLabel  = Carbon::parse($args['purchase_date'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $amountFmt  = $this->formatMoney($amount);
        $cuotas     = ($args['installments_count'] ?? 1) > 1
                        ? "\n⚡ *Cuotas:* {$args['installments_count']}"
                        : '';
        $respText   = !empty($args['responsibles'])
                        ? "\n👥 *Responsables:* " . count($args['responsibles'])
                        : '';
        $confidence = ($args['confidence'] ?? 'high') === 'low'
                        ? "\n⚠️ _Categoría/tarjeta asignada automáticamente. Verifica._"
                        : '';

        if ($isWhatsApp) {
            return "📋 *Vista previa del gasto:*\n"
                 . "🛒 *{$args['name']}*\n"
                 . "💰 {$amountFmt}\n"
                 . "💳 {$args['credit_card_name']}\n"
                 . "🏷️ {$args['category_name']}\n"
                 . "📅 {$dateLabel}"
                 . $cuotas
                 . $respText
                 . $confidence
                 . "\n\n¿Confirmas?";
        }

        return "📋 **Vista previa del gasto:**\n"
             . "- Descripción: **{$args['name']}**\n"
             . "- Valor: **{$amountFmt}**\n"
             . "- Tarjeta: {$args['credit_card_name']}\n"
             . "- Categoría: {$args['category_name']}\n"
             . "- Fecha: {$dateLabel}"
             . ($cuotas ? "\n- Cuotas: {$args['installments_count']}" : '')
             . $confidence
             . "\n\n¿Confirmas el registro?";
    }

    // =========================================================================
    // PAYLOAD & CONTENTS
    // =========================================================================

    private function buildContents(array $history, string $message, ?array $image): array
    {
        $contents = $this->reconstructHistory($history);

        $parts = [['text' => $message]];

        if ($image && !empty($image['data']) && !empty($image['mime_type'])) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image['mime_type'],
                    'data'      => $image['data'],
                ],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => $parts];

        return $contents;
    }

    private function buildPayload(array $contents, string $systemPrompt, ?array $toolConfig): array
    {
        $payload = [
            'contents'           => $contents,
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'tools'              => [['function_declarations' => $this->getToolsDefinition()]],
            'generationConfig'   => [
                'temperature'     => self::TEMPERATURE,
                'maxOutputTokens' => self::MAX_TOKENS,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',        'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',  'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',  'threshold' => 'BLOCK_NONE'],
            ],
        ];

        if ($toolConfig) {
            $payload['tool_config'] = $toolConfig;
        }

        return $payload;
    }

    private function reconstructHistory(array $history): array
    {
        $contents = [];

        foreach ($history as $msg) {
            $content = trim($msg['content'] ?? '');
            if (!$content) continue;

            // Excluir mensajes de confirmación/acción para no confundir al modelo
            if (str_contains($content, '✅') && strlen($content) < 80) continue;

            $role       = ($msg['role'] ?? 'bot') === 'bot' ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
        }

        return $contents;
    }

    // =========================================================================
    // TOOL CONFIG (forzar función en confirmaciones)
    // =========================================================================

    private function resolveToolConfig(User $user, string $message): ?array
    {
        if (!$this->isConfirmation($message)) return null;

        // Mapa: clave de caché → función execute a forzar
        $forceMap = [
            'purchase'           => 'create_purchase',
            'category'           => 'create_category',
            'responsible'        => 'create_responsible',
            'payment'            => 'create_payment',
            'card'               => 'create_card',
            'edit_purchase'      => 'edit_purchase',
            'delete_purchase'    => 'delete_purchase',
            'edit_category'      => 'edit_category',
            'delete_category'    => 'delete_category',
            'edit_responsible'   => 'edit_responsible',
            'delete_responsible' => 'delete_responsible',
            'edit_card'          => 'edit_card',
            'delete_card'        => 'delete_card',
        ];

        foreach ($forceMap as $entity => $function) {
            if (Cache::has($this->cacheKeyFor($entity, $user))) {
                return [
                    'function_calling_config' => [
                        'mode'                  => 'ANY',
                        'allowed_function_names' => [$function],
                    ],
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // CACHÉ
    // =========================================================================

    private function cacheKeyFor(string $entity, User $user): string
    {
        $pattern = self::FUNCTION_CACHE_MAP[$entity] ?? "fintrack_pending_{$entity}_%d";
        return sprintf($pattern, $user->id);
    }

    private function putCache(string $entity, User $user, array $data): void
    {
        Cache::put($this->cacheKeyFor($entity, $user), $data, now()->addMinutes(self::CACHE_TTL));
    }

    private function getCache(string $entity, User $user): ?array
    {
        return Cache::get($this->cacheKeyFor($entity, $user));
    }

    private function forgetCache(string $entity, User $user): void
    {
        Cache::forget($this->cacheKeyFor($entity, $user));
    }

    private function clearAllPending(User $user): void
    {
        foreach (array_keys(self::FUNCTION_CACHE_MAP) as $entity) {
            $this->forgetCache($entity, $user);
        }
    }

    // =========================================================================
    // INTENCIÓN
    // =========================================================================

    private function isRejection(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if (str_word_count($lower) > 4) return false;

        foreach (self::REJECT_TRIGGERS as $trigger) {
            if (str_contains($lower, $trigger)) return true;
        }
        return false;
    }

    private function isConfirmation(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if (str_word_count($lower) > 4) return false;

        foreach (self::CONFIRM_TRIGGERS as $trigger) {
            if (str_contains($lower, $trigger)) return true;
        }
        return false;
    }

    private function isOffTopic(string $message): bool
    {
        $lower = mb_strtolower($message);

        foreach (self::OFF_TOPIC_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) return true;
        }

        $words = preg_split('/\s+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > 8) {
            foreach (self::ALLOWED_TOPICS as $kw) {
                if (str_contains($lower, $kw)) return false;
            }
            return true;
        }

        return false;
    }

    // =========================================================================
    // FORMATO Y HELPERS
    // =========================================================================

    private function fmt(string $text, bool $isWhatsApp): string
    {
        return $isWhatsApp ? $this->formatForWhatsApp($text) : $text;
    }

    private function formatForWhatsApp(string $text): string
    {
        // **negrita** → *negrita* (WA)
        $text = preg_replace('/\*\*(.*?)\*\*/s', '*$1*', $text);
        // Eliminar HTML residual
        return strip_tags($text);
    }

    private function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    private function offTopicResponse(bool $isWhatsApp): string
    {
        $msg = "Soy *FinTrack AI*, especializado en ayudarte con tus finanzas personales 💳\n\n"
             . "Puedo ayudarte con:\n"
             . "• Registrar gastos y pagos\n"
             . "• Consultar deudas y saldos\n"
             . "• Gestionar tarjetas y categorías\n"
             . "• Analizar tus hábitos de gasto\n\n"
             . "¿En qué te puedo ayudar?";

        return $this->fmt($msg, $isWhatsApp);
    }

    private function errorResponse(string $message, bool $isWhatsApp): string
    {
        return $this->fmt("❌ {$message}", $isWhatsApp);
    }

    /**
     * Deduce la clave de ID correcta según la entidad (purchase_id, category_id, etc.)
     */
    private function entityIdKey(string $entity): string
    {
        return match (true) {
            str_contains($entity, 'purchase')    => 'purchase_id',
            str_contains($entity, 'category')    => 'category_id',
            str_contains($entity, 'responsible') => 'responsible_id',
            str_contains($entity, 'card')        => 'card_id',
            default                              => 'id',
        };
    }
}