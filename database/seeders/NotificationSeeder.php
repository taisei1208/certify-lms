<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ChatMessageReceiveNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 通知一覧の動作確認用データを投入する。
 *
 * - 固定受講生・固定コーチへ通知を投入
 * - 既読・未読を混在
 * - チャット・Q&A・面談予約・面談キャンセルを混在
 * - 1ユーザー24件作成し、ページネーションを確認可能にする
 *
 * メール送信を避けるためnotify()は使用せず、
 * notificationsテーブルへアプリ内通知を直接登録する。
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        if ($student === null || $coach === null) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生または固定コーチが存在しません。先にUserSeederを実行してください。',
            );

            return;
        }

        $this->createNotifications($student);
        $this->createNotifications($coach);
    }

    private function createNotifications(User $recipient): void
    {
        $definitions = $this->definitionsFor($recipient);

        for ($index = 0; $index < 24; $index++) {
            $definition = $definitions[$index % 4];

            $createdAt = now()->subHours($index);

            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $definition['class'],
                'data' => [
                    'notification_type' => $definition['type'],
                    'title' => $definition['title'],
                    'message' => $definition['message'],
                    'url' => $definition['url'],
                ],

                /*
                 * 3件に1件を既読にする。
                 * 未読・既読が一覧上で混在する。
                 */
                'read_at' => $index % 3 === 0
                    ? $createdAt->copy()->addMinutes(10)
                    : null,

                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function definitionsFor(User $recipient): array
    {
        $meetingIndexRoute = $recipient->role === UserRole::Coach
            ? 'coach.meetings.index'
            : 'meetings.index';

        return [
            [
                'class' => ChatMessageReceiveNotification::class,
                'type' => 'chat_message_received',
                'title' => '新しいチャットメッセージ',
                'message' => '新しいチャットメッセージが届きました。',
                'url' => route('chat.index'),
            ],
            [
                'class' => QaReplyReceivedNotification::class,
                'type' => 'qa_reply_received',
                'title' => '質問に回答が投稿されました',
                'message' => '質問掲示板に新しい回答が投稿されました。',
                'url' => route('qa-board.index'),
            ],
            [
                'class' => MeetingReservedNotification::class,
                'type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '新しい面談の予定が登録されました。',
                'url' => route($meetingIndexRoute),
            ],
            [
                'class' => MeetingCanceledNotification::class,
                'type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました',
                'message' => '予約されていた面談がキャンセルされました。',
                'url' => route($meetingIndexRoute),
            ],
        ];
    }
}
