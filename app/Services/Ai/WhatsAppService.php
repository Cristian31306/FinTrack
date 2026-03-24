<?php

namespace App\Services\Ai;

use Twilio\Rest\Client;
use Twilio\Http\CurlClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected Client $twilio;
    protected string $from;

    public function __construct()
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->from = config('services.twilio.from');

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

    /**
     * Envía un mensaje de texto por WhatsApp.
     */
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
