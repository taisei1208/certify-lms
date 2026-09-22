<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingReminderType;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification implements ShouldQueue
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
    public function __construct(public readonly Meeting $meeting,
        public readonly MeetingReminderType $reminderType, ) {}

    /**
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
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->greeting($notifiable->name.'さん')
            ->line($data['message'])
            ->action(
                '面談を確認する',
                $data['url'],
            )
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $scheduledAt = $this->meeting
            ->scheduled_at
            ->format('Y/m/d H:i');

        return [
            'notification_type' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'reminder_type' => $this->reminderType->value,
            'title' => $this->title(),
            'message' => "{$scheduledAt}から面談が予定されています。",
            'url' => route(
                'meetings.show',
                $this->meeting,
            ),
        ];
    }

    private function title(): string
    {
        return match ($this->reminderType) {
            MeetingReminderType::Eve => '明日の面談のお知らせ',

            MeetingReminderType::OneHourBefore => '1時間後の面談のお知らせ',
        };
    }
}
