<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class StoreAvatarAction
{
    public function __invoke(User $user, UploadedFile $avatar): User
    {
        $disk = Storage::disk('public');
        $oldAvatarPath = $this->localPath($user->avatar_url);

        $newAvatarPath = $disk->putFile('avatars', $avatar);

        if ($newAvatarPath === false) {
            throw new RuntimeException(
                'アイコン画像を保存できませんでした。',
            );
        }

        try {
            $user->update([
                'avatar_url' => $disk->url($newAvatarPath),
            ]);
        } catch (Throwable $exception) {
            /*
             * DB更新に失敗した場合、新しく保存した画像が
             *不要ファイルとして残らないように削除する。
             */
            $disk->delete($newAvatarPath);

            throw $exception;
        }

        /*
         * DBが新しい画像URLへ更新されたあとで、
         * 古い画像をストレージから削除する。
         */
        if ($oldAvatarPath !== null) {
            $disk->delete($oldAvatarPath);
        }

        return $user->refresh();
    }

    /**
     * 公開URLからpublic disk内のパスを取り出す。
     *
     * 例:
     * /storage/avatars/abc.jpg
     *     ↓
     * avatars/abc.jpg
     */
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
