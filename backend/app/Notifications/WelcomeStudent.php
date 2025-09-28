<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;

class WelcomeStudent extends Notification
{
    use Queueable;

    protected array $context;

    public function __construct(array $context = [])
    {
        $this->context = $context;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->notify_email ?? false) {
            $channels[] = 'mail';
        }
        if ($notifiable->notify_whatsapp ?? false) {
            $channels[] = WhatsAppChannel::class;
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Log email notification
        NotificationLog::create([
            'user_id' => $notifiable->id ?? null,
            'type' => 'email',
            'payload' => [
                'subject' => 'Welcome to Learn Academy',
                'context' => $this->context,
            ],
            'sent_at' => now(),
        ]);

        return (new MailMessage)
            ->subject('Welcome to Learn Academy')
            ->greeting('Hello '.$notifiable->name)
            ->line('Your student account has been created successfully.')
            ->line('Selected language level is set. You can now access your programs and quizzes.')
            ->line('Thank you for joining us!');
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'message' => 'Welcome to Learn Academy! Your account is ready. '
                .'You can access your programs and quizzes now.',
        ];
    }
}
