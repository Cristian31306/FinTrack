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
        $sid = config('services.twilio.sid', env('TWILIO_SID', ''));
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
        }
        catch (\Throwable $e) {
            Log::error("[WhatsAppService] Falló inicialización de cliente Twilio: " . $e->getMessage());
        }
    }

    public function sendMessage(string $to, string $message): void
    {
        if (!isset($this->twilio))
            return;

        try {
            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);
        }
        catch (\Throwable $e) {
            Log::error("[WhatsAppService] Error enviando mensaje: " . $e->getMessage());
        }
    }

    /**
     * Envía botones interactivos por WhatsApp (Reply Buttons - Máx 3).
     */
    public function sendButtons(string $to, string $text, array $buttons): void
    {
        if (!isset($this->twilio)) return;

        try {
            // Logica para botones rápidos (Quick Replies)
            // Si no hay Content SID, mandamos texto con formato
            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => $text . "\n\n" . implode(" | ", array_map(fn($b) => "[$b]", $buttons))
            ]);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Error enviando botones: " . $e->getMessage());
        }
    }

    /**
     * Envía una lista desplegable (List Message - Máx 10 opciones).
     */
    public function sendList(string $to, string $text, string $buttonText, array $options): void
    {
        if (!isset($this->twilio)) return;

        try {
            // Las listas interactivas reales requieren Content SID o la API de Meta directa.
            // Para Twilio, si No hay Content SID, enviamos un menú numerado muy claro.
            $numberedOptions = "";
            foreach ($options as $index => $opt) {
                $numberedOptions .= ($index + 1) . ". $opt\n";
            }

            $this->twilio->messages->create($to, [
                'from' => $this->from,
                'body' => "$text\n\n$numberedOptions\nResponde con el número o nombre de la opción."
            ]);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Error enviando lista: " . $e->getMessage());
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
                    'data' => base64_encode($response->body()),
                ];
            }
        }
        catch (\Exception $e) {
            Log::error("[WhatsAppService] Error descargando media: " . $e->getMessage());
        }

        return null;
    }
}
