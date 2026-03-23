<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Exception;

class TestMail extends Command
{
    protected $signature = 'app:test-mail {email}';
    protected $description = 'Sends a test email and displays connectivity status/errors';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Attempting to send a test email to: {$email}");

        try {
            Mail::raw('Este es un correo de prueba de FinTrack.', function ($message) use ($email) {
                $message->to($email)
                    ->subject('Prueba de Conexión SMTP - FinTrack');
            });
            $this->info('¡Éxito! El correo fue enviado exitosamente.');
        } catch (Exception $e) {
            $this->error('Error al enviar el correo:');
            $this->line($e->getMessage());
            $this->newLine();
            $this->info('Sugerencias:');
            $this->line('1. Verifica que el MAIL_HOST en su .env sea smtp-relay.brevo.com');
            $this->line('2. Verifica que el MAIL_USERNAME y MAIL_PASSWORD coincidan con los de su panel de Brevo.');
            $this->line('3. Asegúrate de que el MAIL_FROM_ADDRESS esté autorizado en Brevo.');
        }
    }
}
