<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックを新規作成するユースケース。
 *
 * 入力された基本情報を下書き状態で保存し、操作した管理者を作成者・最終更新者として記録する。
 */
final class StoreAction
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
    public function __invoke(User $admin, array $data): MeetingPack
    {
        return MeetingPack::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'meeting_count' => $data['meeting_count'],
            'price' => $data['price'],
            'stripe_price_id' => $data['stripe_price_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => MeetingPackStatus::Draft,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }
}
