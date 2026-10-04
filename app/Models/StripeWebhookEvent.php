<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 処理済みStripe Webhookイベントを記録する。
 */
class StripeWebhookEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
