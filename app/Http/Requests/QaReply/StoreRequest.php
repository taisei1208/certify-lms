<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板のスレッドに対する回答の投稿リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $thread = $this->route('thread');

        return $user !== null
            && $thread instanceof QaThread
            && $user->can(
                'create',
                [QaReply::class, $thread],
            );
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
