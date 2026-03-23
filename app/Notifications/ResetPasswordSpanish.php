<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordSpanish extends ResetPassword
{
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Notificación de restablecimiento de contraseña')
            ->greeting('¡Hola!')
            ->line('Estás recibiendo este correo electrónico porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.')
            ->action('Restablecer Contraseña', url(config('app.url').route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line('Este enlace de restablecimiento de contraseña caducará en ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' minutos.')
            ->line('Si no solicitaste un restablecimiento de contraseña, no es necesario realizar ninguna otra acción.');
    }
}
