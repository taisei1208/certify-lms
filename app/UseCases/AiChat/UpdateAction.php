<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

final class UpdateAction
{
    public function __invoke(AiChatConversation $conversation, string $title): AiChatConversation
    {
        $conversation->update([
            'title' => $title,
            'auto_title_enabled' => false,
        ]);

        return $conversation->refresh();
    }
}
