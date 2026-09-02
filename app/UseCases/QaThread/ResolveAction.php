<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;

/**
 * 質問スレッドを解決済みにする。
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return $thread->refresh();
    }
}
