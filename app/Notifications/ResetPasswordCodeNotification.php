<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ResetPasswordCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontend = rtrim(env('FRONTEND_URL'), '/');
        $actionUrl = $frontend . '/restablecer-contrasena?correo=' . urlencode($notifiable->correo);

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('EPESPO | Código para restablecer contraseña')
            ->view('emails.reset_password_code', [
                'code' => $this->code,
                'actionUrl' => $actionUrl,
                'expiresMinutes' => 10,
            ]);
    }
}
