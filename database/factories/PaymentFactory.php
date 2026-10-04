<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $quantity = fake()->randomElement([1, 3, 5, 10]);

        return [
            'user_id' => User::factory()->student()->inProgress(),
            'meeting_pack_id' => MeetingPack::factory()->published(),

            'meeting_pack_name' => "{$quantity} 回パック",
            'quantity' => $quantity,
            'amount' => $quantity * fake()->numberBetween(2500, 3500),
            'currency' => 'jpy',

            'status' => PaymentStatus::Pending->value,

            'stripe_checkout_session_id' => 'cs_test_'.Str::lower(
                Str::random(24),
            ),

            'stripe_payment_intent_id' => null,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }

    /**
     * 支払い処理待ち。
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Pending->value,
            'stripe_payment_intent_id' => null,
            'completed_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * 支払い完了。
     */
    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_'.Str::lower(
                Str::random(24),
            ),
            'completed_at' => now(),
            'failed_at' => null,
        ]);
    }

    /**
     * 支払い失敗。
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed->value,
            'completed_at' => null,
            'failed_at' => now(),
        ]);
    }

    /**
     * 返金済み。
     */
    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Refunded->value,
            'stripe_payment_intent_id' => 'pi_'.Str::lower(
                Str::random(24),
            ),
            'completed_at' => now()->subDay(),
            'failed_at' => null,
        ]);
    }

    /**
     * 実在する面談パックの購入時点情報を反映する。
     */
    public function forMeetingPack(MeetingPack $meetingPack): static
    {
        return $this
            ->for($meetingPack, 'meetingPack')
            ->state(fn () => [
                'meeting_pack_name' => $meetingPack->name,
                'quantity' => $meetingPack->meeting_count,
                'amount' => $meetingPack->price,
                'currency' => 'jpy',
            ]);
    }
}
