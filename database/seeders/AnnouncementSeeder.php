<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\UseCases\Announcement\ResolveRecipientsAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->first();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('id')
            ->get();

        if ($admin === null) {
            $this->command?->warn(
                'AnnouncementSeeder: 管理者が存在しません。',
            );

            return;
        }

        if ($students->isEmpty()) {
            $this->command?->warn(
                'AnnouncementSeeder: 受講中の受講生が存在しません。',
            );

            return;
        }

        $this->seedAllStudentsAnnouncement(
            $admin,
        );

        $this->seedCertificationAnnouncement(
            $admin,
        );

        $this->seedUserAnnouncement(
            $admin,
            $students->first(),
        );
    }

    private function seedAllStudentsAnnouncement(
        User $admin,
    ): void {
        $announcement = Announcement::query()->updateOrCreate(
            [
                'title' => 'システムメンテナンスのお知らせ',
            ],
            [
                'body' => <<<'TEXT'
システムメンテナンスのため、下記の時間帯はCertify LMSをご利用いただけません。

実施日時：9月20日 2:00〜4:00

ご不便をおかけしますが、よろしくお願いいたします。
TEXT,
                'target_type' => AnnouncementTargetType::AllStudents,
                'target_certification_id' => null,
                'target_user_id' => null,
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subDays(5),
            ],
        );

        $this->createNotifications($announcement);
    }

    private function seedCertificationAnnouncement(
        User $admin,
    ): void {
        $certification = Certification::query()
            ->whereHas(
                'enrollments',
                fn ($query) => $query
                    ->where(
                        'status',
                        EnrollmentStatus::Learning->value,
                    )
                    ->whereHas(
                        'user',
                        fn ($userQuery) => $userQuery
                            ->where(
                                'role',
                                UserRole::Student->value,
                            )
                            ->where(
                                'status',
                                UserStatus::InProgress->value,
                            ),
                    ),
            )
            ->orderBy('name')
            ->first();

        if ($certification === null) {
            $this->command?->warn(
                'AnnouncementSeeder: 配信対象となる資格が存在しません。',
            );

            return;
        }

        $announcement = Announcement::query()->updateOrCreate(
            [
                'title' => "{$certification->name} 教材更新のお知らせ",
            ],
            [
                'body' => "{$certification->name}の教材を更新しました。\n"
                    .'最新の内容をご確認ください。',
                'target_type' => AnnouncementTargetType::Certification,
                'target_certification_id' => $certification->id,
                'target_user_id' => null,
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subDays(3),
            ],
        );

        $this->createNotifications($announcement);
    }

    private function seedUserAnnouncement(
        User $admin,
        User $student,
    ): void {
        $announcement = Announcement::query()->updateOrCreate(
            [
                'title' => '学習状況についてのご連絡',
            ],
            [
                'body' => "{$student->name}さんの学習状況について、"
                    .'運営から個別にご連絡があります。'
                    ."\n詳細をご確認ください。",
                'target_type' => AnnouncementTargetType::User,
                'target_certification_id' => null,
                'target_user_id' => $student->id,
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subDay(),
            ],
        );

        $this->createNotifications($announcement);
    }

    private function createNotifications(
        Announcement $announcement,
    ): void {
        /** @var ResolveRecipientsAction $resolveRecipients */
        $resolveRecipients = app(
            ResolveRecipientsAction::class,
        );

        $recipients = $resolveRecipients($announcement);

        foreach ($recipients as $index => $recipient) {
            $this->createNotification(
                $announcement,
                $recipient,
                $index,
            );
        }

        $announcement->update([
            'dispatched_count' => $recipients->count(),
        ]);
    }

    private function createNotification(
        Announcement $announcement,
        User $recipient,
        int $index,
    ): void {
        $alreadyExists = $recipient->notifications()
            ->where(
                'type',
                AdminAnnouncementNotification::class,
            )
            ->where(
                'data->announcement_id',
                $announcement->id,
            )
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $notificationId = (string) Str::uuid();

        $recipient->notifications()->create([
            'id' => $notificationId,
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'notification_type' => 'admin_announcement',
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'message' => '運営からのお知らせが届きました。',
                'body' => $announcement->body,
                'url' => route(
                    'notifications.show',
                    $notificationId,
                ),
            ],
            // 既読・未読を混在させる
            'read_at' => $index % 2 === 0
                ? null
                : now()->subHours(2),
            'created_at' => $announcement->dispatched_at,
            'updated_at' => $announcement->dispatched_at,
        ]);
    }
}
