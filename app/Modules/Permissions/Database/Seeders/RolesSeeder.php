<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Developer', 'slug' => 'developer', 'description' => 'Acesso total (super role)'],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Administração completa'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administração padrão'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Usuário padrão'],
        ];

        foreach ($roles as $role) {
            Role::query()->firstOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'description' => $role['description']]
            );
        }
    }
}
