<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly QaReply $reply) {}

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
        $this->reply->loadMissing(['user', 'thread']);

        return (new MailMessage)
            ->subject('質問に回答が投稿されました')
            ->greeting($notifiable->name.'さん')
            ->line(
                $this->reply->user->name
                .'さんが「'
                .$this->reply->thread->title
                .'」に回答しました。',
            )
            ->action(
                '回答を確認する',
                route('qa-board.show', $this->reply->thread),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->reply->loadMissing(['user', 'thread']);

        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が投稿されました',
            'message' => $this->reply->user->name
                .'さんが「'
                .$this->reply->thread->title
                .'」に回答しました。',
            'url' => route(
                'qa-board.show',
                $this->reply->thread,
            ),
        ];
    }
}
