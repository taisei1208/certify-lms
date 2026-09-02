<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * 質問掲示板のスレッド一覧を取得するユースケース。
 *
 * 戻り値:
 * - `threads`：フィルタ適用済みの質問スレッド
 * - `certification_ids`：公開中の全資格ID
 */
final class IndexAction
{
    /**
     * @param array{certification_id?: string|null, status?: string|null,keyword?: string|null} $filter
     *
     * @return array{threads: LengthAwarePaginator,certifications: Collection<int, Certification>}
     */
    public function __invoke(User $viewer, array $filter, int $perPage = 20): array
    {
        $threads = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->when(
                $viewer->role !== UserRole::Admin,
                fn (Builder $query) => $query->whereHas(
                    'certification',
                    fn (Builder $certificationQuery) => $certificationQuery
                        ->published(),
                ),
            )
            ->when(
                $viewer->role === UserRole::Coach,
                fn (Builder $query) => $query->whereHas(
                    'certification',
                    fn (Builder $certificationQuery) => $certificationQuery
                        ->assignedTo($viewer),
                ),
            )
            ->when(
                $filter['certification_id'] ?? null,
                fn (Builder $query, string $certificationId) => $query
                    ->where('certification_id', $certificationId),
            )
            ->when(
                ($filter['status'] ?? null) === 'unresolved',
                fn (Builder $query) => $query->where('status', QaThreadStatus::Open->value),
            )
            ->when(
                ($filter['status'] ?? null) === 'resolved',
                fn (Builder $query) => $query->where('status', QaThreadStatus::Resolved->value),
            )
            ->when($filter['keyword'] ?? null,
                fn (Builder $query, string $keyword) => $query
                    ->where(function (Builder $keywordQuery) use ($keyword): void {
                        $keywordQuery
                            ->where('title', 'LIKE', '%'.$keyword.'%')
                            ->orWhere('body', 'LIKE', '%'.$keyword.'%');
                    }),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $certifications = Certification::query()
            ->when(
                $viewer->role !== UserRole::Admin,
                fn (Builder $query) => $query->published(),
            )
            ->when(
                $viewer->role === UserRole::Coach,
                fn (Builder $query) => $query->assignedTo($viewer),
            )
            ->orderBy('name')
            ->get();

        return [
            'threads' => $threads,
            'certifications' => $certifications,
        ];
    }
}
