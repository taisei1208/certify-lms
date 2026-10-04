<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota\Checkout;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Checkout\Session;

/**
 * 署名検証済みのStripe Webhookを処理する。
 */
final class HandleCheckoutCompletedAction
{
    public function __invoke(Session $session, string $stripeEventId, string $eventType): void
    {
        DB::transaction(function () use ($session, $stripeEventId, $eventType): void {
            $inserted = StripeWebhookEvent::query()
                ->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'stripe_event_id' => $stripeEventId,
                    'type' => $eventType,
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($inserted === 0) {
                return;
            }

            $paymentId = $session->metadata->payment_id ?? null;

            if (! is_string($paymentId) || $paymentId === '') {
                throw new RuntimeException(
                    'Checkout Sessionにpayment_idがありません。',
                );
            }

            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

            if ($payment === null) {
                throw new RuntimeException(
                    '対応する購入記録が見つかりません。',
                );
            }

            if ($session->payment_status !== 'paid') {
                return;
            }

            if ($payment->status === PaymentStatus::Succeeded) {
                return;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new RuntimeException(
                    '購入記録が決済可能な状態ではありません。',
                );
            }

            if ((int) $session->amount_total !== $payment->amount) {
                throw new RuntimeException(
                    'Stripeの決済金額が購入記録と一致しません。',
                );
            }

            if (
                strtolower((string) $session->currency)
                !== strtolower($payment->currency)
            ) {
                throw new RuntimeException(
                    'Stripeの決済通貨が購入記録と一致しません。',
                );
            }

            $paymentIntentId = $session->payment_intent;

            if (is_object($paymentIntentId)) {
                $paymentIntentId = $paymentIntentId->id ?? null;
            }

            /*
             * 購入を成功状態へ変更する。
             */
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => is_string($paymentIntentId)
                        ? $paymentIntentId
                        : null,
                'completed_at' => now(),
                'failed_at' => null,
            ]);

            /*
             * 購入したパックの回数を面談回数台帳へ加算する。
             */
            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'note' => $payment->meeting_pack_name.'の購入',
                'occurred_at' => now(),
            ]);
        });
    }
}
