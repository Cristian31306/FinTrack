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

        if (empty($sid) || empty($token)) {
            Log::error("[WhatsAppService] Twilio SID o Token no configurados. WhatsApp desactivado.");
            return;
        }

        try {
            $this->twilio = new Client($sid, $token, null, null, $httpClient);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Falló inicialización de cliente Twilio: " . $e->getMessage());
        }
    }

    public function sendMessage(string $to, string $message): void
    {
        if (!isset($this->twilio)) return;

        try {
            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Error enviando mensaje: " . $e->getMessage());
        }
    }

    /**
     * Envía botones interactivos por WhatsApp (Reply Buttons).
     * Solo funciona si el usuario envió un mensaje en las últimas 24h.
     */
    public function sendButtons(string $to, string $text, array $buttons): void
    {
        if (!isset($this->twilio)) return;

        $contentSid = config('services.twilio.content_sid');
        
        try {
            if ($contentSid) {
                // Usar Content API de Twilio (si está configurada)
                $this->twilio->messages->create($to, [
                    'from' => $this->from,
                    'contentSid' => $contentSid,
                    'contentVariables' => json_encode(['1' => $text]),
                ]);
            } else {
                // Fallback a botones mediante interactive message simulado o texto
                // En Twilio WhatsApp, las Reply Buttons suelen requerir Content SID.
                // Si no hay, mandamos una lista de texto.
                $this->twilio->messages->create($to, [
                    'from' => $this->from,
                    'body' => $text . "\n\nResponde con una de las opciones:\n- " . implode("\n- ", $buttons)
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Error enviando botones: " . $e->getMessage());
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
