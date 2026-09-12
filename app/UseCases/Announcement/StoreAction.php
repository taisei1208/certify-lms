<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

/**
 * お知らせを作成し、対象受講生へ配信する。
 */
final class StoreAction
{
    public function __construct(
        private readonly ResolveRecipientsAction $resolveRecipients,
    ) {}

    /**
     * @param array{
     *     title: string,
     *     body: string,
     *     target_type: string,
     *     target_certification_id?: string|null,
     *     target_user_id?: string|null
     * } $data
     */
    public function __invoke(User $admin, array $data): Announcement
    {
        $targetType = AnnouncementTargetType::from(
            $data['target_type'],
        );

        $announcement = Announcement::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'target_type' => $targetType,
            'target_certification_id' => (
                $targetType === AnnouncementTargetType::Certification
            )
                ? $data['target_certification_id']
                : null,
            'target_user_id' => (
                $targetType === AnnouncementTargetType::User
            )
                ? $data['target_user_id']
                : null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 0,
            'dispatched_at' => null,
        ]);

        $recipients = ($this->resolveRecipients)($announcement);

        Notification::send(
            $recipients,
            new AdminAnnouncementNotification($announcement),
        );

        $announcement->update([
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now(),
        ]);

        return $announcement->refresh();
    }
}
