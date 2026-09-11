<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生メモ編集リクエスト。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $this->user()?->can('update', $note) ?? false;
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
