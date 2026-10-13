<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password;

class EmployeeInvitation extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $notifiable->role === 'employee' && $notifiable->is_active
            && $notifiable->requires_password_setup && Password::tokenExists($notifiable, $this->token);
    }

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Set up your Attendify account')
            ->line('Your administrator has created an employee account for you.')
            ->line('Choose your password to securely access Attendify.')
            ->action('Set up your password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If it expires, request a new link from the sign-in page.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
