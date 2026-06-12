<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->users() as $seedUser) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $seedUser['role_slug']],
                ['name' => $seedUser['role']],
            );

            $user = User::query()->updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => Hash::make($seedUser['password']),
                    'email_verified_at' => now(),
                    'must_change_password' => false,
                ],
            );

            $user->roles()->sync([$role->id]);
        }
    }

    /**
     * @return array<int, array{role: string, role_slug: string, name: string, email: string, password: string}>
     */
    private function users(): array
    {
        return config('local-login.users', []);
    }
}
