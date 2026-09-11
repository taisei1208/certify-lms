<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生メモ登録リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()?->can('create', [EnrollmentNote::class, $enrollment]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'メモ本文',
        ];
    }
}
