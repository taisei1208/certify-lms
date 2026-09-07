<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生による個人学習目標登録リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $enrollment = $this->route('enrollment');

        return $user !== null && $user->can('create', [EnrollmentGoal::class, $enrollment]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'description' => '詳細',
            'target_date' => '目標期日',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_date.after_or_equal' => '目標期日には今日以降の日付を指定してください。',
        ];
    }
}
