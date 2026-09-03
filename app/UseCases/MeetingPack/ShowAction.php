<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パックの詳細情報を取得するユースケース。
 *
 * 作成者・最終更新者など、詳細画面で必要な関連情報を取得する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        $plan->load(['createdBy', 'updatedBy']);

        return $plan;
    }
}
