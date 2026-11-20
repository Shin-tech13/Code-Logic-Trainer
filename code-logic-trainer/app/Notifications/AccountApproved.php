<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Code Logic Trainer account is approved')
            ->greeting('Your account is approved, '.$notifiable->name.'!')
            ->line('You can now sign in to Code Logic Trainer using this login address:')
            ->line($notifiable->email)
            ->action('Log in', route('login'));
    }
}
