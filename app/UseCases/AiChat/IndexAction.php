<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;

final class IndexAction
{
    public function __invoke(User $student): ?AiChatConversation
    {
        return $student->aiChatConversations()
            ->latest('last_message_at')
            ->latest('id')
            ->first();
    }
}
