<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 下書きの面談パックを公開するユースケース。
 *
 * 下書きから公開中への正規の状態遷移だけを許可し、
 * 操作した管理者を最終更新者として記録する。
 */
final class PublishAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 下書きの面談パックのみ公開可能
     */
    public function __invoke(MeetingPack $plan, User $admin): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Draft) {
            throw MeetingPackInvalidTransitionException::forPublish();
        }

        $plan->update([
            'status' => MeetingPackStatus::Published,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
