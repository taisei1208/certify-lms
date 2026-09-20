<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Announcement $announcement
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting("{$notifiable->name}さん")
            ->line('運営からのお知らせが届きました。')
            ->line($this->announcement->body)
            ->action(
                'お知らせを確認する',
                route('notifications.show', $this->id),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => '運営からのお知らせが届きました。',
            'body' => $this->announcement->body,
            'url' => route('notifications.show', $this->id),
        ];
    }
}
