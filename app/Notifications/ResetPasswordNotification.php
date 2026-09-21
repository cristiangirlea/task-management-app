<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset mail that links to the SPA rather than to an API route.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('passwords.mail.subject'))
            ->line(__('passwords.mail.intro'))
            ->action(__('passwords.mail.button'), $this->resetUrl($notifiable))
            ->line(__('passwords.mail.expires', ['count' => $minutes]))
            ->line(__('passwords.mail.ignore'));
    }

    private function resetUrl(object $notifiable): string
    {
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return rtrim((string) config('app.frontend_url'), '/')."/reset-password?{$query}";
    }
}
