<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

// ⚠️ Sin ShouldQueue: el email de verificación se envía sincrónicamente
// para garantizar que el usuario lo reciba inmediatamente al registrarse.
class VerifyEmailSpanish extends VerifyEmail
{
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu dirección de correo electrónico')
            ->greeting('¡Hola!')
            ->line('Haz clic en el botón de abajo para verificar tu dirección de correo electrónico.')
            ->action('Verificar Correo', $verificationUrl)
            ->line('Si no creaste una cuenta, no es necesario realizar ninguna otra acción.');
    }
}

