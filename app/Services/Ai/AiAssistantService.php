<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\PurchaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiAssistantService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    // Modelos Groq (Llama 3.3 para texto, 3.2 para visión)
    protected string $textModel   = 'llama-3.3-70b-versatile';
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    public function __construct(
        protected PurchaseService $purchaseService,
        protected DebtSummaryService $summaryService
    ) {
        $this->apiKey = config('services.groq.key');
    }

    /**
     * Punto de entrada principal para el chat (Texto/Herramientas)
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

            // Si es un mensaje del bot que parece una vista previa (tabla markdown), lo marcamos como tool_call
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

        // Determinar si debemos forzar create_purchase (si la intención es clara de confirmar)
        $confirms = ['si', 'sí', 'dale', 'ok', 'confirmado', 'registrar', 'procede', 'págalo', 'hágale', 'adelante'];
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

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl, $payload);

            if ($response->failed()) {
                Log::error('Groq Error: ' . $response->body());
                return "Lo siento, tuve un problema (código {$response->status()})";
            }

            $data   = $response->json();
            $choice = $data['choices'][0]['message'] ?? null;

            if (!$choice) return "No obtuve respuesta del cerebro.";

            // --- MANEJO DE FUNCTION CALLING ---
            if (!empty($choice['tool_calls'])) {
                $call = $choice['tool_calls'][0];
                $funcName = $call['function']['name'];
                $args     = json_decode($call['function']['arguments'], true) ?? [];

                return match ($funcName) {
                    'prepare_purchase' => $this->handlePrepare($args, $user, $context),
                    'create_purchase'  => $this->handleExecute($user),
                    default            => "Función no reconocida.",
                };
            }

            return Str::markdown($choice['content'] ?? "Entendido.");

        } catch (\Exception $e) {
            Log::error('AiAssistantService Error: ' . $e->getMessage());
            return "Error crítico: " . $e->getMessage();
        }
    }

    /**
     * Paso 1: Generar vista previa y persistir en Cache
     */
    protected function handlePrepare(array $args, User $user, array $context): string
    {
        $name   = $args['name'] ?? 'Compra';
        $amount = (float) ($args['total_amount'] ?? 0);

        if ($amount <= 0) return "Necesito saber el monto exacto para registrar.";

        // --- VALIDACIÓN DE CATEGORÍA (Red de Seguridad) ---
        $categoryId     = (int) ($args['category_id'] ?? 0);
        $userCategories = collect($context['categories'] ?? []);
        $categoryMatch  = $userCategories->firstWhere('id', $categoryId);

        if (!$categoryMatch) {
            Log::warning("AI intentó usar ID de categoría inexistente: {$categoryId}. Buscando fallback.");
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
            "¿Confirmas el registro? Haz clic abajo o responde sí.";

        return $markdown;
    }

    /**
     * Paso 2: Registro definitivo desde Cache
     */
    protected function handleExecute(User $user): string
    {
        $pending = Cache::get("pending_purchase_{$user->id}");

        if (!$pending) {
            return "❌ Lo siento, la sesión de registro expiró (10 min). Por favor, dime de nuevo qué quieres registrar.";
        }

        try {
            $purchase = $this->purchaseService->createPurchase($user, [
                'name'               => $pending['name'],
                'total_amount'       => $pending['total_amount'],
                'purchase_date'      => $pending['purchase_date'],
                'credit_card_id'     => $pending['credit_card_id'],
                'category_id'        => $pending['category_id'],
                'installments_count' => $pending['installments_count'] ?? 1,
            ]);

            Cache::forget("pending_purchase_{$user->id}");

            $valor = number_format($purchase->total_amount, 0, ',', '.');
            return "✅ **¡Gasto registrado con éxito!**\n\nHe guardado **{$purchase->name}** por **\${$valor}** en tu historial. Tu dashboard ya está actualizado.";

        } catch (\Exception $e) {
            Log::error('Error al guardar compra desde AI: ' . $e->getMessage());
            return "❌ Hubo un error al guardar en la base de datos: " . $e->getMessage();
        }
    }

    protected function resolveDate(?string $providedDate): string
    {
        if (!$providedDate) return now()->toDateString();
        
        try {
            $date = \Illuminate\Support\Carbon::parse($providedDate);
            // Si la fecha es futura (ej March 2026), forzamos año actual si coincide el día/mes
            if ($date->year > now()->year) {
                $date->year = now()->year;
            }
            return $date->toDateString();
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
                    'description' => 'Genera una VISTA PREVIA (Paso 1). Úsala cuando el usuario mencione un gasto con nombre, monto y tarjeta.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name'               => ['type' => 'string', 'description' => 'Descripción corta del gasto (ej: Gasolina Moto, Almuerzo)'],
                            'total_amount'       => ['type' => 'number', 'description' => 'Monto total sin puntos ni comas'],
                            'credit_card_id'     => ['type' => 'integer', 'description' => 'ID de la tarjeta de crédito del listado proporcionado'],
                            'credit_card_name'   => ['type' => 'string', 'description' => 'Nombre de la tarjeta (ej: Nu, Visa, etc.)'],
                            'category_id'        => ['type' => 'integer', 'description' => 'ID de la categoría del listado proporcionado'],
                            'category_name'      => ['type' => 'string', 'description' => 'Nombre de la categoría elegida'],
                            'installments_count' => ['type' => 'integer', 'description' => 'Número de cuotas. Por defecto 1.'],
                            'purchase_date'      => ['type' => 'string', 'description' => 'Fecha en formato YYYY-MM-DD. Infere el año actual si no se especifica.'],
                        ],
                        'required' => ['name', 'total_amount', 'credit_card_id', 'category_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_purchase',
                    'description' => 'REGISTRO DEFINITIVO (Paso 2). LLÁMALA CUANDO EL USUARIO DIGA "SÍ", "CONFIRMO", O PARECIDO.',
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
TARJETAS DISPONIBLES:
══════════════════════════════════════════
{$cardList}

══════════════════════════════════════════
CATEGORÍAS DISPONIBLES:
══════════════════════════════════════════
{$catList}

🚨 REGLAS DE CATEGORIZACIÓN (PRIORIDAD ALTA):
1. Combustible (Gasolina, Tanqueada, Moto, Terpel, Primax) -> USA Categoría "Transporte". NUNCA "Tarjeta".
2. Comida (Almuerzo, Cena, Rappi, McDonald's) -> USA Categoría "Alimentación".
3. Busca siempre la categoría más específica. Evita categorías genéricas.

🚨 REGLAS DE TARJETAS (MÁXIMA PRIORIDAD):
Cuando el usuario mencione una tarjeta (ej: "Nu", "la de Davivienda", "la 4321"):
- Busca coincidencias en la lista "TARJETAS DISPONIBLES" usando el NOMBRE, la FRANQUICIA o los ÚLTIMOS 4 DÍGITOS.
- Si encuentras el ID, úsalo en `prepare_purchase`.

══════════════════════════════════════════
FLUJO DE REGISTRO SEGURO:
══════════════════════════════════════════
1. Paso 1 (Vista Previa): Muestra la tabla enviando datos a `prepare_purchase`.
2. Paso 2 (Crear): Si ya hay una tabla arriba y el usuario confirma, llama a `create_purchase`. NO vuelvas a pedir vista previa.

Tu misión es registrar con precisión para que {$userName} tenga el control total.
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
            $response = Http::withoutVerifying()->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])->post($this->baseUrl, [
                'model'       => $this->visionModel,
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 1024,
            ]);

            if ($response->failed()) return "Lo siento, no pude procesar la imagen.";
            $data = $response->json();
            return Str::markdown($data['choices'][0]['message']['content'] ?? "No pude ver la imagen.");
        } catch (\Exception $e) {
            return "Error en visión.";
        }
    }
}
