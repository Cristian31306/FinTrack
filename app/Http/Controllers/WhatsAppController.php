<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function webhook(Request $request)
    {
        $from    = $request->input('From'); // whatsapp:+57...
        $body    = $request->input('Body');
        $mediaUrl = $request->input('MediaUrl0');

        Log::info('[WhatsApp Webhook] Mensaje recibido', [
            'from' => $from,
            'body' => $body,
            'media' => $mediaUrl
        ]);

        // 1. Identificar al usuario por el número de teléfono
        $phoneNumber = str_replace('whatsapp:', '', $from);
        $user = User::where('phone_number', $phoneNumber)
                    ->orWhere('phone_number', $from)
                    ->first();

        if (!$user) {
            Log::warning("[WhatsApp Webhook] Usuario no encontrado para el número: $from");
            return response("<Response><Message>Lo siento, no reconozco este número en FinTrack. Por favor, asegúrate de registrarlo en tu perfil.</Message></Response>", 200)
                ->header('Content-Type', 'text/xml');
        }

        try {
            $whatsappService = app(WhatsAppService::class);
            $aiService = app(AiAssistantService::class);

            // 2. Procesar imagen si existe
            $image = null;
            if (!empty($mediaUrl)) {
                $image = $whatsappService->downloadMedia($mediaUrl);
            }

            // 3. Obtener respuesta de la IA
            $response = $aiService->chat($user, $body ?? '', [], $image, true);

            Log::info('[WhatsApp Webhook] Respondiendo con TwiML');

            // 4. Devolver TwiML directamente
            $xmlResponse = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><Message>" . htmlspecialchars($response) . "</Message></Response>";
            
            return response($xmlResponse, 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Throwable $e) {
            Log::error("[WhatsApp Webhook] Error: " . $e->getMessage());
            return response("<Response><Message>Ups, tuve un problema procesando tu mensaje. Intenta de nuevo en un momento.</Message></Response>", 200)
                ->header('Content-Type', 'text/xml');
        }
    }
}
