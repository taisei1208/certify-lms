<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SettingsProfileSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('public');

        $image = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAw'
            .'CAAAAC0lEQVR42mP8/x8AAusB9Wl2nWQAAAAASUVORK5CYII=',
            true,
        );

        if ($image === false) {
            $this->command?->warn(
                'SettingsProfileSeeder: アバター画像を生成できませんでした。',
            );

            return;
        }

        $avatarPath = 'avatars/demo-coach.png';

        $disk->put($avatarPath, $image);

        /*
         * アバター設定済みユーザー。
         */
        User::query()
            ->where('email', 'coach@certify-lms.test')
            ->update(['avatar_url' => $disk->url($avatarPath)]);
    }
}
