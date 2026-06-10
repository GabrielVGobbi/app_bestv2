<?php

use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;

return [
    /*
    |--------------------------------------------------------------------------
    | Super Role
    |--------------------------------------------------------------------------
    |
    | A role que tem acesso total automático a todas as permissões.
    | Users com esta role não precisam ter permissões específicas.
    |
    */
    'super_role' => env('PERMISSIONS_SUPER_ROLE', 'developer'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Classes dos models de Permission e Role.
    |
    */
    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Nomes das tabelas do banco de dados.
    |
    */
    'tables' => [
        'permissions' => 'permissions',
        'roles' => 'roles',
        'users_roles' => 'users_roles',
        'users_permissions' => 'users_permissions',
        'roles_permissions' => 'roles_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Ativar cache de permissões para melhorar performance.
    | Tempo em minutos.
    |
    */
    'cache' => [
        'enabled' => env('PERMISSIONS_CACHE_ENABLED', false),
        'ttl' => env('PERMISSIONS_CACHE_TTL', 60),
        'key' => 'permissions.cache',
    ],
];
