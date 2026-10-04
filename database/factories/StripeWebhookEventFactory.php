<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StripeWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StripeWebhookEvent>
 */
class StripeWebhookEventFactory extends Factory
{
    protected $model = StripeWebhookEvent::class;

    public function definition(): array
    {
        return [
            'stripe_event_id' => 'evt_'.Str::lower(
                Str::random(24),
            ),
            'type' => 'checkout.session.completed',
            'processed_at' => now(),
        ];
    }

    public function checkoutCompleted(): static
    {
        return $this->state(fn () => [
            'type' => 'checkout.session.completed',
        ]);
    }

    public function asyncPaymentSucceeded(): static
    {
        return $this->state(fn () => [
            'type' => 'checkout.session.async_payment_succeeded',
        ]);
    }

    public function asyncPaymentFailed(): static
    {
        return $this->state(fn () => [
            'type' => 'checkout.session.async_payment_failed',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'type' => 'checkout.session.expired',
        ]);
    }
}
