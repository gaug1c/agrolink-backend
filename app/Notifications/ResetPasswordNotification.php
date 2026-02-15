<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontendUrl = "http://localhost:3000/reset-password?token={$this->token}&email={$notifiable->email}";

        return (new MailMessage)
            ->subject('Réinitialisation du mot de passe')
            ->line('Cliquez sur le bouton pour réinitialiser votre mot de passe.')
            ->action('Réinitialiser le mot de passe', $frontendUrl)
            ->line('Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.');
    }
}
