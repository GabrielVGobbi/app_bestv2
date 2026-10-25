import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bug,
    CheckCircle2,
    Clock,
    Database,
    FileCode,
    Globe,
    List,
    Power,
    RefreshCw,
    Server,
    Settings,
    Shield,
    Trash2,
    Wrench,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    clearAllCaches,
    clearSpecificCache,
    index,
    optimize,
    toggleMaintenance,
    updateEnv,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';

interface SystemInfo {
    php_version: string;
    laravel_version: string;
    app_env: string;
    app_debug: boolean;
    cache_driver: string;
    queue_connection: string;
    database_connection: string;
    maintenance_mode: boolean;
    timezone: string;
    locale: string;
    telescope_enabled: boolean;
}

interface OperationalSettings {
    APP_NAME: string;
    APP_LOCALE: string;
    APP_FALLBACK_LOCALE: string;
    TELESCOPE_ENABLED: boolean;
}

interface AppRoute {
    method: string;
    uri: string;
    name: string;
    action: string;
    middleware: string[];
}

interface PageProps {
    maintenanceMode: boolean;
    operationalSettings: OperationalSettings;
    systemInfo: SystemInfo;
    routes: AppRoute[] | null;
}

type Tab = 'tools' | 'routes';

const METHOD_COLORS: Record<string, string> = {
    GET: 'bg-success-soft text-success-soft-foreground',
    POST: 'bg-info-soft text-info-soft-foreground',
    PUT: 'bg-warning-soft text-warning-soft-foreground',
    PATCH: 'bg-warning-soft text-warning-soft-foreground',
    DELETE: 'bg-destructive-soft text-destructive-soft-foreground',
    ANY: 'bg-primary-soft text-primary-soft-foreground',
};

export default function Tools({
    maintenanceMode,
    operationalSettings,
    systemInfo,
    routes,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Ferramentas de desenvolvimento',
                href: index['/admin/dev/dev-tools'],
            },
        ],
    });

    const [activeTab, setActiveTab] = useState<Tab>('tools');
    const [loading, setLoading] = useState<string | null>(null);
    const [routeSearch, setRouteSearch] = useState('');
    const [methodFilter, setMethodFilter] = useState<string>('ALL');

    const handleAction = (url: string, data?: Record<string, string>) => {
        setLoading(url);
        router.post(url, data ?? {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Ação executada com sucesso');
                setLoading(null);
                router.reload({
                    only: [
                        'maintenanceMode',
                        'operationalSettings',
                        'systemInfo',
                    ],
                });
            },
            onError: (errors) => {
                toast.error(
                    errors.value ?? errors.key ?? 'Erro ao executar ação',
                );
                setLoading(null);
            },
        });
    };

    const isLoading = (url: string) => loading === url;

    const filteredRoutes = useMemo(() => {
        if (!routes) {
            return [];
        }

        return routes.filter((r) => {
            const matchesSearch =
                routeSearch === '' ||
                r.uri.toLowerCase().includes(routeSearch.toLowerCase()) ||
                r.name.toLowerCase().includes(routeSearch.toLowerCase()) ||
                r.action.toLowerCase().includes(routeSearch.toLowerCase());
            const matchesMethod =
                methodFilter === 'ALL' || r.method.includes(methodFilter);

            return matchesSearch && matchesMethod;
        });
    }, [routes, routeSearch, methodFilter]);

    const availableMethods = useMemo(() => {
        if (!routes) {
            return [];
        }

        const methods = new Set<string>();
        routes.forEach((r) =>
            r.method.split('|').forEach((m) => methods.add(m)),
        );

        return ['ALL', ...Array.from(methods).sort()];
    }, [routes]);

    return (
        <>
            <Head title="Ferramentas de desenvolvimento" />

            <Header
                title="Ferramentas de desenvolvimento"
                subtitle="Manutenção, cache, variáveis operacionais e rotas do sistema"
            >
                {maintenanceMode && (
                    <Badge variant="warning" className="gap-1.5">
                        <AlertTriangle className="size-3" />
                        Manutenção ativa
                    </Badge>
                )}
            </Header>

            <Tabs
                value={activeTab}
                onValueChange={(value) => setActiveTab(value as Tab)}
                className="mx-auto w-full max-w-7xl space-y-6 px-4 pt-6 pb-12 lg:px-6"
            >
                {/* Tabs */}
                <TabsList aria-label="Seções das ferramentas de desenvolvimento">
                    <TabItem
                        value="tools"
                        icon={Settings}
                        label="Ferramentas"
                    />
                    <TabItem
                        value="routes"
                        icon={List}
                        label={
                            routes
                                ? `Rotas Laravel (${routes.length})`
                                : 'Rotas Laravel'
                        }
                    />
                </TabsList>

                {/* ── Tab: Dev Tools ───────────────────────────────── */}
                <TabPanel value="tools" className="space-y-6">
                    <>
                        {/* System Info */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Server className="size-4 text-muted-foreground" />
                                    Informações do sistema
                                </CardTitle>
                                <CardDescription>
                                    Status atual do ambiente e serviços
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                    <InfoItem
                                        label="PHP"
                                        icon={<FileCode className="size-3.5" />}
                                        value={systemInfo.php_version}
                                    />
                                    <InfoItem
                                        label="Laravel"
                                        icon={<Zap className="size-3.5" />}
                                        value={systemInfo.laravel_version}
                                    />
                                    <InfoItem
                                        label="Ambiente"
                                        icon={<Globe className="size-3.5" />}
                                        value={
                                            <Badge
                                                variant={
                                                    systemInfo.app_env ===
                                                    'production'
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                className="text-xs"
                                            >
                                                {systemInfo.app_env}
                                            </Badge>
                                        }
                                    />
                                    <InfoItem
                                        label="Debug"
                                        icon={<Bug className="size-3.5" />}
                                        value={
                                            <Badge
                                                variant={
                                                    systemInfo.app_debug
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                className="text-xs"
                                            >
                                                {systemInfo.app_debug
                                                    ? 'Ativo'
                                                    : 'Inativo'}
                                            </Badge>
                                        }
                                    />
                                    <InfoItem
                                        label="Cache Driver"
                                        icon={<Database className="size-3.5" />}
                                        value={systemInfo.cache_driver}
                                    />
                                    <InfoItem
                                        label="Queue"
                                        icon={
                                            <RefreshCw className="size-3.5" />
                                        }
                                        value={systemInfo.queue_connection}
                                    />
                                    <InfoItem
                                        label="Database"
                                        icon={<Database className="size-3.5" />}
                                        value={systemInfo.database_connection}
                                    />
                                    <InfoItem
                                        label="Timezone"
                                        icon={<Clock className="size-3.5" />}
                                        value={systemInfo.timezone}
                                    />
                                    <InfoItem
                                        label="Locale"
                                        icon={<Globe className="size-3.5" />}
                                        value={systemInfo.locale}
                                    />
                                    <InfoItem
                                        label="Telescope"
                                        icon={<Settings className="size-3.5" />}
                                        value={
                                            <Badge
                                                variant={
                                                    systemInfo.telescope_enabled
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="text-xs"
                                            >
                                                {systemInfo.telescope_enabled
                                                    ? 'Habilitado'
                                                    : 'Desabilitado'}
                                            </Badge>
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Modo de Manutenção */}
                            <Card
                                className={
                                    maintenanceMode
                                        ? 'border-warning-soft-foreground/40'
                                        : ''
                                }
                            >
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Wrench className="size-4 text-muted-foreground" />
                                        Modo de manutenção
                                    </CardTitle>
                                    <CardDescription>
                                        Ative ou desative o modo de manutenção
                                        do sistema
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="flex items-center gap-3">
                                            {maintenanceMode ? (
                                                <div className="flex size-9 items-center justify-center rounded-full bg-warning-soft">
                                                    <AlertTriangle className="size-4 text-warning-soft-foreground" />
                                                </div>
                                            ) : (
                                                <div className="flex size-9 items-center justify-center rounded-full bg-success-soft">
                                                    <CheckCircle2 className="size-4 text-success-soft-foreground" />
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {maintenanceMode
                                                        ? 'Manutenção ativa'
                                                        : 'Sistema online'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {maintenanceMode
                                                        ? 'O sistema está indisponível para usuários'
                                                        : 'O sistema está operacional'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant={
                                                maintenanceMode
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                handleAction(
                                                    toggleMaintenance.url(),
                                                )
                                            }
                                            disabled={loading !== null}
                                        >
                                            {isLoading(
                                                toggleMaintenance.url(),
                                            ) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Wrench className="size-3.5" />
                                            )}
                                            {maintenanceMode
                                                ? 'Restaurar'
                                                : 'Ativar'}
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Sua sessão mantém acesso para restaurar
                                        o sistema; outros visitantes verão uma
                                        página de indisponibilidade. Se perder o
                                        acesso, um operador pode executar{' '}
                                        <code>php artisan up</code> no servidor.
                                    </p>
                                </CardContent>
                            </Card>

                            {/* Telescope */}
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Settings className="size-4 text-muted-foreground" />
                                        Telescope
                                    </CardTitle>
                                    <CardDescription>
                                        Habilite ou desabilite o Telescope
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-center justify-between rounded-lg border p-3">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={`flex size-8 items-center justify-center rounded-full ${systemInfo.telescope_enabled ? 'bg-primary/10' : 'bg-muted'}`}
                                            >
                                                <Power
                                                    className={`size-4 ${systemInfo.telescope_enabled ? 'text-primary' : 'text-muted-foreground'}`}
                                                />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Status
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {operationalSettings.TELESCOPE_ENABLED
                                                        ? 'Habilitado'
                                                        : 'Desabilitado'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant={
                                                systemInfo.telescope_enabled
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            size="sm"
                                            onClick={() =>
                                                handleAction(updateEnv.url(), {
                                                    key: 'TELESCOPE_ENABLED',
                                                    value: operationalSettings.TELESCOPE_ENABLED
                                                        ? 'false'
                                                        : 'true',
                                                })
                                            }
                                            disabled={loading !== null}
                                        >
                                            {isLoading(updateEnv.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Power className="size-3.5" />
                                            )}
                                            {systemInfo.telescope_enabled
                                                ? 'Desabilitar'
                                                : 'Habilitar'}
                                        </Button>
                                    </div>

                                    <p className="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                        <strong>Nota:</strong> Alterações no
                                        Telescope requerem reinicialização para
                                        efeito completo.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Cache Management */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Database className="size-4 text-muted-foreground" />
                                    Gerenciamento de cache
                                </CardTitle>
                                <CardDescription>
                                    Limpe caches específicos ou otimize a
                                    aplicação
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="mb-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Limpeza individual
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {(
                                            [
                                                {
                                                    type: 'cache',
                                                    label: 'Cache',
                                                    icon: (
                                                        <Database className="size-3.5" />
                                                    ),
                                                },
                                                {
                                                    type: 'config',
                                                    label: 'Config',
                                                    icon: (
                                                        <Settings className="size-3.5" />
                                                    ),
                                                },
                                                {
                                                    type: 'route',
                                                    label: 'Rotas',
                                                    icon: (
                                                        <FileCode className="size-3.5" />
                                                    ),
                                                },
                                                {
                                                    type: 'view',
                                                    label: 'Views',
                                                    icon: (
                                                        <Trash2 className="size-3.5" />
                                                    ),
                                                },
                                            ] as const
                                        ).map(({ type, label, icon }) => (
                                            <Button
                                                key={type}
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleAction(
                                                        clearSpecificCache.url(),
                                                        { type },
                                                    )
                                                }
                                                disabled={loading !== null}
                                            >
                                                {isLoading(
                                                    clearSpecificCache.url(),
                                                ) ? (
                                                    <RefreshCw className="size-3.5 animate-spin" />
                                                ) : (
                                                    icon
                                                )}
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </div>

                                <Separator />

                                <div>
                                    <p className="mb-2.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Ações globais
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                handleAction(
                                                    clearAllCaches.url(),
                                                )
                                            }
                                            disabled={loading !== null}
                                        >
                                            {isLoading(clearAllCaches.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Trash2 className="size-3.5" />
                                            )}
                                            Limpar todos os caches
                                        </Button>
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={() =>
                                                handleAction(optimize.url())
                                            }
                                            disabled={loading !== null}
                                        >
                                            {isLoading(optimize.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Zap className="size-3.5" />
                                            )}
                                            Otimizar Aplicação
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Operational Settings */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Shield className="size-4 text-muted-foreground" />
                                    Configurações operacionais
                                </CardTitle>
                                <CardDescription>
                                    Nome e idiomas da aplicação. Se as
                                    configurações estiverem em cache, limpe o
                                    cache de configuração antes de salvar.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <OperationalSettingForm
                                    key={`APP_NAME:${operationalSettings.APP_NAME}`}
                                    settingKey="APP_NAME"
                                    label="Nome da aplicação"
                                    value={operationalSettings.APP_NAME}
                                    disabled={loading !== null}
                                />
                                <OperationalSettingForm
                                    key={`APP_LOCALE:${operationalSettings.APP_LOCALE}`}
                                    settingKey="APP_LOCALE"
                                    label="Idioma da aplicação"
                                    value={operationalSettings.APP_LOCALE}
                                    disabled={loading !== null}
                                    locales
                                />
                                <OperationalSettingForm
                                    key={`APP_FALLBACK_LOCALE:${operationalSettings.APP_FALLBACK_LOCALE}`}
                                    settingKey="APP_FALLBACK_LOCALE"
                                    label="Idioma alternativo"
                                    value={
                                        operationalSettings.APP_FALLBACK_LOCALE
                                    }
                                    disabled={loading !== null}
                                    locales
                                />
                            </CardContent>
                        </Card>
                    </>
                </TabPanel>

                {/* ── Tab: Rotas Laravel ───────────────────────────── */}
                <TabPanel value="routes">
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <List className="size-4 text-muted-foreground" />
                                        Rotas registradas
                                    </CardTitle>
                                    <CardDescription>
                                        {routes ? (
                                            <>
                                                {filteredRoutes.length} de{' '}
                                                {routes.length} rotas
                                            </>
                                        ) : (
                                            'Carregando rotas…'
                                        )}
                                    </CardDescription>
                                </div>

                                {/* Filters */}
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        placeholder="Buscar uri, nome, action…"
                                        value={routeSearch}
                                        onChange={(e) =>
                                            setRouteSearch(e.target.value)
                                        }
                                        className="h-8 w-56 text-sm"
                                    />
                                    <div className="flex gap-1">
                                        {availableMethods.map((m) => (
                                            <button
                                                key={m}
                                                onClick={() =>
                                                    setMethodFilter(m)
                                                }
                                                className={`rounded px-2 py-1 font-mono text-xs font-semibold transition-colors ${
                                                    methodFilter === m
                                                        ? 'bg-foreground text-background'
                                                        : 'text-muted-foreground hover:bg-muted'
                                                }`}
                                            >
                                                {m}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {!routes ? (
                                // Deferred loading skeleton
                                <div className="divide-y">
                                    {Array.from({ length: 8 }).map((_, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center gap-4 px-6 py-3"
                                        >
                                            <Skeleton className="h-5 w-14 rounded" />
                                            <Skeleton className="h-4 w-48 rounded" />
                                            <Skeleton className="h-4 w-32 rounded" />
                                        </div>
                                    ))}
                                </div>
                            ) : filteredRoutes.length === 0 ? (
                                <div className="py-12 text-center text-sm text-muted-foreground">
                                    Nenhuma rota encontrada.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/40">
                                                <th className="px-6 py-2.5 text-left font-medium text-muted-foreground">
                                                    Método
                                                </th>
                                                <th className="px-6 py-2.5 text-left font-medium text-muted-foreground">
                                                    URI
                                                </th>
                                                <th className="px-6 py-2.5 text-left font-medium text-muted-foreground">
                                                    Nome
                                                </th>
                                                <th className="px-6 py-2.5 text-left font-medium text-muted-foreground">
                                                    Ação
                                                </th>
                                                <th className="px-6 py-2.5 text-left font-medium text-muted-foreground">
                                                    Middleware
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {filteredRoutes.map((route, i) => (
                                                <tr
                                                    key={i}
                                                    className="transition-colors hover:bg-muted/30"
                                                >
                                                    <td className="px-6 py-2.5">
                                                        <div className="flex flex-wrap gap-1">
                                                            {route.method
                                                                .split('|')
                                                                .map((m) => (
                                                                    <span
                                                                        key={m}
                                                                        className={`inline-block rounded px-1.5 py-0.5 font-mono text-xs font-bold ${METHOD_COLORS[m] ?? METHOD_COLORS.ANY}`}
                                                                    >
                                                                        {m}
                                                                    </span>
                                                                ))}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-2.5">
                                                        <span className="font-mono text-xs">
                                                            {route.uri}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-2.5">
                                                        {route.name ? (
                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                {route.name}
                                                            </span>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground/40">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="max-w-xs px-6 py-2.5">
                                                        <span className="block truncate font-mono text-xs text-muted-foreground">
                                                            {route.action}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-2.5">
                                                        <div className="flex flex-wrap gap-1">
                                                            {route.middleware.map(
                                                                (m) => (
                                                                    <span
                                                                        key={m}
                                                                        className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                                                    >
                                                                        {m}
                                                                    </span>
                                                                ),
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabPanel>
            </Tabs>
        </>
    );
}

/* ── Componentes auxiliares ───────────────────────────────────────────────── */

function OperationalSettingForm({
    settingKey,
    label,
    value,
    disabled,
    locales = false,
}: {
    settingKey: 'APP_NAME' | 'APP_LOCALE' | 'APP_FALLBACK_LOCALE';
    label: string;
    value: string;
    disabled: boolean;
    locales?: boolean;
}) {
    const form = useForm({ key: settingKey, value });
    const id = `operational-${settingKey}`;
    const error = form.errors.value ?? form.errors.key;

    return (
        <form
            action={updateEnv.url()}
            method="post"
            className="space-y-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(updateEnv.url(), {
                    preserveScroll: true,
                    onSuccess: () => toast.success('Configuração atualizada'),
                });
            }}
        >
            <input type="hidden" name="key" value={settingKey} />
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-start gap-3">
                {locales ? (
                    <Select
                        name="value"
                        value={form.data.value}
                        onValueChange={(next) => {
                            form.setData('value', next);
                            form.clearErrors();
                        }}
                        disabled={disabled || form.processing}
                    >
                        <SelectTrigger
                            id={id}
                            className="w-full"
                            aria-invalid={Boolean(error)}
                            aria-describedby={error ? `${id}-error` : undefined}
                        />
                        <SelectContent>
                            <SelectItem index={0} value="en">
                                English
                            </SelectItem>
                            <SelectItem index={1} value="pt_BR">
                                Português (Brasil)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                ) : (
                    <Input
                        id={id}
                        name="value"
                        value={form.data.value}
                        required
                        maxLength={80}
                        disabled={disabled || form.processing}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? `${id}-error` : undefined}
                        onChange={(event) => {
                            form.setData('value', event.target.value);
                            form.clearErrors();
                        }}
                    />
                )}
                <Button
                    type="submit"
                    size="sm"
                    disabled={disabled || form.processing}
                >
                    {form.processing && (
                        <RefreshCw className="size-3.5 animate-spin" />
                    )}
                    Salvar
                </Button>
            </div>
            <InputError id={`${id}-error`} message={error} aria-live="polite" />
        </form>
    );
}

function InfoItem({
    label,
    icon,
    value,
}: {
    label: string;
    icon: React.ReactNode;
    value: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                {icon}
                {label}
            </div>
            <div className="text-sm font-medium">{value}</div>
        </div>
    );
}
