<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;

/**
 * 面談パックを物理削除するユースケース。
 *
 * 公開中の面談パックは削除を拒否し、
 * 削除可能な状態の面談パックのみ削除する。
 */
final class DestroyAction
{
    /**
     * @thorws MeetingPackInvalidTransitionException 公開中の面談パックは削除不可
     */
    public function __invoke(MeetingPack $plan): void
    {
        if ($plan->status === MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forDelete();
        }

        $plan->delete();
    }
}
