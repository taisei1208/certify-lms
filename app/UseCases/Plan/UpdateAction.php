<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\UserStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;

/**
 * プランの基本情報を更新するユースケース。
 *
 * 公開状態は本処理では変更しない。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     duration_days: int,
     *     default_meeting_quota: int,
     *     sort_order?: int|null
     * } $data
     */
    public function __invoke(Plan $plan, User $admin, array $data): Plan
    {
        $hasActiveUsers = $plan->users()
            ->where('status', UserStatus::InProgress->value)
            ->exists();

        if ($hasActiveUsers) {
            throw PlanInvalidTransitionException::forUpdateUsers();
        }

        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_days' => $data['duration_days'],
            'default_meeting_quota' => $data['default_meeting_quota'],
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
