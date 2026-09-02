<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;

/**
 * 回答を削除する。
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        $reply->delete();
    }
}
