<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', false),

    'daily_limit' => 50,

    'history_limit' => 20,

    'auto_title_enabled' => true,

    'ai-chat.system_prompt' => 'あなたは資格学習を支援するAIアシスタントです。',

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'timeout' => 30,
    ],
];
