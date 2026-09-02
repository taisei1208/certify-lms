<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答投稿者による回答内容の変更リクエスト。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');
        $reply = $this->route('reply');

        return $reply->qa_thread_id === $thread->id
        && $this->user()?->can('update', $reply) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
