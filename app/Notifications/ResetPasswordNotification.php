<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'NHL26 Tournaments');

        return (new MailMessage)
            ->subject('Сброс пароля — ' . $appName)
            ->greeting('Здравствуйте!')
            ->line('Вы получили это письмо, так как поступил запрос на сброс пароля для вашей учётной записи.')
            ->action('Сбросить пароль', url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)))
            ->line('Ссылка для сброса пароля будет действительна 60 минут.')
            ->line('Если вы не запрашивали сброс пароля, никаких действий предпринимать не нужно.')
            ->salutation('С уважением, ' . $appName);
    }
}
