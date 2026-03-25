<?php

namespace App\Services\Ai;

use Twilio\Rest\Client;
use Twilio\Http\CurlClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected Client $twilio;
    protected ?string $from = null;

    public function __construct()
    {
        $sid   = config('services.twilio.sid', env('TWILIO_SID', ''));
        $token = config('services.twilio.token', env('TWILIO_TOKEN', ''));
        $this->from = config('services.twilio.from', env('TWILIO_WHATSAPP_FROM', ''));

        // Configuración para entornos locales (Windows suele fallar con SSL)
        $httpClient = null;
        if (config('app.env') === 'local') {
            $httpClient = new CurlClient([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
        }

        $this->twilio = new Client($sid, $token, null, null, $httpClient);
    }

    public function sendMessage(string $to, string $message): void
    {
        try {
            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);
        } catch (\Exception $e) {
            Log::error("[WhatsAppService] Error enviando mensaje: " . $e->getMessage());
        }
    }

    /**
     * Envía botones interactivos por WhatsApp (Reply Buttons).
     * Solo funciona si el usuario envió un mensaje en las últimas 24h.
     */
    public function sendButtons(string $to, string $body, array $buttons): void
    {
        try {
            // WhatsApp permite máximo 3 botones de respuesta rápida
            $buttonItems = [];
            foreach (array_slice($buttons, 0, 3) as $btn) {
                $buttonItems[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id'    => \Illuminate\Support\Str::slug($btn),
                        'title' => $btn
                    ]
                ];
            }

            // Usamos el parámetro 'content' para enviar el JSON interactivo directamente
            // Nota: Requiere que la cuenta de Twilio soporte este formato (Content API)
            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'content' => json_encode([
                    'type'    => 'whatsapp/button',
                    'body'    => ['text' => $body],
                    'actions' => $buttonItems
                ])
            ]);
        } catch (\Exception $e) {
            Log::warning("[WhatsAppService] Falló envío de botones (usando texto): " . $e->getMessage());
            // Fallback a mensaje de texto si los botones fallan
            $this->sendMessage($to, $body . "\n\nResponde: " . implode(" o ", $buttons));
        }
    }

    /**
     * Descarga un archivo multimedia de Twilio y devuelve los datos en base64.
     */
    public function downloadMedia(string $mediaUrl): ?array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withoutVerifying()
                ->withBasicAuth(config('services.twilio.sid'), config('services.twilio.token'))
                ->get($mediaUrl);

            if ($response->successful()) {
                return [
                    'mime_type' => $response->header('Content-Type'),
                    'data'      => base64_encode($response->body()),
                ];
            }
        } catch (\Exception $e) {
            Log::error("[WhatsAppService] Error descargando media: " . $e->getMessage());
        }

        return null;
    }
}
