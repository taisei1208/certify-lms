<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingCanceledNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly Meeting $meeting) {}

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
        $this->meeting->loadMissing('canceledBy');

        return (new MailMessage)
            ->subject('面談がキャンセルされました')
            ->greeting($notifiable->name.'さん')
            ->line(
                $this->meeting->canceledBy->name
                .'さんが面談をキャンセルしました。',
            )
            ->line(
                '予定日時：'
                .$this->meeting->scheduled_at->format('Y/m/d H:i'),
            )
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->meeting->loadMissing('canceledBy');

        return [
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => $this->meeting->canceledBy->name
                .'さんが'
                .$this->meeting->scheduled_at->format('Y/m/d H:i')
                .'の面談をキャンセルしました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
