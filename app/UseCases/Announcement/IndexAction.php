<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * お知らせの配信履歴を新着順で取得する。
 */
final class IndexAction
{
    /**
     * @return LengthAwarePaginator<Announcement>
     */
    public function __invoke(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with([
                'targetCertification',
                'targetUser',
                'createdBy',
            ])
            ->latest('dispatched_at')
            ->latest('id')
            ->paginate(20);
    }
}
