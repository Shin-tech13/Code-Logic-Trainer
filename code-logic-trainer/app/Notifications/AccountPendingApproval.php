<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountPendingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Code Logic Trainer account is pending approval')
            ->greeting('Welcome to Code Logic Trainer, '.$notifiable->name.'!')
            ->line('Your Code Logic Trainer login address is:')
            ->line($notifiable->email)
            ->line('Your account is pending approval. Approval may take about one day.')
            ->line('If you need access sooner, please contact your professor.')
            ->line('You can sign in once your account has been approved.');
    }
}
