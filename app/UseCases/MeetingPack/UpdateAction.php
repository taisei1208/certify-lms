<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックの基本情報を更新するユースケース。
 *
 * パック名・説明・面談回数・価格・Stripe Price ID・
 * 並び順を更新し、操作した管理者を最終更新者として記録する。
 * 公開状態は本処理では変更しない。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: string|null,
     *     meeting_count: int,
     *     price: int,
     *     stripe_price_id?: string|null,
     *     sort_order?: int|null
     * } $data
     */
    public function __invoke(MeetingPack $plan, User $admin, array $data): MeetingPack
    {
        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'meeting_count' => $data['meeting_count'],
            'price' => $data['price'],
            'stripe_price_id' => $data['stripe_price_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan->refresh();
    }
}
