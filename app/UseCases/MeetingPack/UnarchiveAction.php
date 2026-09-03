<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * アーカイブされた面談パックを下書きに戻すユースケース。
 *
 * アーカイブから下書きへの正規の状態遷移だけを許可し、
 * 操作した管理者を最終更新者として記録する。
 */
final class UnarchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException アーカイブ中の面談パックのみ下書きに変更可能
     */
    public function __invoke(MeetingPack $plan, User $admin): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Archived) {
            throw MeetingPackInvalidTransitionException::forUnarchive();
        }

        $plan->update([
            'status' => MeetingPackStatus::Draft,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
