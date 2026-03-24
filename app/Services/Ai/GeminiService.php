<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\PurchaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    // Modelo con Function Calling (texto)
    protected string $textModel = 'llama-3.3-70b-versatile';

    // Modelo con visión (sin function calling)
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    public function __construct(
        protected DebtSummaryService $summaryService,
        protected PurchaseService $purchaseService
    ) {
        $this->apiKey = config('services.groq.key');
    }

    public function chat(User $user, string $message, array $history = [], ?array $image = null): string
    {
        $context = $this->getUserContext($user);
        $systemPrompt = $this->getSystemPrompt($user->name, $context);

        // Construir mensajes en formato OpenAI
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Añadir historial
        foreach ($history as $msg) {
            if (str_starts_with($msg['content'], '✅')) continue;
            $messages[] = [
                'role'    => $msg['role'] === 'bot' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        // Si hay imagen, usar modelo de visión (no soporta function calling)
        if ($image) {
            return $this->chatWithVision($messages, $message, $image);
        }

        // Mensaje del usuario
        $messages[] = ['role' => 'user', 'content' => $message];

        // Herramientas (Function Calling)
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_purchase',
                    'description' => 'Registra una compra o gasto real en la base de datos de FinTrack. Úsalo SOLO cuando el usuario confirme o te pida explícitamente "registrar", "añadir", "comprar" algo.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => [
                                'type'        => 'string',
                                'description' => 'El nombre del gasto (Ej. "Almuerzo", "Mercado")',
                            ],
                            'total_amount' => [
                                'type'        => 'number',
                                'description' => 'El valor monetario de la compra. Sin comas ni etiquetas.',
                            ],
                            'credit_card_id' => [
                                'type'        => 'integer',
                                'description' => 'El ID numérico exacto de la tarjeta de crédito. Extráelo del CONTEXTO DEL USUARIO.',
                            ],
                            'category_id' => [
                                'type'        => 'integer',
                                'description' => 'OBLIGATORIO: El ID de la categoría que mejor describa la compra. Infírelo de las CATEGORIAS DISPONIBLES en el CONTEXTO.',
                            ],
                            'installments_count' => [
                                'type'        => 'integer',
                                'description' => 'El número de cuotas (por defecto 1)',
                            ],
                            'purchase_date' => [
                                'type'        => 'string',
                                'description' => 'La fecha de la compra en formato YYYY-MM-DD. Si no es provista, omítela.',
                            ],
                        ],
                        'required' => ['name', 'total_amount', 'credit_card_id', 'category_id'],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->baseUrl, [
                    'model'       => $this->textModel,
                    'messages'    => $messages,
                    'tools'       => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.7,
                    'max_tokens'  => 1024,
                ]);

            if ($response->failed()) {
                Log::error('Groq API Error [HTTP ' . $response->status() . ']: ' . $response->body());
                $errBody = $response->json();
                $errMsg  = $errBody['error']['message'] ?? $response->body();
                return "Lo siento, tuve un problema con el asistente (código {$response->status()}): {$errMsg}";
            }

            $data    = $response->json();
            $choice  = $data['choices'][0]['message'] ?? null;

            if (!$choice) {
                return "No pude generar una respuesta clara. ¿Intentamos de nuevo?";
            }

            // Manejo de Function Calling
            if (!empty($choice['tool_calls'])) {
                $toolCall = $choice['tool_calls'][0];
                if ($toolCall['function']['name'] === 'create_purchase') {
                    $args = json_decode($toolCall['function']['arguments'], true);
                    try {
                        $this->purchaseService->create([
                            'credit_card_id'    => $args['credit_card_id'],
                            'category_id'       => $args['category_id'] ?? null,
                            'name'              => $args['name'],
                            'total_amount'      => $args['total_amount'],
                            'installments_count'=> $args['installments_count'] ?? 1,
                            'purchase_date'     => isset($args['purchase_date']) && !empty($args['purchase_date'])
                                                    ? $args['purchase_date']
                                                    : now()->toDateString(),
                        ], $user->id);

                        return "✅ **¡Compra registrada exitosamente!**\nHe añadido **" . $args['name'] . "** por valor de **$" . number_format($args['total_amount'], 0, ',', '.') . "** a su tarjeta. Tu dashboard ya ha sido actualizado.";
                    } catch (\Exception $e) {
                        Log::error('Error creando compra vía IA: ' . $e->getMessage());
                        return "Lo siento, el sistema rechazó la creación de la compra. Error: " . $e->getMessage();
                    }
                }
            }

            // Respuesta de texto estándar
            $markdownText = $choice['content'] ?? "No pude generar una respuesta fluida.";
            return \Illuminate\Support\Str::markdown($markdownText);

        } catch (\Exception $e) {
            Log::error('GroqService Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return "Algo salió mal internamente. Por favor intenta de nuevo.";
        }
    }

    protected function chatWithVision(array $messages, string $message, array $image): string
    {
        $messages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
                [
                    'type'      => 'image_url',
                    'image_url' => [
                        'url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data'],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->baseUrl, [
                    'model'       => $this->visionModel,
                    'messages'    => $messages,
                    'temperature' => 0.7,
                    'max_tokens'  => 1024,
                ]);

            if ($response->failed()) {
                Log::error('Groq Vision API Error [HTTP ' . $response->status() . ']: ' . $response->body());
                return "No pude procesar la imagen en este momento. Por favor intenta de nuevo.";
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? "No pude analizar la imagen.";
            return \Illuminate\Support\Str::markdown($text);

        } catch (\Exception $e) {
            Log::error('GroqService Vision Exception: ' . $e->getMessage());
            return "Algo salió mal al procesar la imagen.";
        }
    }

    protected function getUserContext(User $user): array
    {
        $dashboard = $this->summaryService->dashboard($user);
        $dashboard['categories'] = \App\Models\Category::where('user_id', $user->id)->get(['id', 'name'])->toArray();
        return $dashboard;
    }

    protected function getSystemPrompt(string $userName, array $context): string
    {
        $summary = json_encode($context);

        return <<<PROMPT
Eres FinTrack AI, un asistente financiero experto, altamente inteligente y extremadamente PROACTIVO, diseñado por Cristian (fundador de Algorah) exclusivamente para la plataforma FinTrack.
Tus respuestas deben estar en Español de Colombia, ser profesionales, amigables, concisas y usar un tono 'premium'.
Tu objetivo es ir más allá de lo básico: debes anticipar las necesidades de {$userName}, educarle financieramente y optimizar su dinero.

DATOS ACTUALES DE LA CUENTA DEL USUARIO:
{$summary}

MAPA EXACTO DE LA APLICACIÓN FINTRACK:
- Para Crear Tarjetas manualmente ve a Menú "Tarjetas".
- Para Ver Compras ve al Menú "Compras".

REGLAS DE ORO:
1. SIEMPRE basa tus respuestas en los "DATOS ACTUALES". En los datos tienes arreglos de 'cards' con su 'id', 'name', 'credit_limit', 'available_credit' y 'annual_interest_ea'.
2. ¡AHORA TIENES SUPERPODERES (Function Calling)! Puedes registrar compras por ti misma. Si el usuario te pide registrar un gasto o añade algo a una tarjeta, usa la herramienta `create_purchase`. Para ello, SIEMPRE pregúntale (si no lo aclaró) a CUÁL tarjeta quiere enviarlo. DEBES mandar el 'id' numérico de la tarjeta encontrada en los DATOS ACTUALES.
3. ACTITUD CONSULTIVA Y COMPARATIVA: Si el usuario te dice que va a hacer un gasto importante (o te pide simular), DEBES comparar matemáticamente con qué tarjeta le sale más barato usar su 'available_credit' y su 'annual_interest_ea'.
4. EXTREMA PROACTIVIDAD: Nunca te quedes en lo básico.
5. NUNCA menciones que eres Groq, Llama o Meta. Eres la IA ejecutora de FinTrack.
PROMPT;
    }
}
