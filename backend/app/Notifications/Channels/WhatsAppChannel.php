<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = method_exists($notifiable, 'routeNotificationForWhatsApp')
            ? $notifiable->routeNotificationForWhatsApp()
            : null;

        if (!$phone) {
            return; // No phone attached
        }

        $payload = $notification->toWhatsApp($notifiable);

        // Here you could integrate with a real WhatsApp provider API.
        // For now, we just record to NotificationLog for auditing.
        NotificationLog::create([
            'user_id' => $notifiable->id ?? null,
            'type' => 'whatsapp',
            'payload' => [
                'phone' => $phone,
                'message' => $payload['message'] ?? null,
                'data' => $payload,
            ],
            'sent_at' => now(),
        ]);
    }
}
