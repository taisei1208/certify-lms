<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DestroyAvatarAction
{
    public function __invoke(User $user): User
    {
        $avatarPath = $this->localPath(
            $user->avatar_url,
        );

        $user->update([
            'avatar_url' => null,
        ]);

        if ($avatarPath !== null) {
            Storage::disk('public')->delete(
                $avatarPath,
            );
        }

        return $user->refresh();
    }

    private function localPath(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null) {
            return null;
        }

        $urlPath = parse_url($avatarUrl, PHP_URL_PATH);

        if (
            ! is_string($urlPath)
            || ! str_starts_with(
                $urlPath,
                '/storage/avatars/',
            )
        ) {
            return null;
        }

        return Str::after($urlPath, '/storage/');
    }
}
