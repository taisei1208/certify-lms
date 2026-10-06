<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AIチャット会話の認可を管理する。
 */
class AiChatConversationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $auth): bool
    {
        return $this->canUseAiChat($auth);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $auth, AiChatConversation $conversation): bool
    {
        return $this->ownsConversation($auth, $conversation);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $auth): bool
    {
        return $this->canUseAiChat($auth);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $auth, AiChatConversation $conversation): bool
    {
        return $this->ownsConversation($auth, $conversation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $auth, AiChatConversation $conversation): bool
    {
        return $this->ownsConversation($auth, $conversation);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function sendMessage(User $auth, AiChatConversation $conversation): bool
    {
        return $this->ownsConversation($auth, $conversation);
    }

    /**
     * AIチャット機能を利用できるか。
     */
    private function canUseAiChat(User $auth): bool
    {
        return (bool) config('ai-chat.enabled', false)
            && $auth->role === UserRole::Student
            && $auth->status === UserStatus::InProgress;
    }

    /**
     * 利用可能な受講生本人の会話か。
     */
    private function ownsConversation(User $auth, AiChatConversation $conversation): bool
    {
        return $this->canUseAiChat($auth) && $conversation->user_id === $auth->id;
    }
}
