<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;

/**
 * 投稿者本人による回答本文の更新。
 */
final class UpdateAction
{
    public function __invoke(QaReply $reply, string $body): QaReply
    {
        $reply->update([
            'body' => $body,
        ]);

        return $reply->refresh();
    }
}
