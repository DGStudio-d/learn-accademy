<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;

class NewMeeting extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting)
    {
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
                'subject' => 'New Meeting Scheduled',
                'meeting_id' => $this->meeting->id,
            ],
            'sent_at' => now(),
        ]);

        return (new MailMessage)
            ->subject('New Meeting Scheduled')
            ->greeting('Hello '.$notifiable->name)
            ->line('A new meeting has been scheduled for your program: '.$this->meeting->title)
            ->line('Starts at: '.$this->meeting->starts_at.' ('.$this->meeting->timezone.')')
            ->action('Join Meeting', $this->meeting->link)
            ->line('See you there!');
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'message' => 'New meeting: '.$this->meeting->title.' at '.$this->meeting->starts_at.' ('.$this->meeting->timezone.")\n".'Join: '.$this->meeting->link,
        ];
    }
}
