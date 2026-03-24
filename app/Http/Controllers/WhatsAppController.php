<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function __construct(
        protected AiAssistantService $aiService,
        protected WhatsAppService $whatsappService
    ) {}

    /**
     * Maneja el webhook entrante de Twilio.
     */
    public function webhook(Request $request)
    {
        $from    = $request->input('From'); // whatsapp:+57...
        $body    = $request->input('Body');
        $mediaUrl = $request->input('MediaUrl0');

        Log::info("[WhatsApp Webhook] Mensaje recibido", [
            'from' => $from,
            'body' => $body,
            'has_media' => !empty($mediaUrl)
        ]);

        // 1. Identificar al usuario por el número de teléfono
        $phoneNumber = str_replace('whatsapp:', '', $from);
        $user = User::where('phone_number', $phoneNumber)
                    ->orWhere('phone_number', $from)
                    ->first();

        if (!$user) {
            $this->whatsappService->sendMessage($from, "Lo siento, no reconozco este número de teléfono en FinTrack. Por favor, asegúrate de registrarlo en tu perfil.");
            return response('User not found', 200);
        }

        try {
            // 2. Procesar imagen si existe
            $image = null;
            if (!empty($mediaUrl)) {
                $image = $this->whatsappService->downloadMedia($mediaUrl);
            }

            // 3. Obtener respuesta de la IA
            // Nota: Para WhatsApp no estamos manejando historial persistente en esta fase,
            // pero AiAssistantService lo usa para reconstruir el estado de confirmación si es necesario.
            $response = $this->aiService->chat($user, $body ?? '', [], $image);

            // 4. Enviar respuesta de vuelta
            $this->whatsappService->sendMessage($from, $response);

        } catch (\Exception $e) {
            Log::error("[WhatsApp Webhook] Error: " . $e->getMessage());
            $this->whatsappService->sendMessage($from, "Ups, tuve un problema procesando tu mensaje. Intenta de nuevo en un momento.");
        }

        return response('OK', 200);
    }
}
