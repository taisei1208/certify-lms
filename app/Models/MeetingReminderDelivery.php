<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingReminderType;
use Database\Factories\MeetingReminderDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談リマインダーの配信実績。
 *
 * 面談・受信者・リマインダー種別の組み合わせを一意に保持し、
 * 同じリマインダーが二重配信されることを防止する。
 */
class MeetingReminderDelivery extends Model
{
    /** @use HasFactory<MeetingReminderDeliveryFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'reminder_type',
        'delivered_at',
    ];

    protected $casts = [
        'reminder_type' => MeetingReminderType::class,
        'delivered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * リマインダーを受信したユーザー。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }
}
