<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile_settings(): void
    {
        $this->get(route('settings.profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_student_can_view_profile_settings(): void
    {
        $student = User::factory()
            ->student()
            ->create();

        $this->actingAs($student)
            ->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertViewHas(
                'user',
                fn (User $user): bool => $user->is($student),
            );
    }

    public function test_coach_can_view_profile_settings(): void
    {
        $coach = User::factory()
            ->coach()
            ->create();

        $this->actingAs($coach)
            ->get(route('settings.profile.edit'))
            ->assertOk();
    }

    public function test_admin_can_view_profile_settings(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs($admin)
            ->get(route('settings.profile.edit'))
            ->assertOk();
    }

    public function test_graduated_student_can_view_profile_settings(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $this->actingAs($student)
            ->get(route('settings.profile.edit'))
            ->assertOk();
    }

    public function test_student_can_update_name_and_bio(): void
    {
        $student = User::factory()
            ->student()
            ->create([
                'name' => '変更前の氏名',
                'bio' => null,
            ]);

        $this->actingAs($student)
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => '変更後の氏名',
                    'bio' => '新しい自己紹介です。',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHas(
                'success',
                'プロフィールを更新しました。',
            );

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '変更後の氏名',
            'bio' => '新しい自己紹介です。',
        ]);
    }

    public function test_profile_email_cannot_be_updated(): void
    {
        $student = User::factory()
            ->student()
            ->create([
                'email' => 'before@example.com',
            ]);

        $this->actingAs($student)
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => $student->name,
                    'bio' => $student->bio,
                    'email' => 'after@example.com',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            );

        $this->assertSame(
            'before@example.com',
            $student->fresh()->email,
        );
    }

    public function test_student_cannot_update_meeting_url(): void
    {
        $student = User::factory()
            ->student()
            ->create([
                'meeting_url' => null,
            ]);

        $this->actingAs($student)
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => $student->name,
                    'bio' => $student->bio,
                    'meeting_url' => 'https://meet.google.com/invalid-update',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            );

        $this->assertNull(
            $student->fresh()->meeting_url,
        );
    }

    public function test_admin_cannot_update_meeting_url(): void
    {
        $admin = User::factory()
            ->admin()
            ->create([
                'meeting_url' => null,
            ]);

        $this->actingAs($admin)
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => $admin->name,
                    'bio' => $admin->bio,
                    'meeting_url' => 'https://meet.google.com/invalid-update',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            );

        $this->assertNull(
            $admin->fresh()->meeting_url,
        );
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()
            ->coach()
            ->create([
                'meeting_url' => 'https://meet.google.com/old-meeting',
            ]);

        $this->actingAs($coach)
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => $coach->name,
                    'bio' => $coach->bio,
                    'meeting_url' => 'https://zoom.us/j/123456789',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            );

        $this->assertSame(
            'https://zoom.us/j/123456789',
            $coach->fresh()->meeting_url,
        );
    }

    public function test_profile_requires_name(): void
    {
        $student = User::factory()
            ->student()
            ->create();

        $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => '',
                    'bio' => '自己紹介',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHasErrors('name');
    }

    public function test_profile_rejects_too_long_name_and_bio(): void
    {
        $student = User::factory()
            ->student()
            ->create();

        $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => str_repeat('あ', 51),
                    'bio' => str_repeat('あ', 1001),
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHasErrors([
                'name',
                'bio',
            ]);
    }

    public function test_coach_meeting_url_must_be_valid_url(): void
    {
        $coach = User::factory()
            ->coach()
            ->create();

        $this->actingAs($coach)
            ->from(route('settings.profile.edit'))
            ->patch(
                route('settings.profile.update'),
                [
                    'name' => $coach->name,
                    'bio' => $coach->bio,
                    'meeting_url' => 'invalid-url',
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHasErrors('meeting_url');
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->put(
                route('settings.password.update'),
                [
                    'current_password' => 'old-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ],
            )
            ->assertRedirect(
                route(
                    'settings.profile.edit',
                    ['tab' => 'password'],
                ),
            )
            ->assertSessionHas(
                'success',
                'パスワードを変更しました。',
            );

        $this->assertTrue(
            Hash::check(
                'new-password',
                $user->fresh()->password,
            ),
        );
    }

    public function test_password_cannot_be_updated_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->from(
                route(
                    'settings.profile.edit',
                    ['tab' => 'password'],
                ),
            )
            ->put(
                route('settings.password.update'),
                [
                    'current_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ],
            )
            ->assertRedirect(
                route(
                    'settings.profile.edit',
                    ['tab' => 'password'],
                ),
            )
            ->assertSessionHasErrors(
                ['current_password'],
                null,
                'updatePassword',
            );

        $this->assertTrue(
            Hash::check(
                'old-password',
                $user->fresh()->password,
            ),
        );
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->from(
                route(
                    'settings.profile.edit',
                    ['tab' => 'password'],
                ),
            )
            ->put(
                route('settings.password.update'),
                [
                    'current_password' => 'old-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'different-password',
                ],
            )
            ->assertRedirect(
                route(
                    'settings.profile.edit',
                    ['tab' => 'password'],
                ),
            )
            ->assertSessionHasErrors(
                ['password'],
                null,
                'updatePassword',
            );

        $this->assertTrue(
            Hash::check(
                'old-password',
                $user->fresh()->password,
            ),
        );
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => null,
        ]);

        $avatar = UploadedFile::fake()
            ->image('avatar.jpg', 300, 300)
            ->size(500);

        $this->actingAs($user)
            ->post(
                route('settings.avatar.store'),
                [
                    'avatar' => $avatar,
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHas(
                'success',
                'アイコン画像を更新しました。',
            );

        $user->refresh();

        $this->assertNotNull($user->avatar_url);

        $avatarPath = $this->storagePath(
            $user->avatar_url,
        );

        Storage::disk('public')
            ->assertExists($avatarPath);
    }

    public function test_uploading_new_avatar_deletes_old_avatar(): void
    {
        Storage::fake('public');

        $oldAvatarPath = 'avatars/old-avatar.jpg';

        Storage::disk('public')->put(
            $oldAvatarPath,
            'old avatar',
        );

        $user = User::factory()->create([
            'avatar_url' => Storage::disk('public')
                ->url($oldAvatarPath),
        ]);

        $newAvatar = UploadedFile::fake()
            ->image('new-avatar.png', 300, 300)
            ->size(500);

        $this->actingAs($user)
            ->post(
                route('settings.avatar.store'),
                [
                    'avatar' => $newAvatar,
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            );

        $user->refresh();

        Storage::disk('public')
            ->assertMissing($oldAvatarPath);

        Storage::disk('public')
            ->assertExists(
                $this->storagePath($user->avatar_url),
            );
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        $avatarPath = 'avatars/current-avatar.jpg';

        Storage::disk('public')->put(
            $avatarPath,
            'avatar',
        );

        $user = User::factory()->create([
            'avatar_url' => Storage::disk('public')
                ->url($avatarPath),
        ]);

        $this->actingAs($user)
            ->delete(
                route('settings.avatar.destroy'),
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHas(
                'success',
                'アイコン画像を削除しました。',
            );

        $this->assertNull(
            $user->fresh()->avatar_url,
        );

        Storage::disk('public')
            ->assertMissing($avatarPath);
    }

    public function test_avatar_rejects_unsupported_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'avatar.svg',
            100,
            'image/svg+xml',
        );

        $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->post(
                route('settings.avatar.store'),
                [
                    'avatar' => $file,
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHasErrors('avatar');
    }

    public function test_avatar_rejects_file_larger_than_two_megabytes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'avatar.jpg',
            2049,
            'image/jpeg',
        );

        $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->post(
                route('settings.avatar.store'),
                [
                    'avatar' => $file,
                ],
            )
            ->assertRedirect(
                route('settings.profile.edit'),
            )
            ->assertSessionHasErrors('avatar');
    }

    private function storagePath(string $avatarUrl): string
    {
        $urlPath = parse_url(
            $avatarUrl,
            PHP_URL_PATH,
        );

        $this->assertIsString($urlPath);

        return ltrim(
            str_replace('/storage/', '', $urlPath),
            '/',
        );
    }
}
