<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_published_pack(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $published = MeetingPack::factory()->create([
            'name' => '公開3回パック',
            'status' => MeetingPackStatus::Published->value,
        ]);

        $draft = MeetingPack::factory()->create([
            'name' => '非公開3回パック',
            'status' => MeetingPackStatus::Draft->value,
        ]);

        $this->actingAs($student)->get('/meeting-quota/checkout')->assertOk()->assertSee($published->name)->assertDontSee($draft->name);
    }

    public function test_student_who_is_not_in_progress_cannot_access_checkout(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::Graduated->value,
        ]);

        $this->actingAs($student)
            ->get('/meeting-quota/checkout')
            ->assertForbidden();
    }

    public function test_unpublished_pack_cannot_be_purchased(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $pack = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Draft->value,
        ]);

        $this->actingAs($student)
            ->post('/meeting-quota/checkout', [
                'meeting_pack_id' => $pack->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_coach_cannot_access_checkout(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $this->actingAs($coach)
            ->get('/meeting-quota/checkout')
            ->assertForbidden();
    }

    public function test_checkout_session_is_created_and_pending_payment_is_saved(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $pack = MeetingPack::factory()->create([
            'name' => '3回パック',
            'meeting_count' => 3,
            'price' => 5000,
            'status' => MeetingPackStatus::Published->value,
        ]);

        $session = Session::constructFrom([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/test-session',
        ]);

        $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($session): void {
            $mock->shouldReceive('create')->once()->withArgs(
                function (Payment $payment): bool {
                    return
                        $payment->status === PaymentStatus::Pending
                        && $payment->meeting_pack_name === '3回パック'
                        && $payment->quantity === 3
                        && $payment->amount === 5000
                        && $payment->currency === 'jpy';
                },
            )
                ->andReturn($session);
        });

        $this->actingAs($student)
            ->post('/meeting-quota/checkout', [
                'meeting_pack_id' => $pack->id,
            ])
            ->assertRedirect(
                'https://checkout.stripe.com/test-session',
            );

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'meeting_pack_name' => '3回パック',
            'quantity' => 3,
            'amount' => 5000,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);

        // 面談回数はWebhook受信時に加算する
        $this->assertDatabaseCount(
            'meeting_quota_transactions',
            0,
        );
    }
}
