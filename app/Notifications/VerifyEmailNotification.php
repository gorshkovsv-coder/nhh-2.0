<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'NHL26 Tournaments');

        return (new MailMessage)
            ->subject('Подтверждение email — ' . $appName)
            ->greeting('Здравствуйте!')
            ->line('Спасибо за регистрацию в ' . $appName . '! Пожалуйста, подтвердите ваш email, нажав на кнопку ниже.')
            ->action('Подтвердить email', $this->verificationUrl($notifiable))
            ->line('Ссылка для подтверждения действительна 60 минут.')
            ->line('Если вы не создавали аккаунт, никаких действий предпринимать не нужно.')
            ->salutation('С уважением, ' . $appName);
    }

    protected function verificationUrl($notifiable): string
    {
        $expiration = (int) Config::get('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($expiration),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
