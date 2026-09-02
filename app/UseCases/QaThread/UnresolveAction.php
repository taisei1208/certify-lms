<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;

/**
 * 解決済みの質問スレッドを未解決へ戻す。
 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Open,
            'resolved_at' => null,
        ]);

        return $thread->refresh();
    }
}
