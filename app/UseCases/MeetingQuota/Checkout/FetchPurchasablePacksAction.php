<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Checkout;

use App\Models\MeetingPack;
use Illuminate\Database\Eloquent\Collection;

/**
 * 受講生が購入可能な公開中の面談パックを取得する。
 */
final class FetchPurchasablePacksAction
{
    /**
     * @return Collection<int, MeetingPack>
     */
    public function __invoke(): Collection
    {
        return MeetingPack::query()->published()->ordered()->get();
    }
}
