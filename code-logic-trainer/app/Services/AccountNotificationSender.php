<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountNotificationSender
{
    public function send(User $user, Notification $notification, string $purpose): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('Account notification could not be delivered.', [
                'user_id' => $user->getKey(),
                'purpose' => $purpose,
                'exception' => $exception::class,
            ]);
        }
    }
}
