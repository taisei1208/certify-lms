<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチのGoogle Calendar OAuth連携情報を管理するモデル。
 *
 * コーチごとのアクセストークン・リフレッシュトークン・有効期限を保持し、
 * Google Calendarの予定取得および面談イベントの登録・削除に使用する。
 */
class GoogleCalendarConnection extends Model
{
    use HasUlids;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
