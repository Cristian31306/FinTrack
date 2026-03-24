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

    protected string $textModel   = 'llama-3.3-70b-versatile';
    protected string $visionModel = 'llama-3.2-11b-vision-preview';

    public function __construct(
        protected DebtSummaryService $summaryService,
        protected PurchaseService $purchaseService
    ) {
        $this->apiKey = config('services.groq.key');
    }

    public function chat(User $user, string $message, array $history = [], ?array $image = null): string
    {
        $context      = $this->getUserContext($user);
        $systemPrompt = $this->getSystemPrompt($user->name, $context);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            if (str_starts_with($msg['content'], '✅')) continue;
            $messages[] = [
                'role'    => $msg['role'] === 'bot' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }

        if ($image) {
            return $this->chatWithVision($messages, $message, $image);
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $tools = [
            // PASO 1: Vista previa (nunca guarda en BD)
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'prepare_purchase',
                    'description' => 'Genera una VISTA PREVIA de la compra para que el usuario la revise y apruebe. Llama esta función SIEMPRE que tengas todos los datos (nombre, monto, tarjeta, categoría). NO guarda nada en la base de datos. El usuario deberá confirmar antes de que se ejecute create_purchase.',
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => [
                                'type'        => 'string',
                                'description' => 'Nombre descriptivo del gasto',
                            ],
                            'total_amount' => [
                                'type'        => 'number',
                                'description' => 'Valor monetario positivo mayor a 0',
                            ],
                            'credit_card_id' => [
                                'type'        => 'integer',
                                'description' => 'ID numérico de la tarjeta',
                            ],
                            'credit_card_name' => [
                                'type'        => 'string',
                                'description' => 'Nombre de la tarjeta para mostrarlo al usuario',
                            ],
                            'category_id' => [
                                'type'        => 'integer',
                                'description' => 'ID de la categoría inferida',
                            ],
                            'category_name' => [
                                'type'        => 'string',
                                'description' => 'Nombre de la categoría para mostrarlo al usuario',
                            ],
                            'installments_count' => [
                                'type'        => 'integer',
                                'description' => 'Número de cuotas (por defecto 1)',
                            ],
                            'purchase_date' => [
                                'type'        => 'string',
                                'description' => 'Fecha de la compra en formato YYYY-MM-DD',
                            ],
                        ],
                        'required' => ['name', 'total_amount', 'credit_card_id', 'credit_card_name', 'category_id', 'category_name'],
                    ],
                ],
            ],
            // PASO 2: Registro real (solo después de confirmación del usuario)
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_purchase',
                    'description' => 'Registra DEFINITIVAMENTE la compra en la base de datos. SOLO llama esta función cuando el usuario haya respondido con una confirmación explícita como "sí", "confirmar", "dale", "ok", "correcto", "procede" después de ver la vista previa de prepare_purchase. NUNCA llames esta función sin que el usuario haya confirmado primero.',
                    'parameters' => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'total_amount' => ['type' => 'number'],
                            'credit_card_id' => ['type' => 'integer'],
                            'category_id' => ['type' => 'integer'],
                            'installments_count' => ['type' => 'integer'],
                            'purchase_date' => ['type' => 'string'],
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

            $data   = $response->json();
            $choice = $data['choices'][0]['message'] ?? null;

            if (!$choice) {
                return "No pude generar una respuesta clara. ¿Intentamos de nuevo?";
            }

            if (!empty($choice['tool_calls'])) {
                $toolCall = $choice['tool_calls'][0];
                $funcName = $toolCall['function']['name'];
                $args     = json_decode($toolCall['function']['arguments'], true);

                // ── PASO 1: Vista previa ───────────────────────────────────────
                if ($funcName === 'prepare_purchase') {
                    $amount   = (float) ($args['total_amount'] ?? 0);
                    $name     = trim($args['name'] ?? '');

                    if ($amount <= 0 || strlen($name) < 3) {
                        return "Para continuar necesito que me confirmes: **¿cuánto fue el valor exacto?** y **¿cuál es el nombre del gasto?**";
                    }

                    $date         = $this->resolveDate($args['purchase_date'] ?? null);
                    $cardName     = $args['credit_card_name'] ?? "Tarjeta ID " . ($args['credit_card_id'] ?? '?');
                    $categoryName = $args['category_name']    ?? "Categoría ID " . ($args['category_id'] ?? '?');
                    $cuotas       = (int) ($args['installments_count'] ?? 1);
                    $carbonDate   = \Carbon\Carbon::parse($date);
                    $meses        = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    $dateLabel    = $carbonDate->day . ' de ' . $meses[$carbonDate->month - 1] . ' de ' . $carbonDate->year;
                    $amountFmt    = '$' . number_format($amount, 0, ',', '.');

                    $markdown = "📋 **Vista previa del registro:**\n\n" .
                           "| Campo | Valor |\n" .
                           "|---|---|\n" .
                           "| 🛒 Descripción | **{$name}** |\n" .
                           "| 💰 Valor | **{$amountFmt}** |\n" .
                           "| 💳 Tarjeta | **{$cardName}** |\n" .
                           "| 🏷️ Categoría | **{$categoryName}** |\n" .
                           "| 📅 Fecha | **{$dateLabel}** |\n" .
                           "| 🔢 Cuotas | **{$cuotas}** |\n\n" .
                           "¿Confirmas el registro? Responde **sí** para guardar o **no** si necesitas corregir algo.";

                    return \Illuminate\Support\Str::markdown($markdown);
                }

                // ── PASO 2: Registro definitivo ───────────────────────────────
                if ($funcName === 'create_purchase') {
                    $amount = (float) ($args['total_amount'] ?? 0);
                    $name   = trim($args['name'] ?? '');

                    if ($amount <= 0 || strlen($name) < 3) {
                        return "Para registrar el gasto necesito que me confirmes: **¿cuánto fue el valor exacto?** y **¿cuál es el nombre del gasto?**";
                    }

                    $date = $this->resolveDate($args['purchase_date'] ?? null);

                    try {
                        $this->purchaseService->create([
                            'credit_card_id'     => $args['credit_card_id'],
                            'category_id'        => $args['category_id'] ?? null,
                            'name'               => $name,
                            'total_amount'       => $amount,
                            'installments_count' => $args['installments_count'] ?? 1,
                            'purchase_date'      => $date,
                        ], $user->id);

                        return "✅ **¡Compra registrada exitosamente!**\nHe añadido **{$name}** por valor de **$" . number_format($amount, 0, ',', '.') . "** a su tarjeta. Tu dashboard ya ha sido actualizado.";
                    } catch (\Exception $e) {
                        Log::error('Error creando compra vía IA: ' . $e->getMessage());
                        return "Lo siento, el sistema rechazó la creación de la compra. Error: " . $e->getMessage();
                    }
                }
            }

            $markdownText = $choice['content'] ?? "No pude generar una respuesta fluida.";
            return \Illuminate\Support\Str::markdown($markdownText);

        } catch (\Exception $e) {
            Log::error('GroqService Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return "Algo salió mal internamente. Por favor intenta de nuevo.";
        }
    }

    /**
     * Resuelve y valida la fecha: corrige el año si el modelo usa uno erróneo.
     */
    protected function resolveDate(?string $rawDate): string
    {
        $currentYear = (int) now()->format('Y');

        if (!$rawDate) {
            return now()->toDateString();
        }

        try {
            $parsed = \Carbon\Carbon::parse($rawDate);
            $year   = (int) $parsed->format('Y');

            // Si el año está fuera de rango razonable (±1 del actual), corregirlo
            if (abs($year - $currentYear) > 1) {
                $parsed->setYear($currentYear);
                // Si la fecha resultante es futura, usar el año anterior
                if ($parsed->isFuture()) {
                    $parsed->setYear($currentYear - 1);
                }
            }

            return $parsed->toDateString();
        } catch (\Exception $e) {
            return now()->toDateString();
        }
    }

    protected function chatWithVision(array $messages, string $message, array $image): string
    {
        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $message],
                [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:' . $image['mime_type'] . ';base64,' . $image['data']],
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
        $dashboard['categories'] = \App\Models\Category::where('user_id', $user->id)
            ->get(['id', 'name', 'icon'])
            ->toArray();
        return $dashboard;
    }

    protected function getSystemPrompt(string $userName, array $context): string
    {
        $categories = $context['categories'] ?? [];
        unset($context['categories']);
        $summary = json_encode($context);

        $catList = '';
        foreach ($categories as $cat) {
            $catList .= "  - ID {$cat['id']}: \"{$cat['name']}\"\n";
        }
        if (empty($catList)) {
            $catList = "  (El usuario aún no tiene categorías creadas)\n";
        }

        $today = now()->format('Y-m-d');

        return <<<PROMPT
Eres FinTrack AI, un asistente financiero experto y PROACTIVO de la plataforma FinTrack, diseñado por Cristian (Algorah).
Responde siempre en Español de Colombia, con tono profesional y premium.

HOY ES: {$today}. Úsalo para inferir años en fechas como "22/03".

══════════════════════════════════════════
DATOS FINANCIEROS DEL USUARIO:
══════════════════════════════════════════
{$summary}

══════════════════════════════════════════
CATEGORÍAS DISPONIBLES (ESTRICTAS):
══════════════════════════════════════════
{$catList}

REGLA DE CATEGORIZACIÓN:
Cuando el usuario mencione un gasto, DEBES buscar en la lista de arriba la categoría que mejor encaje semánticamente. 
- "Tanqueada", "gasolina", "combustible" → Busca una categoría de Transporte.
- "Almuerzo", "cena", "comida", "restaurante" → Busca una categoría de Alimentación.
- "Netflix", "internet", "agua", "luz" → Busca categorías de Servicios o Suscripciones.
- SIEMPRE usa un `category_id` de la lista anterior. NO inventes IDs.

══════════════════════════════════════════
FLUJO DE REGISTRO (CONFIRMACIÓN FLEXIBLE):
══════════════════════════════════════════
1. PASO 1 (VISTA PREVIA): Siempre que tengas los datos (nombre, monto, tarjeta, categoría), llama a `prepare_purchase`.
2. PASO 2 (REGISTRO): SOLO si el usuario confirma en el siguiente mensaje, llama a `create_purchase`.
   - Acepta CUALQUIER forma de confirmación positiva: "si", "sí", "dale", "procede", "ok", "está bien", "confirmado", "hágale", "listo", "bueno", "perfecto", "adelante".
   - NO seas estricto con la ortografía o tildes.

⛔ NUNCA llames `create_purchase` sin mostrar la vista previa primero.
⛔ NUNCA registres con monto 0 ni nombres genéricos.
PROMPT;
    }
}
