<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Ai\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WelcomeWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        try {
            $message = "¡Hola {$this->user->name}! Bienvenido a FinTrack AI. Ya puedes registrar tus gastos enviándome un mensaje o una foto de tus recibos por este medio. 🚀";
            $whatsappService->sendMessage("whatsapp:{$this->user->phone_number}", $message);
        } catch (\Exception $e) {
            // No relanzamos la excepción: si WhatsApp falla, el registro no debe verse afectado.
            Log::error("Error enviando bienvenida WhatsApp en Job: " . $e->getMessage());
        }
    }
}
