<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Sales\Models\Sale;

return [
    'actions' => [
        'auth.login' => [
            'label' => 'Login',
            'log_name' => 'auth',
            'event' => 'login',
            'description' => ':causer fez login no sistema.',
            'icon' => 'LogIn',
            'severity' => 'info',
            'subject_type' => User::class,
            'enabled' => true,
        ],
        'auth.logout' => [
            'label' => 'Logout',
            'log_name' => 'auth',
            'event' => 'logout',
            'description' => ':causer saiu do sistema.',
            'icon' => 'LogOut',
            'severity' => 'muted',
            'subject_type' => User::class,
            'enabled' => true,
        ],
        'clients.viewed' => [
            'label' => 'Visualizou cliente',
            'log_name' => 'clients',
            'event' => 'viewed',
            'description' => ':causer visualizou o cliente :subject.',
            'icon' => 'Eye',
            'severity' => 'info',
            'subject_type' => Client::class,
            'enabled' => true,
        ],
        'clients.updated' => [
            'label' => 'Editou cliente',
            'log_name' => 'clients',
            'event' => 'updated',
            'description' => ':causer editou o cliente :subject.',
            'icon' => 'Pencil',
            'severity' => 'warning',
            'subject_type' => Client::class,
            'enabled' => true,
        ],
        'clients.index.viewed' => [
            'label' => 'Acessou clientes',
            'log_name' => 'clients',
            'event' => 'index_viewed',
            'description' => ':causer acessou a lista de clientes.',
            'icon' => 'TableProperties',
            'severity' => 'muted',
            'subject_type' => Client::class,
            'enabled' => false,
        ],
        'sales.item.updated' => [
            'label' => 'Alterou item da venda',
            'log_name' => 'sales',
            'event' => 'item_updated',
            'description' => ':causer alterou um item da venda :subject.',
            'icon' => 'Pencil',
            'severity' => 'warning',
            'subject_type' => Sale::class,
            'enabled' => true,
        ],
    ],
];
