<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * お知らせの配信対象となる受講生を取得する。
 */
final class ResolveRecipientsAction
{
    /**
     * @return Collection<int, User>
     */
    public function __invoke(Announcement $announcement): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => $query->get(),

            AnnouncementTargetType::Certification => $query
                ->whereHas('enrollments',
                    fn (Builder $enrollmentQuery) => $enrollmentQuery
                        ->where(
                            'certification_id',
                            $announcement->target_certification_id,
                        )
                        ->where(
                            'status',
                            EnrollmentStatus::Learning->value,
                        ),
                )
                ->get(),

            AnnouncementTargetType::User => $query
                ->where('id', $announcement->target_user_id)
                ->get(),
        };
    }
}
