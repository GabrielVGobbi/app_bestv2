<?php

namespace App\Modules\Permissions\Database\Seeders;

use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Developer', 'slug' => 'developer', 'description' => 'Acesso tecnico total em ambiente de desenvolvimento'],
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Acesso total de administracao da plataforma'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Gestao operacional ampla'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Usuario padrao'],
            ['name' => 'Financeiro', 'slug' => 'finance', 'description' => 'Receber, pagar, cobrancas, baixas e bancos'],
            ['name' => 'Gestor', 'slug' => 'manager', 'description' => 'Relatorios e acompanhamento comercial, operacional e financeiro'],
            ['name' => 'Comercial', 'slug' => 'commercial', 'description' => 'Gestao comercial e vendas para clientes'],
            ['name' => 'Operacao', 'slug' => 'operation', 'description' => 'Operacao de playlists, TOPs e vagas'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name'], 'description' => $role['description']]
            );
        }
    }
}
