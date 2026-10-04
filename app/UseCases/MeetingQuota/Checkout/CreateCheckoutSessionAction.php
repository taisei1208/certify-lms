<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Checkout;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\MeetingPack\MeetingPackNotPurchasableException;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 追加面談パックの購入記録を作成し、
 * Stripe Checkout Sessionを発行する。
 */
final class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly StripeCheckoutService $stripe,
    ) {}

    public function __invoke(User $student, string $meetingPackId): string
    {
        $payment = DB::transaction(
            function () use ($student, $meetingPackId): Payment {
                $meetingPack = MeetingPack::query()->lockForUpdate()->findOrFail($meetingPackId);

                if ($meetingPack === null || $meetingPack->status !== MeetingPackStatus::Published
                ) {
                    throw MeetingPackNotPurchasableException::make();
                }

                return Payment::create([
                    'user_id' => $student->id,
                    'meeting_pack_id' => $meetingPack->id,

                    'meeting_pack_name' => $meetingPack->name,
                    'quantity' => $meetingPack->meeting_count,
                    'amount' => $meetingPack->price,
                    'currency' => 'jpy',

                    'status' => PaymentStatus::Pending,
                ]);
            },
        );

        try {
            $session = $this->stripe->create($payment);

            $payment->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            return $session->url;
        } catch (Throwable $exception) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
