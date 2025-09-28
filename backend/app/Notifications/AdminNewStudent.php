<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;

class AdminNewStudent extends Notification
{
    use Queueable;

    public function __construct(public User $student)
    {
    }

    public function via(object $notifiable): array
    {
        // Admins may prefer both channels; respect their own notify_* flags
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
                'subject' => 'New Student Registered',
                'student' => [
                    'id' => $this->student->id,
                    'name' => $this->student->name,
                    'email' => $this->student->email,
                ],
            ],
            'sent_at' => now(),
        ]);

        return (new MailMessage)
            ->subject('New Student Registered')
            ->greeting('Hello Admin')
            ->line('A new student has registered: '.$this->student->name.' ('.$this->student->email.')');
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'message' => 'New student registered: '.$this->student->name.' ('.$this->student->email.')',
        ];
    }
}
