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

        // Añadir historial (evitamos duplicar tags de éxito)
        foreach ($history as $msg) {
            if (str_contains($msg['content'] ?? '', '✅')) continue;
            $messages[] = [
                'role'    => ($msg['role'] ?? 'bot') === 'bot' ? 'assistant' : 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        // Manejo de Visión (Llama 3.2 Vision)
        if ($image) {
            return $this->chatWithVision($messages, $message, $image);
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        // Definición de Herramientas (Functions)
        $tools = $this->getToolsDefinition();

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
                    'temperature' => 0.6, // Bajamos un poco la temperatura para mayor precisión
                    'max_tokens'  => 1024,
                ]);

            if ($response->failed()) {
                Log::error('Groq API Error: ' . $response->body());
                return "Lo siento, tuve un problema conectando con mi cerebro (código {$response->status()})";
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
            $categoryMatch = $userCategories->first(); // Fallback a la primera categoría del usuario
            $args['category_id']   = $categoryMatch['id'] ?? null;
            $args['category_name'] = $categoryMatch['name'] ?? 'General';
        } else {
            // Aseguramos el nombre real de la BD
            $args['category_name'] = $categoryMatch['name'];
        }

        // --- RESOLVER FECHA ---
        $args['purchase_date'] = $this->resolveDate($args['purchase_date'] ?? null);

        // --- PERSISTENCIA EN CACHE (10 minutos) ---
        Cache::put("pending_purchase_{$user->id}", $args, now()->addMinutes(10));

        // --- RENDERIZADO DE TABLA ---
        $dateLabel = \Carbon\Carbon::parse($args['purchase_date'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
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
                    'parameters'  => ['type' => 'object', 'properties' => []],
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

        $today = now()->toDateString();

        return <<<PROMPT
Eres FinTrack AI, el cerebro financiero proactivo de la plataforma. Diseñado por Cristian (Algorah).
Responde en Español de Colombia con tono premium.

HOY ES: {$today}.

══════════════════════════════════════════
ESTADO FINANCIERO: {$summary}
══════════════════════════════════════════
CATEGORÍAS DISPONIBLES:
{$catList}

🚨 CONTRATO DE CATEGORIZACIÓN (ESTRICTO):
Busca siempre la coincidencia semántica más fuerte ignorando nombres genéricos:
- Gasto en Gasolina, Tanqueada, Moto, Terpel, Primax -> USA Categoría de "Transporte".
- Gasto en D1, Éxito, Rappi, Comida, Almuerzo, Cena -> USA Categoría de "Alimentación" o "Mercado".
- Gasto en Spotify, Netflix, Disney -> USA "Suscripciones" o "Entretenimiento".
- NUNCA elijas "Tarjeta" o "Personalizada" si hay una categoría mejor.

══════════════════════════════════════════
FLUJO DE REGISTRO SEGURO:
══════════════════════════════════════════
1. PASO 1 (Vista Previa): Llama a `prepare_purchase`. Muestra la tabla al usuario.
2. PASO 2 (Registro Real): Si el usuario confirma (ej. "sí", "dale", "ok", botón clic), llama a `create_purchase`.
   - NO necesitas pasar argumentos a `create_purchase`, el sistema recuperará los datos del Cache.
   - Si no has mostrado la vista previa, NUNCA llames a `create_purchase`.
   - Si el usuario dice "No", pregunta qué dato corregir.

¡Tu misión es que {$userName} tenga un control total de sus gastos sin fricción!
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
