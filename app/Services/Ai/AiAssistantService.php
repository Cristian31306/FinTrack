<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\PurchaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAssistantService
{
    protected string $baseUrl    = 'https://api.groq.com/openai/v1/chat/completions';
    protected string $textModel  = 'llama-3.3-70b-versatile';
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    // Palabras que indican confirmación explícita del usuario
    private const CONFIRM_TRIGGERS = [
        'si', 'sí', 'dale', 'ok', 'okay', 'confirmado', 'confirmo',
        'registrar', 'procede', 'págalo', 'hágale', 'adelante',
        'listo', 'perfecto', 'correcto', 'exacto', 'guardar',
    ];

    // Tiempo de vida del registro pendiente en caché (minutos)
    private const CACHE_TTL_MINUTES = 10;

    public function __construct(
        protected PurchaseService    $purchaseService,
        protected DebtSummaryService $summaryService,
    ) {}

    // =========================================================================
    // PUNTO DE ENTRADA PRINCIPAL
    // =========================================================================

    public function chat(User $user, string $message, array $history = [], ?array $image = null): string
    {
        $context      = $this->buildUserContext($user);
        $systemPrompt = $this->buildSystemPrompt($user->name, $context);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $messages = array_merge($messages, $this->reconstructHistory($history));

        if ($image) {
            $messages[] = ['role' => 'user', 'content' => $message];
            return $this->chatWithVision($messages, $message, $image);
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $this->callGroq($messages, $user, $context, $message);
    }

    // =========================================================================
    // LLAMADA AL LLM
    // =========================================================================

    private function callGroq(array $messages, User $user, array $context, string $rawMessage): string
    {
        $isConfirm = $this->isConfirmationMessage($rawMessage);

        $payload = [
            'model'       => $this->textModel,
            'messages'    => $messages,
            'tools'       => $this->getToolsDefinition(),
            'tool_choice' => $isConfirm
                ? ['type' => 'function', 'function' => ['name' => 'create_purchase']]
                : 'auto',
            'temperature' => 0.3,   // Más bajo = más determinista para function calling
            'max_tokens'  => 1024,
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . config('services.groq.key')])
                ->post($this->baseUrl, $payload);

            if ($response->failed()) {
                Log::error('[FinTrack AI] Groq HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return "Lo siento, tuve un problema de comunicación (código {$response->status()}). Intenta de nuevo.";
            }

            $choice = $response->json('choices.0.message');

            if (empty($choice)) {
                return "No obtuve una respuesta válida. Por favor intenta de nuevo.";
            }

            // ── Function calling ──────────────────────────────────────────────
            if (!empty($choice['tool_calls'])) {
                $call     = $choice['tool_calls'][0];
                $funcName = $call['function']['name'];
                $args     = json_decode($call['function']['arguments'], true) ?? [];

                return match ($funcName) {
                    'prepare_purchase' => $this->handlePrepare($args, $user, $context),
                    'create_purchase'  => $this->handleExecute($user),
                    default            => "Función desconocida: {$funcName}.",
                };
            }

            // ── Respuesta de texto normal ─────────────────────────────────────
            return Str::markdown($choice['content'] ?? "Entendido, ¿en qué más te puedo ayudar?");

        } catch (\Exception $e) {
            Log::error('[FinTrack AI] Excepción en callGroq', ['error' => $e->getMessage()]);
            return "Ocurrió un error interno. Por favor intenta de nuevo.";
        }
    }

    // =========================================================================
    // PASO 1 — VISTA PREVIA (no persiste en BD)
    // =========================================================================

    private function handlePrepare(array $args, User $user, array $context): string
    {
        // ── Validaciones básicas ──────────────────────────────────────────────
        $name   = trim($args['name'] ?? '');
        $amount = (float) ($args['total_amount'] ?? 0);

        if (strlen($name) < 2) {
            return "Necesito una descripción más clara del gasto. ¿Cómo lo llamo?";
        }
        if ($amount <= 0) {
            return "Necesito el monto exacto del gasto para continuar.";
        }

        // ── Resolución semántica de categoría ────────────────────────────────
        $args = $this->resolveCategory($args, $context);

        // ── Resolución de tarjeta ─────────────────────────────────────────────
        $args = $this->resolveCard($args, $context);

        // ── Resolución de fecha ───────────────────────────────────────────────
        $args['purchase_date'] = $this->resolveDate($args['purchase_date'] ?? null);

        // ── Guardar en caché (sin tocar la BD) ───────────────────────────────
        Cache::put(
            $this->cacheKey($user),
            $args,
            now()->addMinutes(self::CACHE_TTL_MINUTES)
        );

        // ── Construir vista previa ────────────────────────────────────────────
        return $this->buildPreviewMarkdown($args, $amount);
    }

    // =========================================================================
    // PASO 2 — REGISTRO DEFINITIVO (lee desde caché, escribe en BD)
    // =========================================================================

    private function handleExecute(User $user): string
    {
        $pending = Cache::get($this->cacheKey($user));

        if (!$pending) {
            return "❌ La sesión de confirmación expiró (" . self::CACHE_TTL_MINUTES . " min). ¿Me cuentas de nuevo qué quieres registrar?";
        }

        try {
            $purchase = $this->purchaseService->create([
                'name'               => $pending['name'],
                'total_amount'       => $pending['total_amount'],
                'purchase_date'      => $pending['purchase_date'],
                'credit_card_id'     => $pending['credit_card_id'],
                'category_id'        => $pending['category_id'],
                'installments_count' => $pending['installments_count'] ?? 1,
            ], $user->id);

            Cache::forget($this->cacheKey($user));

            $valor = '$' . number_format($purchase->total_amount, 0, ',', '.');
            return "✅ **¡Gasto registrado exitosamente!**\n\nGuardé **{$purchase->name}** por **{$valor}**. Tu dashboard ya está actualizado.";

        } catch (\Exception $e) {
            Log::error('[FinTrack AI] Error al guardar compra', ['error' => $e->getMessage()]);
            return "❌ Hubo un error al guardar en la base de datos. Por favor intenta de nuevo o contacta soporte.";
        }
    }

    // =========================================================================
    // RESOLUCIÓN SEMÁNTICA DE CATEGORÍA
    // El LLM ya eligió un ID basándose en los nombres reales del usuario.
    // Aquí solo validamos y aplicamos un fallback si el ID no existe.
    // =========================================================================

    private function resolveCategory(array $args, array $context): array
    {
        $categoryId     = (int) ($args['category_id'] ?? 0);
        $userCategories = collect($context['categories'] ?? []);

        $match = $userCategories->firstWhere('id', $categoryId);

        if ($match) {
            // El LLM acertó — usar tal cual
            $args['category_id']   = $match['id'];
            $args['category_name'] = $match['name'];
            return $args;
        }

        // El LLM devolvió un ID que no existe — loguear y usar la primera disponible
        Log::warning('[FinTrack AI] ID de categoría inválido', [
            'ai_category_id' => $categoryId,
            'available_ids'  => $userCategories->pluck('id')->toArray(),
        ]);

        $fallback = $userCategories->first();
        $args['category_id']   = $fallback['id']   ?? null;
        $args['category_name'] = $fallback['name'] ?? 'Sin categoría';
        // Marcamos baja confianza para mostrar advertencia al usuario
        $args['confidence']    = 'low';

        return $args;
    }

    // =========================================================================
    // RESOLUCIÓN DE TARJETA (validación igual que categoría)
    // =========================================================================

    private function resolveCard(array $args, array $context): array
    {
        $cardId    = (int) ($args['credit_card_id'] ?? 0);
        $userCards = collect($context['cards'] ?? []);

        $match = $userCards->firstWhere('id', $cardId);

        if ($match) {
            $args['credit_card_id']   = $match['id'];
            $args['credit_card_name'] = $args['credit_card_name'] ?? $match['name'];
            return $args;
        }

        Log::warning('[FinTrack AI] ID de tarjeta inválido', [
            'ai_card_id'    => $cardId,
            'available_ids' => $userCards->pluck('id')->toArray(),
        ]);

        $fallback = $userCards->first();
        $args['credit_card_id']   = $fallback['id']   ?? null;
        $args['credit_card_name'] = $fallback['name'] ?? 'Tarjeta principal';
        $args['confidence']       = 'low';

        return $args;
    }

    // =========================================================================
    // CONSTRUCCIÓN DE LA VISTA PREVIA EN MARKDOWN
    // =========================================================================

    private function buildPreviewMarkdown(array $args, float $amount): string
    {
        $dateLabel = Carbon::parse($args['purchase_date'])
            ->locale('es')
            ->translatedFormat('j \d\e F \d\e Y');

        $amountFmt    = '$' . number_format($amount, 0, ',', '.');
        $cardName     = $args['credit_card_name'] ?? 'Tarjeta seleccionada';
        $categoryName = $args['category_name']    ?? 'Sin categoría';
        $installments = $args['installments_count'] ?? 1;

        $table = "📋 **Vista previa del registro:**\n\n" .
            "| Campo | Valor |\n" .
            "|---|---|\n" .
            "| 🛒 Descripción | **{$args['name']}** |\n" .
            "| 💰 Valor | **{$amountFmt}** |\n" .
            "| 💳 Tarjeta | **{$cardName}** |\n" .
            "| 🏷️ Categoría | **{$categoryName}** |\n" .
            "| 📅 Fecha | **{$dateLabel}** |\n" .
            "| 🔢 Cuotas | **{$installments}** |\n\n";

        // Advertencia de baja confianza en categoría o tarjeta
        if (($args['confidence'] ?? 'high') === 'low') {
            $table .= "> ⚠️ *No estaba del todo seguro de la categoría o la tarjeta. Por favor revísalas antes de confirmar.*\n\n";
        }

        $table .= "¿Confirmas el registro? Responde **sí** o corrígeme lo que necesites.";

        return $table;
    }

    // =========================================================================
    // RECONSTRUCCIÓN DEL HISTORIAL (para multi-turno)
    // Convierte el historial del chat en el formato que espera la API.
    // Los mensajes de "Vista previa" se reconstruyen como tool_call + tool_result
    // para que el modelo entienda el estado de la conversación y no entre en bucles.
    // =========================================================================

    private function reconstructHistory(array $history): array
    {
        $messages = [];

        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            $role    = ($msg['role'] ?? 'bot') === 'bot' ? 'assistant' : 'user';

            // Ignorar confirmaciones ya procesadas (✅)
            if (str_contains($content, '✅')) {
                continue;
            }

            // Detectar mensajes de vista previa y reconstruirlos como function call
            // para que el modelo sepa que ya mostró la preview y no la repita
            if ($role === 'assistant' && str_contains($content, 'Vista previa del registro')) {
                $toolCallId = 'call_hist_' . uniqid();

                $messages[] = [
                    'role'    => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id'   => $toolCallId,
                        'type' => 'function',
                        'function' => [
                            'name'      => 'prepare_purchase',
                            'arguments' => json_encode(['_reconstructed' => true]),
                        ],
                    ]],
                ];

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content'      => $content,
                ];

                continue;
            }

            $messages[] = ['role' => $role, 'content' => $content];
        }

        return $messages;
    }

    // =========================================================================
    // DEFINICIÓN DE HERRAMIENTAS (Function Calling)
    // =========================================================================

    private function getToolsDefinition(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'prepare_purchase',
                    'description' => <<<DESC
                    PASO 1 — Genera una vista previa del gasto. 
                    Úsala la primera vez que el usuario mencione un gasto con datos suficientes (nombre + monto + tarjeta).
                    NO la uses si el usuario solo está confirmando algo que ya vio.
                    DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => [
                                'type'        => 'string',
                                'description' => 'Descripción corta del gasto. Ej: "Gasolina moto", "Almuerzo oficina", "Netflix".',
                            ],
                            'total_amount' => [
                                'type'        => 'number',
                                'description' => 'Monto total en pesos colombianos, sin puntos ni símbolos. Ej: 85000',
                            ],
                            'credit_card_id' => [
                                'type'        => 'integer',
                                'description' => 'ID exacto de la tarjeta del listado TARJETAS DISPONIBLES. Busca por nombre, franquicia o últimos 4 dígitos.',
                            ],
                            'credit_card_name' => [
                                'type'        => 'string',
                                'description' => 'Nombre amigable de la tarjeta para mostrar al usuario.',
                            ],
                            'category_id' => [
                                'type'        => 'integer',
                                'description' => 'ID de la categoría del listado CATEGORÍAS DISPONIBLES. Elige semánticamente la que más se acerque al gasto.',
                            ],
                            'category_name' => [
                                'type'        => 'string',
                                'description' => 'Nombre de la categoría elegida (para mostrar al usuario).',
                            ],
                            'installments_count' => [
                                'type'        => 'integer',
                                'description' => 'Número de cuotas. Si no se menciona, usar 1.',
                                'default'     => 1,
                            ],
                            'purchase_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha del gasto en formato YYYY-MM-DD. Usa el año actual si no se especifica. Hoy es: ' . now()->toDateString(),
                            ],
                            'confidence' => [
                                'type'        => 'string',
                                'enum'        => ['high', 'low'],
                                'description' => 'high = estás seguro de la categoría y la tarjeta. low = no hay coincidencia clara y el usuario debería verificar.',
                            ],
                        ],
                        'required' => ['name', 'total_amount', 'credit_card_id', 'category_id', 'confidence'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_purchase',
                    'description' => <<<DESC
                    PASO 2 — Guarda el gasto definitivamente en la base de datos.
                    SOLO llámala cuando el usuario haya visto la vista previa Y confirme explícitamente (sí, dale, ok, confirmo, etc.).
                    NUNCA la llames sin que exista una vista previa previa.
                    DESC,
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => (object)[],
                    ],
                ],
            ],
        ];
    }

    // =========================================================================
    // CONSTRUCCIÓN DEL CONTEXTO DEL USUARIO
    // =========================================================================

    private function buildUserContext(User $user): array
    {
        $dashboard = $this->summaryService->dashboard($user);

        $dashboard['categories'] = Category::where('user_id', $user->id)
            ->get(['id', 'name', 'icon'])
            ->toArray();

        $dashboard['cards'] = CreditCard::where('user_id', $user->id)
            ->get(['id', 'name', 'franchise', 'last_4_digits'])
            ->toArray();

        return $dashboard;
    }

    // =========================================================================
    // CONSTRUCCIÓN DEL SYSTEM PROMPT
    // Toda la "inteligencia de negocio" vive aquí.
    // =========================================================================

    private function buildSystemPrompt(string $userName, array $context): string
    {
        $categories = $context['categories'] ?? [];
        $cards      = $context['cards']      ?? [];

        // Excluir del resumen JSON las listas que ya se formatean abajo
        $summary = $context;
        unset($summary['categories'], $summary['cards']);

        $catList = collect($categories)
            ->map(fn($c) => "  - ID {$c['id']}: \"{$c['name']}\"")
            ->implode("\n");

        $cardList = collect($cards)
            ->map(fn($c) => "  - ID {$c['id']}: \"{$c['name']}\" (Franquicia: {$c['franchise']}, Últimos 4 dígitos: {$c['last_4_digits']})")
            ->implode("\n");

        $financialSummary = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $today            = now()->toDateString();

        return <<<PROMPT
        Eres FinTrack AI, el asistente financiero personal de {$userName}.
        Responde siempre en Español de Colombia. Tono profesional pero cercano.
        HOY ES: {$today}.

        ══════════════════════════════════════════
        RESUMEN FINANCIERO DEL USUARIO
        ══════════════════════════════════════════
        {$financialSummary}

        ══════════════════════════════════════════
        TARJETAS DISPONIBLES
        ══════════════════════════════════════════
        {$cardList}

        Reglas para identificar tarjetas:
        - Busca por NOMBRE ("Nu", "Davivienda"), FRANQUICIA ("Visa", "Mastercard") o ÚLTIMOS 4 DÍGITOS.
        - Si el usuario dice "la Nu" o "mi tarjeta Visa" busca la coincidencia más cercana.
        - Si no hay ninguna tarjeta que encaje, usa confidence=low y elige la primera de la lista.

        ══════════════════════════════════════════
        CATEGORÍAS DISPONIBLES
        ══════════════════════════════════════════
        {$catList}

        Reglas de categorización (semántica, no hardcodeada):
        - Analiza el SIGNIFICADO del gasto y compáralo con los NOMBRES REALES de las categorías de arriba.
        - Elige el ID de la categoría cuyo nombre sea semánticamente más cercano al gasto descrito.
        - Si la coincidencia es clara (gasolina → hay una categoría "Transporte"), usa confidence=high.
        - Si no hay categoría obvia o varias sirven igual, usa la más genérica y confidence=low.
        - NUNCA inventes IDs. Usa solo los de la lista de arriba.

        ══════════════════════════════════════════
        FLUJO DE REGISTRO (síguelo sin excepción)
        ══════════════════════════════════════════
        1. PASO 1 — Vista previa:
           Cuando el usuario mencione un gasto con datos suficientes (qué + cuánto + con qué tarjeta),
           llama a `prepare_purchase`. Esto solo muestra una tabla, NO guarda nada.

        2. PASO 2 — Confirmación:
           Si ya mostraste la tabla de vista previa en este chat Y el usuario confirma
           (dice "sí", "dale", "ok", "confirmo", etc.), llama a `create_purchase` de inmediato.
           ⛔ NO vuelvas a llamar a `prepare_purchase` si el usuario solo está confirmando.
           ⛔ NO pidas más información si ya tienes la vista previa guardada.

        3. CORRECCIONES:
           Si el usuario dice "no" o quiere cambiar algo (categoría, monto, tarjeta),
           pídele el dato corregido y luego llama de nuevo a `prepare_purchase` con los datos actualizados.

        ══════════════════════════════════════════
        OTRAS CAPACIDADES
        ══════════════════════════════════════════
        - Puedes responder preguntas sobre el resumen financiero del usuario (deudas, gastos, tarjetas).
        - Puedes dar consejos financieros basados en sus datos reales.
        - Si el usuario sube una foto de un recibo o factura, extrae los datos y prepara la vista previa.
        PROMPT;
    }

    // =========================================================================
    // VISIÓN (análisis de imágenes / recibos)
    // =========================================================================

    private function chatWithVision(array $messages, string $message, array $image): string
    {
        // Reemplazar el último mensaje de texto por uno multimodal
        array_pop($messages);

        $messages[] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'text',      'text'      => $message],
                ['type' => 'image_url', 'image_url' => [
                    'url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data'],
                ]],
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . config('services.groq.key')])
                ->post($this->baseUrl, [
                    'model'       => $this->visionModel,
                    'messages'    => $messages,
                    'temperature' => 0.4,
                    'max_tokens'  => 1024,
                ]);

            if ($response->failed()) {
                Log::error('[FinTrack AI] Vision error', ['status' => $response->status()]);
                return "No pude procesar la imagen. ¿Puedes contarme el gasto manualmente?";
            }

            $content = $response->json('choices.0.message.content') ?? '';
            return Str::markdown($content ?: "No pude extraer información de la imagen.");

        } catch (\Exception $e) {
            Log::error('[FinTrack AI] Excepción en chatWithVision', ['error' => $e->getMessage()]);
            return "Error al analizar la imagen. Por favor intenta de nuevo.";
        }
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function isConfirmationMessage(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        // Mensaje muy corto = probablemente una confirmación directa
        if (str_word_count($lower) <= 3) {
            foreach (self::CONFIRM_TRIGGERS as $trigger) {
                if (str_contains($lower, $trigger)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveDate(?string $rawDate): string
    {
        if (!$rawDate) {
            return now()->toDateString();
        }

        try {
            $date        = Carbon::parse($rawDate);
            $currentYear = (int) now()->format('Y');

            // Si el año está desfasado más de 1 año, forzamos el año actual
            if (abs($date->year - $currentYear) > 1) {
                $date->setYear($currentYear);
                // Si aún así queda en el futuro, retrocedemos un año
                if ($date->isFuture()) {
                    $date->subYear();
                }
            }

            return $date->toDateString();
        } catch (\Exception) {
            return now()->toDateString();
        }
    }

    private function cacheKey(User $user): string
    {
        return "fintrack_pending_purchase_{$user->id}";
    }
}