<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * 面談パックのマスタ一覧を取得するユースケース。
 *
 * パック名と公開状態による絞り込みを適用し、
 * 設定された並び順でページネーションした一覧を返す。
 */
final class IndexAction
{
    /**
     * @param array{
     *     keyword?: string|null,
     *     status?: string|null
     * } $filter
     *
     * @return LengthAwarePaginator<MeetingPack>
     */
    public function __invoke(array $filter): LengthAwarePaginator
    {
        return MeetingPack::query()
            ->when(
                $filter['keyword'] ?? null,
                fn (Builder $query, string $keyword) => $query
                    ->where('name', 'like', '%'.$keyword.'%'),
            )
            ->when(
                $filter['status'] ?? null,
                fn (Builder $query, string $status) => $query
                    ->where('status', $status),
            )
            ->ordered()
            ->paginate(20)
            ->withQueryString();
    }
}
