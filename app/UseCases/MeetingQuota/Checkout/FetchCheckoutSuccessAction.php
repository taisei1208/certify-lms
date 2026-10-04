<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Checkout;

use App\Models\Payment;
use App\Models\User;

/**
 * Stripe Checkoutから成功画面へ戻った受講生の購入記録を取得する。
 */
final class FetchCheckoutSuccessAction
{
    public function __invoke(User $student, ?string $checkoutSessionId): ?Payment
    {
        if ($checkoutSessionId === null) {
            return null;
        }

        return Payment::query()->where('user_id', $student->id)
            ->where(
                'stripe_checkout_session_id',
                $checkoutSessionId,
            )
            ->with('meetingPack')
            ->first();
    }
}
