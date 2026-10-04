<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 追加面談パックの購入記録。
 */
class Payment extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'meeting_pack_name',
        'quantity',
        'amount',
        'currency',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'integer',
        'status' => PaymentStatus::class,
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * 購入した受講生。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 購入対象となった面談パック。
     *
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }

    /**
     * 購入によって発生した面談回数の加算履歴。
     *
     * @return HasOne<MeetingQuotaTransaction, $this>
     */
    public function quotaTransaction(): HasOne
    {
        return $this->hasOne(MeetingQuotaTransaction::class, 'related_payment_id');
    }
}
