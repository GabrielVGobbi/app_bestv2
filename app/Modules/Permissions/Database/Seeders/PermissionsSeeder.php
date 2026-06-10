<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'access-painel'],
            [
                'name' => 'Acessar Painel',
                'group' => 'painel',
                'description' => 'Permite acessar o painel administrativo',
            ]
        );

        $roles = Role::query()->whereIn('slug', ['developer', 'super-admin', 'admin', 'user'])->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
