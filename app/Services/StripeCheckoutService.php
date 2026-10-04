<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function create(Payment $payment): Session
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException(
                'Stripeの秘密鍵が設定されていません。',
            );
        }

        $stripe = new StripeClient($secret);

        $successUrl = route('meeting-quota.checkout.success').'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('meeting-quota.checkout.select');

        return $stripe->checkout->sessions->create(
            [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $payment->currency,
                            'unit_amount' => $payment->amount,
                            'product_data' => [
                                'name' => $payment->meeting_pack_name,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'metadata' => ['payment_id' => $payment->id],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
            [
                'idempotency_key' => 'meeting-quota-'.$payment->id,
            ],
        );
    }
}
