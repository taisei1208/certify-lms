<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板の絞り込みリクエスト。keyword / status / certification_id の 3 種フィルタ。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'status' => ['nullable', 'string', 'in:unresolved,resolved'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'status' => '解決状態',
            'keyword' => 'キーワード',
            'page' => 'ページ番号',
        ];
    }
}
