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
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(
        protected DebtSummaryService $summaryService,
        protected PurchaseService $purchaseService
    ) {
        $this->apiKey = config('services.gemini.key');
    }

    protected function getApiUrl(): string
    {
        $model = config('services.gemini.model', 'gemini-2.0-flash-lite');
        return $this->baseUrl . $model . ':generateContent';
    }

    public function chat(User $user, string $message, array $history = [], ?array $image = null): string
    {
        $context = $this->getUserContext($user);
        
        $systemPrompt = $this->getSystemPrompt($user->name, $context);

        $contents = [];
        
        // System instruction (special for Gemini 1.5/2.5)
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => "INSTRUCCIÓN DE SISTEMA: " . $systemPrompt]]
        ];
        $contents[] = [
            'role' => 'model',
            'parts' => [['text' => "Entendido. Soy FinTrack AI, tu super-agente financiero. Te ayudaré a analizar o registrar lo que necesites."]]
        ];

        // Add history
        foreach ($history as $msg) {
            // Ignorar respuestas inyectadas por function calling local
            if (str_starts_with($msg['content'], '✅')) continue;
            
            $contents[] = [
                'role' => $msg['role'] === 'bot' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        // Add current message
        $userParts = [['text' => $message]];
        if ($image) {
            $userParts[] = [
                'inlineData' => [
                    'mimeType' => $image['mime_type'],
                    'data' => $image['data']
                ]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => $userParts
        ];

        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'create_purchase',
                        'description' => 'Registra una compra o gasto real en la base de datos de FinTrack. Úsalo SOLO cuando el usuario confirme o te pida explícitamente "registrar", "añadir", "comprar" algo.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'name' => [
                                    'type' => 'STRING',
                                    'description' => 'El nombre del gasto (Ej. "Almuerzo", "Mercado")'
                                ],
                                'total_amount' => [
                                    'type' => 'NUMBER',
                                    'description' => 'El valor monetario de la compra. Sin comas ni etiquetas.'
                                ],
                                'credit_card_id' => [
                                    'type' => 'INTEGER',
                                    'description' => 'El ID numérico exacto de la tarjeta de crédito que se usará. Debes extraerlo del CONTEXTO DEL USUARIO.'
                                ],
                                'category_id' => [
                                    'type' => 'INTEGER',
                                    'description' => 'OBLIGATORIO: El ID de la categoría que mejor describa la compra. Debes inferirlo lógicamente eligiendo de las CATEGORIAS DISPONIBLES en el CONTEXTO DEL USUARIO.'
                                ],
                                'installments_count' => [
                                    'type' => 'INTEGER',
                                    'description' => 'El número de cuotas (por defecto 1)'
                                ],
                                'purchase_date' => [
                                    'type' => 'STRING',
                                    'description' => 'La fecha exacta o aproximada provista por el usuario para la compra en formato YYYY-MM-DD. Si no es provisto, omítelo.'
                                ]
                            ],
                            'required' => ['name', 'total_amount', 'credit_card_id', 'category_id']
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->getApiUrl() . '?key=' . $this->apiKey, [
                    'contents' => $contents,
                    'tools' => $tools,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error [HTTP ' . $response->status() . ']: ' . $response->body());
                $errBody = $response->json();
                $errMsg  = $errBody['error']['message'] ?? $response->body();
                return "Lo siento, tuve un problema conectando con mi cerebro (código {$response->status()}): {$errMsg}";
            }

            $data = $response->json();
            $part = $data['candidates'][0]['content']['parts'][0] ?? null;

            if (!$part) {
                return "No pude generar una respuesta clara. ¿Intentamos de nuevo?";
            }

            // Manejo de Invocación de Función (Function Calling)
            if (isset($part['functionCall'])) {
                $func = $part['functionCall'];
                if ($func['name'] === 'create_purchase') {
                    $args = $func['args'];
                    
                    try {
                        $this->purchaseService->create([
                            'credit_card_id' => $args['credit_card_id'],
                            'category_id' => $args['category_id'] ?? null,
                            'name' => $args['name'],
                            'total_amount' => $args['total_amount'],
                            'installments_count' => $args['installments_count'] ?? 1,
                            'purchase_date' => isset($args['purchase_date']) && !empty($args['purchase_date']) ? $args['purchase_date'] : now()->toDateString(),
                        ], $user->id);
                        return "✅ **¡Compra registrada exitosamente!**\nHe añadido **" . $args['name'] . "** por valor de **$" . number_format($args['total_amount'], 0, ',', '.') . "** a su tarjeta. Tu dashboard ya ha sido actualizado con los nuevos saldos rápidos.";
                    } catch (\Exception $e) {
                         Log::error('Error creando compra vía IA: ' . $e->getMessage());
                         return "Lo siento, el sistema rechazó la creación de la compra. Asegúrate de tener una tarjeta de crédito válida seleccionada. Error interno: " . $e->getMessage();
                    }
                }
            }
            
            // Respuesta de texto estándar
            $markdownText = $part['text'] ?? "No pude generar una respuesta fluida.";
            
            // Convertir de Markdown a HTML usando Laravel Str::markdown
            return \Illuminate\Support\Str::markdown($markdownText);

        } catch (\Exception $e) {
            Log::error('AiService Exception: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return "Algo salió mal internamente. Por favor intenta de nuevo.";
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
5. NUNCA menciones que eres de Google o Gemini. Eres la IA ejecutora de FinTrack.
PROMPT;
    }
}
