<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentNote extends Model
{
    /** @use HasFactory<EnrollmentNoteFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'author_user_id',
        'body',
    ];

    /**
     * メモの対象となる受講登録。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * メモを作成したコーチまたは管理者。
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id')->withTrashed();
    }
}
