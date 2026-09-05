<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceiveNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly ChatMessage $message) {}

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
        $this->message->loadMissing('sender');

        return (new MailMessage)
            ->subject('新しいチャットメッセージがあります')
            ->greeting($notifiable->name.'さん')
            ->line($this->message->sender->name.'さんからメッセージが届きました。')
            ->line($this->message->body)
            ->action('チャットを確認する', route('chat.show', $this->message->chat_room_id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('sender');

        return [
            'notification_type' => 'chat_message_received',
            'title' => '新しいチャットメッセージ',
            'message' => $this->message->sender->name.'さんからメッセージが届きました。',
            'url' => route('chat.show', $this->message->chat_room_id),
        ];
    }
}
