<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問スレッドを物理削除する。
 *
 * qa_replies.qa_thread_id の cascadeOnDelete により、
 * 配下の回答も同時に物理削除される。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        $thread->delete();
    }
}
