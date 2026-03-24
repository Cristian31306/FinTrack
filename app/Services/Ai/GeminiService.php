<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\PurchaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    protected string $textModel   = 'llama-3.3-70b-versatile';
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    public function __construct(
        protected DebtSummaryService $summaryService,
        protected PurchaseService $purchaseService
    ) {
        $this->apiKey = config('services.groq.key');
    }

    /**
     * Lógica principal del Chat AI (Groq/Llama)
     */
    public function chat(User $user, string $message, array $history = [], ?array $image = null): string
    {
        $context      = $this->getUserContext($user);
        $systemPrompt = $this->getSystemPrompt($user->name, $context);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // --- RECONSTRUCCIÓN INTELIGENTE DEL HISTORIAL ---
        foreach ($history as $msg) {
            $content = $msg['content'] ?? '';
            if (str_contains($content, '✅')) continue;

            $role = ($msg['role'] ?? 'bot') === 'bot' ? 'assistant' : 'user';

            // Si es un mensaje del bot que parece una vista previa, lo marcamos como tool_call
            // Esto ayuda al modelo a entender el estado de la conversación (evita bucles)
            if ($role === 'assistant' && str_contains($content, 'Vista previa del registro')) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_' . uniqid(),
                            'type' => 'function',
                            'function' => [
                                'name' => 'prepare_purchase',
                                'arguments' => json_encode(['mock' => true])
                            ]
                        ]
                    ]
                ];
                // Y añadimos la respuesta de la herramienta (el texto que el usuario ve)
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $messages[count($messages)-1]['tool_calls'][0]['id'],
                    'content' => $content
                ];
                continue;
            }

            $messages[] = ['role' => $role, 'content' => $content];
        }

        // Manejo de Visión
        if ($image) {
            return $this->chatWithVision($messages, $message, $image);
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        // Definición de Herramientas
        $tools = $this->getToolsDefinition();

        // Determinar si debemos forzar create_purchase (si la intención es clara)
        $confirms = ['si', 'sí', 'dale', 'ok', 'confirmado', 'registrar', 'procede'];
        $isConfirm = false;
        $lowerMsg = strtolower($message);
        foreach ($confirms as $c) {
            if (str_contains($lowerMsg, $c)) {
                $isConfirm = true;
                break;
            }
        }

        try {
            $payload = [
                'model'       => $this->textModel,
                'messages'    => $messages,
                'tools'       => $tools,
                'tool_choice' => $isConfirm ? ['type' => 'function', 'function' => ['name' => 'create_purchase']] : 'auto',
                'temperature' => 0.4,
                'max_tokens'  => 1024,
            ];

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->post($this->baseUrl, $payload);

            if ($response->failed()) {
                Log::error('Groq Error: ' . $response->body());
                return "Lo siento, tuve un problema (código {$response->status()})";
            }

            $data   = $response->json();
            $choice = $data['choices'][0]['message'] ?? null;

            if (!$choice) {
                return "No pude generar una respuesta clara.";
            }

            // --- PROCESAMIENTO DE HERRAMIENTAS (Function Calling) ---
            if (!empty($choice['tool_calls'])) {
                $toolCall = $choice['tool_calls'][0];
                $funcName = $toolCall['function']['name'];
                $args     = json_decode($toolCall['function']['arguments'], true);

                return match ($funcName) {
                    'prepare_purchase' => $this->handlePrepare($args, $user, $context),
                    'create_purchase'  => $this->handleExecute($user),
                    default            => "Función no reconocida por el sistema.",
                };
            }

            // Respuesta de texto normal
            return Str::markdown($choice['content'] ?? "No pude generar una respuesta fluida.");

        } catch (\Exception $e) {
            Log::error('ChatService Exception: ' . $e->getMessage());
            return "Algo salió mal internamente. Por favor intenta de nuevo.";
        }
    }

    /**
     * PASO 1: Generar vista previa y persistir en Cache
     */
    protected function handlePrepare(array $args, User $user, array $context): string
    {
        $amount = (float) ($args['total_amount'] ?? 0);
        $name   = trim($args['name'] ?? '');

        if ($amount <= 0 || strlen($name) < 2) {
            return "Para registrar el gasto necesito un nombre real y un monto superior a 0. ¿Me los confirmas?";
        }

        // --- VALIDACIÓN DE CATEGORÍA (Red de Seguridad) ---
        $categoryId     = (int) ($args['category_id'] ?? 0);
        $userCategories = collect($context['categories'] ?? []);
        $categoryMatch  = $userCategories->firstWhere('id', $categoryId);

        if (!$categoryMatch) {
            Log::warning("IA intentó usar ID de categoría inexistente: {$categoryId}. Buscando fallback.");
            $categoryMatch = $userCategories->first();
        }

        if ($categoryMatch) {
            $args['category_id']   = $categoryMatch['id'] ?? null;
            $args['category_name'] = $categoryMatch['name'] ?? 'General';
        } else {
            $args['category_id']   = null;
            $args['category_name'] = 'Sin categoría';
        }

        // --- RESOLVER FECHA ---
        $args['purchase_date'] = $this->resolveDate($args['purchase_date'] ?? null);

        // --- PERSISTENCIA EN CACHE (10 minutos) ---
        Cache::put("pending_purchase_{$user->id}", $args, now()->addMinutes(10));

        // --- RENDERIZADO DE TABLA ---
        $dateLabel = \Illuminate\Support\Carbon::parse($args['purchase_date'])->locale('es')->translatedFormat('j [de] F [de] Y');
        $amountFmt = '$' . number_format($amount, 0, ',', '.');
        $cardName  = $args['credit_card_name'] ?? 'Tarjeta seleccionada';

        $markdown = "📋 **Vista previa del registro:**\n\n" .
            "| Campo | Valor |\n" .
            "|---|---|\n" .
            "| 🛒 Descripción | **{$name}** |\n" .
            "| 💰 Valor | **{$amountFmt}** |\n" .
            "| 💳 Tarjeta | **{$cardName}** |\n" .
            "| 🏷️ Categoría | **{$args['category_name']}** |\n" .
            "| 📅 Fecha | **{$dateLabel}** |\n" .
            "| 🔢 Cuotas | **" . ($args['installments_count'] ?? 1) . "** |\n\n" .
            "¿Confirmas el registro? Haz clic abajo o responde **sí**.";

        return Str::markdown($markdown);
    }

    /**
     * PASO 2: Ejecutar desde Cache (Precisión 100%)
     */
    protected function handleExecute(User $user): string
    {
        $data = Cache::get("pending_purchase_{$user->id}");

        if (!$data) {
            return "Lo siento, la sesión de confirmación expiró o no encontré una compra pendiente. ¿Podemos empezar de nuevo?";
        }

        try {
            $this->purchaseService->create([
                'credit_card_id'     => $data['credit_card_id'],
                'category_id'        => $data['category_id'],
                'name'               => $data['name'],
                'total_amount'       => $data['total_amount'],
                'installments_count' => $data['installments_count'] ?? 1,
                'purchase_date'      => $data['purchase_date'],
            ], $user->id);

            // Limpiamos el cache tras el éxito
            Cache::forget("pending_purchase_{$user->id}");

            return "✅ **¡Compra registrada exitosamente!**\nHe añadido **{$data['name']}** por valor de **$" . number_format($data['total_amount'], 0, ',', '.') . "** a su tarjeta. Tu dashboard ya ha sido actualizado.";
        } catch (\Exception $e) {
            Log::error('Error finalizando compra desde Cache: ' . $e->getMessage());
            return "Hubo un problema al guardar definitivamente: " . $e->getMessage();
        }
    }

    /**
     * Resuelve y valida la fecha
     */
    protected function resolveDate(?string $rawDate): string
    {
        $currentYear = (int) now()->format('Y');
        if (!$rawDate) return now()->toDateString();

        try {
            $parsed = \Carbon\Carbon::parse($rawDate);
            if (abs((int)$parsed->format('Y') - $currentYear) > 1) {
                $parsed->setYear($currentYear);
                if ($parsed->isFuture()) $parsed->subYear();
            }
            return $parsed->toDateString();
        } catch (\Exception $e) {
            return now()->toDateString();
        }
    }

    protected function getToolsDefinition(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'prepare_purchase',
                    'description' => 'Genera una VISTA PREVIA (Paso 1). Úsala cuando el usuario mencione un gasto con nombre, monto y tarjeta. NO guarda en BD.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'total_amount' => ['type' => 'number'],
                            'credit_card_id' => ['type' => 'integer'],
                            'credit_card_name' => ['type' => 'string'],
                            'category_id' => ['type' => 'integer', 'description' => 'ID exacto de la lista de categorías.'],
                            'category_name' => ['type' => 'string'],
                            'installments_count' => ['type' => 'integer'],
                            'purchase_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        ],
                        'required' => ['name', 'total_amount', 'credit_card_id', 'category_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_purchase',
                    'description' => 'REGISTRO DEFINITIVO (Paso 2). Llama esta función SOLO cuando el usuario confirme el registro después de haber visto la vista previa.',
                    'parameters'  => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
        ];
    }

    protected function getUserContext(User $user): array
    {
        $dashboard = $this->summaryService->dashboard($user);
        $dashboard['categories'] = \App\Models\Category::where('user_id', $user->id)
            ->get(['id', 'name', 'icon'])
            ->toArray();
        $dashboard['cards'] = \App\Models\CreditCard::where('user_id', $user->id)
            ->get(['id', 'name', 'franchise', 'last_4_digits'])
            ->toArray();
        return $dashboard;
    }

    protected function getSystemPrompt(string $userName, array $context): string
    {
        $categories = $context['categories'] ?? [];
        $cards      = $context['cards'] ?? [];
        unset($context['categories'], $context['cards']);
        $summary = json_encode($context);

        $catList = '';
        foreach ($categories as $cat) {
            $catList .= "  - ID {$cat['id']}: \"{$cat['name']}\"\n";
        }

        $cardList = '';
        foreach ($cards as $card) {
            $cardList .= "  - ID {$card['id']}: \"{$card['name']}\" (Franquicia: {$card['franchise']}, Últimos 4: {$card['last_4_digits']})\n";
        }

        $today = now()->toDateString();

        return <<<PROMPT
Eres FinTrack AI, el asistente financiero de élite.
Responde en Español de Colombia, tono profesional.

HOY ES: {$today}.

══════════════════════════════════════════
ESTADO FINANCIERO:
══════════════════════════════════════════
{$summary}

══════════════════════════════════════════
TARJETAS DISPONIBLES:
══════════════════════════════════════════
{$cardList}

══════════════════════════════════════════
CATEGORÍAS DISPONIBLES:
══════════════════════════════════════════
{$catList}

🚨 REGLAS DE CATEGORIZACIÓN (MÁXIMA PRIORIDAD):
Analiza semánticamente el gasto antes de elegir la categoría:
- Gasto en Gasolina, Tanqueada, Moto, Terpel, Primax, Carro, Combustible -> USA Categoría "Transporte". NUNCA elijas "Tarjeta".
- Gasto en Comida, Almuerzo, Cena, Rappi, Mercado, D1, Carulla -> USA Categoría "Alimentación" o "Mercado".
- NUNCA uses la categoría llamada "Tarjeta" para gastos de combustible o comida. "Tarjeta" es solo si no hay nada más que encaje.
- Si el usuario menciona "Nu", se refiere a su tarjeta Nu de crédito que aparece en la lista de arriba con su ID.

══════════════════════════════════════════
FLUJO DE REGISTRO SEGURO (SIN BUCLES):
══════════════════════════════════════════
1. PASO 1 (Vista Previa): Siempre que recibas datos de un gasto, llama a `prepare_purchase`.
2. PASO 2 (Registro Real): Si ya mostraste la TABLA DE VISTA PREVIA arriba y el usuario confirma (ej. "sí", "dale", "ok", "Sí, registrar"), llama a `create_purchase` INMEDIATAMENTE.
   - ⛔ NO vuelvas a llamar a `prepare_purchase` si el usuario solo está confirmando lo que ya vio.
   - Si el usuario dice "No", pregunta qué corregir.

Tu misión es registrar con precisión y sin bucles para que {$userName} tenga el control total.
PROMPT;
    }

    protected function chatWithVision(array $messages, string $message, array $image): string
    {
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $message],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data']]],
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->post($this->baseUrl, [
                    'model'       => $this->visionModel,
                    'messages'    => $messages,
                    'temperature' => 0.7,
                    'max_tokens'  => 1024,
                ]);

            if ($response->failed()) return "Problema de visión temporal.";
            $data = $response->json();
            return Str::markdown($data['choices'][0]['message']['content'] ?? "No pude ver la imagen.");
        } catch (\Exception $e) {
            return "Error en visión.";
        }
    }
}
