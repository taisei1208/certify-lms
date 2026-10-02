<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 通知ポップオーバーの表示タブを検証する。
 */
class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:all,unread'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tab' => 'タブ',
        ];
    }
}
