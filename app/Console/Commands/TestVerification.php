<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\VerifyEmailSpanish;
use Exception;

class TestVerification extends Command
{
    protected $signature = 'app:test-verification {email}';
    protected $description = 'Sends the Spanish verification email to a specific email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emailAddress = $this->argument('email');
        $this->info("Buscando usuario con email: {$emailAddress}");

        $user = User::where('email', $emailAddress)->first();

        if (!$user) {
            $this->error("Usuario no encontrado.");
            return 1;
        }

        $this->info("Intentando enviar notificación de verificación a: {$user->email}");

        try {
            $user->notify(new VerifyEmailSpanish());
            $this->info("¡Notificación enviada exitosamente!");
        } catch (Exception $e) {
            $this->error("Error al enviar la notificación:");
            $this->line($e->getMessage());
        }

        return 0;
    }
}
