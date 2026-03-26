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
            $msg = "¡Hola! 🚀 Soy FinTrack AI, tu nuevo asistente financiero inteligente.\n\n" .
                   "Veo que aún no estás registrado. Conmigo puedes:\n" .
                   "✅ Registrar gastos solo con un mensaje o foto.\n" .
                   "💳 Controlar tus tarjetas de crédito y fechas de corte.\n" .
                   "📊 Ver resúmenes de tus deudas y categorías.\n" .
                   "💡 Recibir consejos para mejorar tus finanzas.\n\n" .
                   "Para empezar, regístrate aquí: https://fintrack.algorah.bond/register\n\n" .
                   "¡Te espero para empezar a ahorrar juntos! 📈";
            
            return response("<Response><Message>" . htmlspecialchars($msg) . "</Message></Response>", 200)
                ->header('Content-Type', 'text/xml');
        }

        try {
            $whatsappService = app(WhatsAppService::class);
            $botService = app(\App\Services\Ai\WhatsAppBotService::class);

            // 2. Procesar imagen si existe (aunque el bot estructurado aún no procese OCR, lo dejamos para futura expansión)
            $image = null;
            if (!empty($mediaUrl)) {
                $image = $whatsappService->downloadMedia($mediaUrl);
            }

            // 3. Manejar con el Bot Estructurado
            $response = $botService->handle($user, $body ?? '', $from);

            // Si el bot devuelve null (rara vez), enviamos el menú por defecto
            if ($response === null) {
                $response = "No entendí ese comando. Escribe 'menu' para ver las opciones disponibles.";
            }

            // 4. Si la respuesta trae botones o lista, los enviamos vía API y retornamos TwiML vacío
            if (is_array($response) && isset($response['type'])) {
                if ($response['type'] === 'list') {
                    $whatsappService->sendList(
                        $from, 
                        $response['text'], 
                        $response['buttonText'] ?? 'Opciones', 
                        $response['options']
                    );
                } elseif ($response['type'] === 'buttons') {
                    $whatsappService->sendButtons(
                        $from, 
                        $response['text'], 
                        $response['buttons']
                    );
                }
                
                return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response></Response>", 200)
                    ->header('Content-Type', 'text/xml');
            }

            // 5. Devolver TwiML estándar para mensajes de texto
            $text = is_array($response) ? ($response['text'] ?? '') : $response;
            $xmlResponse = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response><Message>" . htmlspecialchars($text) . "</Message></Response>";
            
            return response($xmlResponse, 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Throwable $e) {
            Log::error("[WhatsApp Webhook] Error: " . $e->getMessage());
            return response("<Response><Message>Ups, tuve un problema procesando tu mensaje. Intenta de nuevo en un momento.</Message></Response>", 200)
                ->header('Content-Type', 'text/xml');
        }
    }
}
