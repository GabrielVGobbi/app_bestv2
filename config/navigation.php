<?php

/*
|--------------------------------------------------------------------------
| Navigation Configuration
|--------------------------------------------------------------------------
|
| Fonte única de verdade para toda a navegação da aplicação.
|
| Módulos são agrupamentos temáticos de rotas. Cada módulo tem um
| route_prefixes para auto-detecção a partir da URL atual.
|
| Formatos de href:
|   - String literal:  '/dashboard'
|   - Named route:     ['route' => 'app.dashboard']
|   - Route c/ params: ['route' => 'users.show', 'params' => ['user' => 1]]
|
| Propriedades do item:
|   slug           — Identificador único (snake_case)
|   label          — Texto exibido
|   icon           — Nome do ícone Lucide (string)
|   type           — 'link' | 'group' | 'section'
|   href           — URL ou named route (apenas type='link')
|   order          — Ordem de exibição (menor = primeiro)
|   enabled        — false: remove completamente (não enviado ao frontend)
|   visible        — false: presente mas oculto
|   items          — Sub-itens (para type='group' ou type='section')
|
| Propriedades do módulo:
|   slug           — Identificador único
|   label          — Nome exibido
|   icon           — Ícone Lucide
|   default        — true: módulo padrão quando nenhum prefix bate
|   route_prefixes — Prefixos de rota para auto-detecção
|   enabled        — false: módulo oculto

*/

return [

    /*
    |----------------------------------------------------------------------
    | Navigation Style
    |----------------------------------------------------------------------
    |
    | 'modules' — sidebar com troca de módulos (drill-down, padrão)
    | 'flat'    — sidebar tradicional com todos os itens em lista única
    |
    */
    'style' => 'modules',

    /*
    |----------------------------------------------------------------------
    | Modules Menu
    |----------------------------------------------------------------------
    |
    | Quando style='modules', define onde o seletor/lista de módulos aparece.
    |
    | 'sidebar' — lista de módulos na sidebar, com drill-down para itens
    | 'header'  — lista de módulos no header; sidebar mostra itens do módulo ativo
    |
    */
    'modules_menu' => [
        'position' => 'header',
    ],

    /*
    |----------------------------------------------------------------------
    | Global — Itens fixos acima dos módulos (sempre visíveis na sidebar)
    |----------------------------------------------------------------------

    'global' => [
        [
            'slug'    => 'dashboard_global',
            'label'   => 'Dashboard',
            'icon'    => 'LayoutGrid',
            'type'    => 'link',
            'href'    => ['route' => 'app.dashboard'],
            'order'   => 1,
            'enabled' => true,
            'visible' => true,
        ],
    ],
*/
    /*
    |----------------------------------------------------------------------
    | Módulos
    |----------------------------------------------------------------------
    */
    'modules' => [

        /*
        | Gestão — Módulo principal (Plataforma e Cadastros)
        */
        [
            'slug' => 'gestao',
            'label' => 'Gestão',
            'icon' => 'LayoutGrid',
            'default' => true,
            'enabled' => true,
            'route_prefixes' => [
                '/dashboard',
                '/clients',
                '/suppliers',
                '/services',
                '/sales',
                '/playlist-tops',
                '/playlists',
            ],
            'default_href' => ['route' => 'app.dashboard'],
            'items' => [
                [
                    'slug' => 'gestao_plataforma_section',
                    'label' => 'Plataforma',
                    'type' => 'section',
                    'order' => 1,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'gestao_dashboard',
                            'label' => 'Dashboard',
                            'icon' => 'LayoutGrid',
                            'type' => 'link',
                            'href' => ['route' => 'app.dashboard'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
                [
                    'slug' => 'gestao_cadastros_section',
                    'label' => 'Cadastros',
                    'type' => 'section',
                    'order' => 10,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'gestao_clients',
                            'label' => 'Clientes',
                            'icon' => 'Contact',
                            'type' => 'link',
                            'href' => ['route' => 'clients.index'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_services',
                            'label' => 'Serviços',
                            'icon' => 'ShoppingBag',
                            'type' => 'link',
                            'href' => ['route' => 'services.index'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_sales',
                            'label' => 'Vendas',
                            'icon' => 'ReceiptText',
                            'type' => 'link',
                            'href' => ['route' => 'sales.index'],
                            'permissions' => ['sales.view', 'sales.view-own'],
                            'order' => 5,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_suppliers',
                            'label' => 'Fornecedores',
                            'icon' => 'Truck',
                            'type' => 'link',
                            'href' => ['route' => 'suppliers.index'],
                            'permissions' => ['suppliers.view'],
                            'order' => 3,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_playlists',
                            'label' => 'Playlists',
                            'icon' => 'Music2',
                            'type' => 'link',
                            'href' => ['route' => 'playlists.index'],
                            'permissions' => ['playlists.view'],
                            'order' => 7,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_playlist_delivery_tasks',
                            'label' => 'Fila de playlists',
                            'icon' => 'ListChecks',
                            'type' => 'link',
                            'href' => ['route' => 'playlists.delivery-tasks.index'],
                            'permissions' => ['playlist-delivery-tasks.view'],
                            'order' => 8,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'gestao_playlist_tops',
                            'label' => 'TOPs',
                            'icon' => 'BadgePercent',
                            'type' => 'link',
                            'href' => ['route' => 'playlist-tops.index'],
                            'order' => 9,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],

        [
            'slug' => 'financeiro',
            'label' => 'Financeiro',
            'icon' => 'CreditCard',
            'default' => false,
            'enabled' => true,
            'permissions' => [
                'finance.view',
                'finance.entries.view',
                'finance.reports.view',
                'finance.reconciliation.manage',
                'finance.settings.manage',
                'finance.accounts.view',
                'finance.categories.view',
                'finance.payment-methods.view',
            ],
            'route_prefixes' => [
                '/financial-entries',
                '/financial-reports',
                '/financial-reconciliation',
                '/financial-accounts',
                '/financial-categories',
                '/payment-methods',
            ],
            'default_href' => ['route' => 'financial-entries.index'],
            'items' => [
                [
                    'slug' => 'financeiro_operacoes_section',
                    'label' => 'Operacoes',
                    'type' => 'section',
                    'order' => 1,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'financeiro_financial_entries',
                            'label' => 'Lançamentos Financeiros',
                            'icon' => 'ReceiptText',
                            'type' => 'link',
                            'href' => ['route' => 'financial-entries.index'],
                            'permissions' => ['finance.entries.view'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'financeiro_financial_reports',
                            'label' => 'Relatórios',
                            'icon' => 'ChartNoAxesColumn',
                            'type' => 'link',
                            'href' => ['route' => 'financial-reports.index'],
                            'permissions' => ['finance.reports.view'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'financeiro_financial_reconciliation',
                            'label' => 'Conciliação',
                            'icon' => 'ListChecks',
                            'type' => 'link',
                            'href' => ['route' => 'financial-reconciliation.imports.index'],
                            'permissions' => ['finance.reconciliation.manage'],
                            'order' => 3,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
                [
                    'slug' => 'financeiro_configuracoes_section',
                    'label' => 'Configuracoes',
                    'type' => 'section',
                    'order' => 10,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'financeiro_financial_accounts',
                            'label' => 'Contas financeiras',
                            'icon' => 'Landmark',
                            'type' => 'link',
                            'href' => ['route' => 'financial-accounts.index'],
                            'permissions' => ['finance.accounts.view'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'financeiro_financial_categories',
                            'label' => 'Categorias financeiras',
                            'icon' => 'FolderTree',
                            'type' => 'link',
                            'href' => ['route' => 'financial-categories.index'],
                            'permissions' => ['finance.categories.view'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'financeiro_payment_methods',
                            'label' => 'Metodos de pagamento',
                            'icon' => 'CreditCard',
                            'type' => 'link',
                            'href' => ['route' => 'payment-methods.index'],
                            'permissions' => ['finance.payment-methods.view'],
                            'order' => 3,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],

        /*
        | Painel — Dashboard do painel

        [
            'slug' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'LayoutGrid',
            'default' => true,
            'enabled' => true,
            'route_prefixes' => [],
            'href' => '/dashboard',

            // 'items'          => [
            //    [
            //        'slug'    => 'dashboard',
            //        'label'   => 'Dashboard',
            //        'icon'    => 'LayoutGrid',
            //        'type'    => 'link',
            //        'order'   => 1,
            //        'enabled' => true,
            //        'visible' => true,
            //    ],
            // ],
        ],
*/
        /*
        | Admin — Administração da plataforma (ACL e Sistema)
        |
        | Estrutura derivada da matriz de roles/permissões em
        | .docs/implementations/bestplay-foundation/01-roles-and-permissions.md
        */
        [
            'slug' => 'admin',
            'label' => 'Admin',
            'icon' => 'ShieldCheck',
            'default' => false,
            'enabled' => true,
            'roles' => ['admin', 'super-admin'],
            'route_prefixes' => [
                '/users',
                '/roles',
                '/permissions',
                '/activity-log',
                '/exportacoes',
                '/email-logs',
                '/companies',
                '/admin/music-catalog',
                '/admin/backups',
            ],
            'default_href' => ['route' => 'users.index'],
            'items' => [
                [
                    'slug' => 'admin_acl_section',
                    'label' => 'ACL',
                    'type' => 'section',
                    'order' => 1,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'admin_usuarios',
                            'label' => 'Usuários',
                            'icon' => 'Users',
                            'type' => 'link',
                            'href' => ['route' => 'users.index'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_papeis',
                            'label' => 'Papéis',
                            'icon' => 'Shield',
                            'type' => 'link',
                            'href' => ['route' => 'roles.index'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_permissoes',
                            'label' => 'Permissões',
                            'icon' => 'KeyRound',
                            'type' => 'link',
                            'href' => ['route' => 'permissions.index'],
                            'order' => 3,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
                [
                    'slug' => 'admin_cadastros_section',
                    'label' => 'Cadastros',
                    'type' => 'section',
                    'order' => 5,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'admin_companies',
                            'label' => 'Empresas',
                            'icon' => 'Building2',
                            'type' => 'link',
                            'href' => ['route' => 'companies.index'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_music_catalog',
                            'label' => 'Catálogo musical',
                            'icon' => 'ListMusic',
                            'type' => 'link',
                            'href' => ['route' => 'playlists.music-catalog.index'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
                [
                    'slug' => 'admin_sistema_section',
                    'label' => 'Sistema',
                    'type' => 'section',
                    'order' => 10,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'admin_activity_log',
                            'label' => 'Activity Log',
                            'icon' => 'History',
                            'type' => 'link',
                            'href' => ['route' => 'activity-log.index'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_email_logs',
                            'label' => 'E-mails',
                            'icon' => 'Mail',
                            'type' => 'link',
                            'href' => ['route' => 'email-logs.index'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_exportacoes',
                            'label' => 'Exportações',
                            'icon' => 'Download',
                            'type' => 'link',
                            'href' => ['route' => 'exports.index'],
                            'order' => 3,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'admin_backups',
                            'label' => 'Backups',
                            'icon' => 'Archive',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.backups.index'],
                            'order' => 4,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],

        /*
        | External APIs — Playgrounds para integrações externas

        [
            'slug' => 'external_api',
            'label' => 'External APIs',
            'icon' => 'PlugZap',
            'default' => false,
            'enabled' => true,
            'route_prefixes' => ['/external-api'],
            'default_href' => ['route' => 'external-api.diagnostics'],
            'items' => [
                [
                    'slug' => 'external_api_diagnostics',
                    'label' => 'Diagnostico',
                    'icon' => 'Activity',
                    'type' => 'link',
                    'href' => ['route' => 'external-api.diagnostics'],
                    'order' => 1,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'spotify_artists',
                    'label' => 'Spotify Artistas',
                    'icon' => 'Mic2',
                    'type' => 'link',
                    'href' => ['route' => 'external-api.spotify.artists'],
                    'order' => 2,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'spotify_playlists',
                    'label' => 'Spotify Playlists',
                    'icon' => 'ListMusic',
                    'type' => 'link',
                    'href' => ['route' => 'external-api.spotify.playlists'],
                    'order' => 3,
                    'enabled' => true,
                    'visible' => true,
                ],
            ],
        ],
 */
        /*
        | Desenvolvimento — Ferramentas e componentes
        */
        [
            'slug' => 'desenvolvimento',
            'label' => 'Dev',
            'icon' => 'Zap',
            'default' => false,
            'enabled' => true,
            'roles' => ['developer'],
            'route_prefixes' => ['/admin/dev'],
            'default_href' => ['route' => 'admin.dev.dashboard'],
            'items' => [
                [
                    'slug' => 'dev_tools',
                    'label' => 'Dev Tools',
                    'icon' => 'Wrench',
                    'type' => 'link',
                    'href' => ['route' => 'admin.dev.dashboard'],
                    'order' => 1,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'seeder_generator',
                    'label' => 'Seeder Generator',
                    'icon' => 'DatabaseZap',
                    'type' => 'link',
                    'href' => ['route' => 'admin.dev.seeder-generator.index'],
                    'order' => 3,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'crud_generator',
                    'label' => 'CRUD Generator',
                    'icon' => 'TableProperties',
                    'type' => 'link',
                    'href' => ['route' => 'admin.dev.crud-generator.index'],
                    'order' => 4,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'file_attachments',
                    'label' => 'File Attachments',
                    'icon' => 'TableProperties',
                    'type' => 'link',
                    'href' => ['route' => 'admin.dev.components.file-attachments'],
                    'order' => 4,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'backups',
                    'label' => 'Backups',
                    'icon' => 'Archive',
                    'type' => 'link',
                    'href' => ['route' => 'admin.dev.backups.index'],
                    'order' => 5,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'dev_activity_log',
                    'label' => 'Activity Log',
                    'icon' => 'History',
                    'type' => 'link',
                    'href' => ['route' => 'activity-log.index'],
                    'order' => 6,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'dev_exportacoes',
                    'label' => 'Exportações',
                    'icon' => 'Download',
                    'type' => 'link',
                    'href' => ['route' => 'exports.index'],
                    'order' => 7,
                    'enabled' => true,
                    'visible' => true,
                ],
                [
                    'slug' => 'componentes',
                    'label' => 'Componentes',
                    'icon' => 'Puzzle',
                    'type' => 'group',
                    'href' => ['route' => 'admin.dev.components.index'],
                    'order' => 2,
                    'enabled' => true,
                    'visible' => true,
                    'items' => [
                        [
                            'slug' => 'componentes_indice',
                            'label' => 'Índice',
                            'icon' => 'LayoutGrid',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.index'],
                            'order' => 1,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'design_system',
                            'label' => 'Design System',
                            'icon' => 'Layers',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.design-system'],
                            'order' => 2,
                            'enabled' => true,
                            'visible' => true,
                        ],

                        [
                            'slug' => 'inline_cell_edit',
                            'label' => 'Inline Cell Edit',
                            'icon' => 'PencilLine',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.inline-cell-edit'],
                            'order' => 4,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'async_select',
                            'label' => 'Async Select',
                            'icon' => 'Search',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.async-select'],
                            'order' => 5,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'notifications_playground',
                            'label' => 'Notifications',
                            'icon' => 'BellRing',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.notifications'],
                            'order' => 6,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'activity_actions',
                            'label' => 'Activity Actions',
                            'icon' => 'History',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.activity-actions'],
                            'order' => 7,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'activity_timeline',
                            'label' => 'Activity Timeline',
                            'icon' => 'MessageSquare',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.activity-timeline'],
                            'order' => 8,
                            'enabled' => true,
                            'visible' => true,
                        ],
                        [
                            'slug' => 'toasts',
                            'label' => 'Toasts',
                            'icon' => 'Bell',
                            'type' => 'link',
                            'href' => ['route' => 'admin.dev.components.toasts'],
                            'order' => 9,
                            'enabled' => true,
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | Rodapé — Links externos (sempre visíveis)
    |----------------------------------------------------------------------
    */
    'footer' => [

        [
            'slug' => 'repository',
            'label' => 'Repository',
            'icon' => 'FolderGit2',
            'type' => 'link',
            'href' => 'https://github.com/laravel/react-starter-kit',
            'order' => 1,
            'enabled' => true,
            'visible' => true,
        ],

        [
            'slug' => 'documentation',
            'label' => 'Documentation',
            'icon' => 'BookOpen',
            'type' => 'link',
            'href' => 'https://laravel.com/docs/starter-kits#react',
            'order' => 2,
            'enabled' => true,
            'visible' => true,
        ],

    ],
];
