<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 公開中の面談パックをアーカイブするユースケース。
 *
 * 公開中からアーカイブへの正規の状態遷移だけを許可し、
 * 受講生の購入対象から除外する。
 */
final class ArchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 公開中の面談パックのみアーカイブ化可能
     */
    public function __invoke(MeetingPack $plan, User $admin): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forArchive();
        }

        $plan->update([
            'status' => MeetingPackStatus::Archived,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
