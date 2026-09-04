<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * プランのマスタ一覧を取得するユースケース。
 *
 * プラン名と公開状態による絞り込みを適用し、
 * 契約中の受講者数を含めて並び順で返す。
 */
final class IndexAction
{
    /**
     * @param array{
     *     keyword?: string|null,
     *     status?: string|null
     * } $filter
     *
     * @return LengthAwarePaginator<Plan>
     */
    public function __invoke(array $filter): LengthAwarePaginator
    {
        return Plan::query()
            ->withCount('users')
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
