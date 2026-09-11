<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;

final class UpdateProfileAction
{
    /**
     * @param array{
     *     name: string,
     *     bio?: string|null,
     *     meeting_url?: string|null
     * } $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $attributes = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $attributes['meeting_url'] =
                $validated['meeting_url'] ?? null;
        }

        $user->update($attributes);

        return $user->refresh();
    }
}
