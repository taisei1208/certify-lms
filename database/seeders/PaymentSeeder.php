<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談パック購入の開発用データを投入する。
 *
 * - 固定受講生に決済成功・処理中・失敗を投入
 * - デモ受講生にも購入記録を投入
 * - 成功済みPaymentだけ面談回数へ反映
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $meetingPacks = MeetingPack::query()->published()->ordered()->get();

        if ($meetingPacks->isEmpty()) {
            $this->command?->warn(
                'PaymentSeeder: 公開中の面談パックが存在しません。',
            );

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent === null) {
            $this->command?->warn(
                'PaymentSeeder: 固定受講生が存在しません。',
            );

            return;
        }

        $demoStudent = User::query()
            ->where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress)
            ->whereKeyNot($fixedStudent->id)
            ->orderBy('id')
            ->first();

        DB::transaction(function () use ($meetingPacks, $fixedStudent, $demoStudent): void {
            $firstPack = $meetingPacks->first();
            $secondPack = $meetingPacks->get(1) ?? $firstPack;
            $thirdPack = $meetingPacks->get(2) ?? $firstPack;

            $succeededPayment = $this->createPayment(
                user: $fixedStudent,
                meetingPack: $firstPack,
                status: PaymentStatus::Succeeded,
                sessionId: 'cs_test_seed_succeeded',
                paymentIntentId: 'pi_seed_succeeded',
                completedAt: now()->subDays(3),
            );

            /*
             * 成功済み購入だけ面談回数取引を作成する。
             */
            MeetingQuotaTransaction::query()->updateOrCreate(
                [
                    'related_payment_id' => $succeededPayment->id,
                ],
                [
                    'user_id' => $fixedStudent->id,
                    'type' => MeetingQuotaTransactionType::Purchased,
                    'amount' => $succeededPayment->quantity,
                    'granted_by_user_id' => null,
                    'related_meeting_id' => null,
                    'note' => $succeededPayment->meeting_pack_name.'の購入',
                    'occurred_at' => $succeededPayment->completed_at ?? now(),
                ],
            );

            /*
             * Pendingは残数へ反映しない。
             */
            $this->createPayment(
                user: $fixedStudent,
                meetingPack: $secondPack,
                status: PaymentStatus::Pending,
                sessionId: 'cs_test_seed_pending',
            );

            /*
             * Failedも残数へ反映しない。
             */
            $this->createPayment(
                user: $fixedStudent,
                meetingPack: $thirdPack,
                status: PaymentStatus::Failed,
                sessionId: 'cs_test_seed_failed',
                failedAt: now()->subDay(),
            );

            if ($demoStudent !== null) {
                $demoPayment = $this->createPayment(
                    user: $demoStudent,
                    meetingPack: $firstPack,
                    status: PaymentStatus::Succeeded,
                    sessionId: 'cs_test_seed_demo_succeeded',
                    paymentIntentId: 'pi_seed_demo_succeeded',
                    completedAt: now()->subDays(5),
                );

                MeetingQuotaTransaction::query()->updateOrCreate(
                    [
                        'related_payment_id' => $demoPayment->id,
                    ],
                    [
                        'user_id' => $demoStudent->id,
                        'type' => MeetingQuotaTransactionType::Purchased,
                        'amount' => $demoPayment->quantity,
                        'granted_by_user_id' => null,
                        'related_meeting_id' => null,
                        'note' => $demoPayment->meeting_pack_name.'の購入',
                        'occurred_at' => $demoPayment->completed_at ?? now(),
                    ],
                );
            }
        });
    }

    private function createPayment(User $user, MeetingPack $meetingPack, PaymentStatus $status, string $sessionId, ?string $paymentIntentId = null, mixed $completedAt = null, mixed $failedAt = null): Payment
    {
        return Payment::query()->updateOrCreate(
            [
                'stripe_checkout_session_id' => $sessionId,
            ],
            [
                'user_id' => $user->id,
                'meeting_pack_id' => $meetingPack->id,
                'meeting_pack_name' => $meetingPack->name,
                'quantity' => $meetingPack->meeting_count,
                'amount' => $meetingPack->price,
                'currency' => 'jpy',
                'status' => $status,
                'stripe_payment_intent_id' => $paymentIntentId,
                'completed_at' => $completedAt,
                'failed_at' => $failedAt,
            ],
        );
    }
}
