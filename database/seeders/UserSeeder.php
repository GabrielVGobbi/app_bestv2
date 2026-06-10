<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Developer — acesso total
        $developer = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Developer',
                'password' => bcrypt('superadmin'),
                'email_verified_at' => now(),
            ],
        );

        $developer->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['slug' => 'developer'], ['name' => 'Developer'])->id,
        ]);

        // Admin — acesso ao painel
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id,
        ]);

        // User — sem acesso ao painel (para testar restrições)
        $user = User::firstOrCreate(
            ['email' => 'user@test.com'],
            [
                'name' => 'User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['slug' => 'user'], ['name' => 'User'])->id,
        ]);
    }
}
