<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class UpdatePasswordAction
{
    public function __invoke(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);
    }
}
