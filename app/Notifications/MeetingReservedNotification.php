<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReservedNotification extends Notification
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
        $this->meeting->loadMissing('student');

        return (new MailMessage)
            ->subject('面談が予約されました')
            ->greeting($notifiable->name.'さん')
            ->line(
                $this->meeting->student->name
                .'さんとの面談が予約されました。',
            )
            ->line(
                '日時：'
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
        $this->meeting->loadMissing('student');

        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談が予約されました',
            'message' => $this->meeting->student->name
                .'さんとの面談が'
                .$this->meeting->scheduled_at->format('Y/m/d H:i')
                .'に予約されました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
