<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    public function test_completed_checkout_grants_quota(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_123',
            'stripe_payment_intent_id' => null,
            'quantity' => 3,
            'amount' => 5000,
            'currency' => 'jpy',
        ]);

        $payload = $this->payload('evt_test_123', $payment);

        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 3,
        ]);
    }

    public function test_same_event_does_not_grant_quota_twice(): void
    {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_duplicate',
            'quantity' => 2,
            'amount' => 4000,
            'currency' => 'jpy',
        ]);

        $payload = $this->payload('evt_test_duplicate', $payment);

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('stripe_webhook_events', 1);

        $this->assertSame(
            1,
            $payment->quotaTransaction()->count(),
        );
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->withHeader(
            'Stripe-Signature',
            't='.time().',v1=invalid',
        )
            ->call(
                method: 'POST',
                uri: '/webhooks/stripe',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: '{}',
            )
            ->assertStatus(400);

        $this->assertDatabaseCount('stripe_webhook_events', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    private function payload(string $eventId, Payment $payment): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $payment->stripe_checkout_session_id,
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_123',
                    'amount_total' => $payment->amount,
                    'currency' => $payment->currency,
                    'metadata' => ['payment_id' => $payment->id],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(string $payload)
    {
        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            self::SECRET,
        );

        return $this->call(
            method: 'POST',
            uri: '/webhooks/stripe',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            content: $payload,
        );
    }
}
