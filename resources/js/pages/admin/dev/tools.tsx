import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    AlertTriangle,
    Bug,
    CheckCircle2,
    Clock,
    Database,
    Eye,
    EyeOff,
    FileCode,
    Globe,
    List,
    Power,
    RefreshCw,
    Server,
    Settings,
    Shield,
    Terminal,
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
    toggleTelescope,
    toggleTelescopeCaptureAll,
    updateEnv,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    telescope_capture_all: boolean;
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
    envVariables: Record<string, string>;
    systemInfo: SystemInfo;
    routes: AppRoute[] | null;
}

type Tab = 'tools' | 'routes';

const METHOD_COLORS: Record<string, string> = {
    GET: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    POST: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    PUT: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    PATCH: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    DELETE: 'bg-red-500/10 text-red-600 dark:text-red-400',
    ANY: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
};

export default function Tools({ maintenanceMode, envVariables, systemInfo, routes }: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Dev Tools', href: index['/admin/dev/dev-tools'] }],
    });

    const [activeTab, setActiveTab] = useState<Tab>('tools');
    const [loading, setLoading] = useState<string | null>(null);
    const [showEnvValues, setShowEnvValues] = useState(false);
    const [envKey, setEnvKey] = useState('');
    const [envValue, setEnvValue] = useState('');
    const [routeSearch, setRouteSearch] = useState('');
    const [methodFilter, setMethodFilter] = useState<string>('ALL');

    const handleAction = (url: string, data?: Record<string, string>) => {
        setLoading(url);
        router.post(url, data ?? {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Ação executada com sucesso');
                setLoading(null);
                router.reload({ only: ['maintenanceMode', 'envVariables', 'systemInfo'] });
            },
            onError: () => {
                toast.error('Erro ao executar ação');
                setLoading(null);
            },
        });
    };

    const handleUpdateEnv = () => {
        if (!envKey || !envValue) {
            toast.error('Preencha a chave e o valor');
            return;
        }
        handleAction(updateEnv.url(), { key: envKey, value: envValue });
        setEnvKey('');
        setEnvValue('');
    };

    const isLoading = (url: string) => loading === url;

    const filteredRoutes = useMemo(() => {
        if (!routes) return [];
        return routes.filter((r) => {
            const matchesSearch =
                routeSearch === '' ||
                r.uri.toLowerCase().includes(routeSearch.toLowerCase()) ||
                r.name.toLowerCase().includes(routeSearch.toLowerCase()) ||
                r.action.toLowerCase().includes(routeSearch.toLowerCase());
            const matchesMethod = methodFilter === 'ALL' || r.method.includes(methodFilter);
            return matchesSearch && matchesMethod;
        });
    }, [routes, routeSearch, methodFilter]);

    const availableMethods = useMemo(() => {
        if (!routes) return [];
        const methods = new Set<string>();
        routes.forEach((r) => r.method.split('|').forEach((m) => methods.add(m)));
        return ['ALL', ...Array.from(methods).sort()];
    }, [routes]);

    return (
        <>
            <Head title="Dev Tools" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 pb-12 pt-6 lg:px-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <div className="flex size-12 items-center justify-center rounded-xl bg-linear-to-br from-violet-500/20 to-violet-500/5 ring-1 ring-violet-500/20">
                        <Terminal className="size-6 text-violet-500" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Dev Tools</h1>
                        <p className="text-muted-foreground text-sm">Ferramentas de desenvolvimento e manutenção do sistema</p>
                    </div>
                    {maintenanceMode && (
                        <Badge variant="destructive" className="ml-auto animate-pulse gap-1.5">
                            <AlertTriangle className="size-3" />
                            Manutenção Ativa
                        </Badge>
                    )}
                </div>

                {/* Tabs */}
                <div className="border-b">
                    <nav className="flex gap-1">
                        <TabButton active={activeTab === 'tools'} onClick={() => setActiveTab('tools')} icon={<Settings className="size-4" />}>
                            Dev Tools
                        </TabButton>
                        <TabButton active={activeTab === 'routes'} onClick={() => setActiveTab('routes')} icon={<List className="size-4" />}>
                            Rotas Laravel
                            {routes && (
                                <span className="bg-muted text-muted-foreground ml-1.5 rounded-full px-1.5 py-0.5 text-xs tabular-nums">
                                    {routes.length}
                                </span>
                            )}
                        </TabButton>
                    </nav>
                </div>

                {/* ── Tab: Dev Tools ───────────────────────────────── */}
                {activeTab === 'tools' && (
                    <>
                        {/* System Info */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Server className="text-muted-foreground size-4" />
                                    Informações do Sistema
                                </CardTitle>
                                <CardDescription>Status atual do ambiente e serviços</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                    <InfoItem label="PHP" icon={<FileCode className="size-3.5" />} value={systemInfo.php_version} />
                                    <InfoItem label="Laravel" icon={<Zap className="size-3.5" />} value={systemInfo.laravel_version} />
                                    <InfoItem
                                        label="Ambiente"
                                        icon={<Globe className="size-3.5" />}
                                        value={
                                            <Badge
                                                variant={systemInfo.app_env === 'production' ? 'destructive' : 'default'}
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
                                            <Badge variant={systemInfo.app_debug ? 'destructive' : 'secondary'} className="text-xs">
                                                {systemInfo.app_debug ? 'Ativo' : 'Inativo'}
                                            </Badge>
                                        }
                                    />
                                    <InfoItem label="Cache Driver" icon={<Database className="size-3.5" />} value={systemInfo.cache_driver} />
                                    <InfoItem label="Queue" icon={<RefreshCw className="size-3.5" />} value={systemInfo.queue_connection} />
                                    <InfoItem label="Database" icon={<Database className="size-3.5" />} value={systemInfo.database_connection} />
                                    <InfoItem label="Timezone" icon={<Clock className="size-3.5" />} value={systemInfo.timezone} />
                                    <InfoItem label="Locale" icon={<Globe className="size-3.5" />} value={systemInfo.locale} />
                                    <InfoItem
                                        label="Telescope"
                                        icon={<Settings className="size-3.5" />}
                                        value={
                                            <Badge variant={systemInfo.telescope_enabled ? 'default' : 'secondary'} className="text-xs">
                                                {systemInfo.telescope_enabled ? 'Habilitado' : 'Desabilitado'}
                                            </Badge>
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Modo de Manutenção */}
                            <Card className={maintenanceMode ? 'border-yellow-500/40 bg-yellow-500/5' : ''}>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Wrench className="text-muted-foreground size-4" />
                                        Modo de Manutenção
                                    </CardTitle>
                                    <CardDescription>Ative ou desative o modo de manutenção do sistema</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="flex items-center gap-3">
                                            {maintenanceMode ? (
                                                <div className="flex size-9 items-center justify-center rounded-full bg-yellow-500/15">
                                                    <AlertTriangle className="size-4 text-yellow-500" />
                                                </div>
                                            ) : (
                                                <div className="flex size-9 items-center justify-center rounded-full bg-green-500/15">
                                                    <CheckCircle2 className="size-4 text-green-500" />
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-sm font-medium">{maintenanceMode ? 'Manutenção Ativa' : 'Sistema Online'}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {maintenanceMode
                                                        ? 'O sistema está indisponível para usuários'
                                                        : 'O sistema está operacional'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant={maintenanceMode ? 'default' : 'destructive'}
                                            size="sm"
                                            onClick={() => handleAction(toggleMaintenance.url())}
                                            disabled={loading !== null}
                                        >
                                            {isLoading(toggleMaintenance.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Wrench className="size-3.5" />
                                            )}
                                            {maintenanceMode ? 'Restaurar' : 'Ativar'}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Telescope */}
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Settings className="text-muted-foreground size-4" />
                                        Telescope
                                    </CardTitle>
                                    <CardDescription>Controle o Telescope e suas configurações de captura</CardDescription>
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
                                                <p className="text-sm font-medium">Status</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {systemInfo.telescope_enabled ? 'Capturando dados' : 'Desabilitado'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant={systemInfo.telescope_enabled ? 'destructive' : 'default'}
                                            size="sm"
                                            onClick={() => handleAction(toggleTelescope.url())}
                                            disabled={loading !== null}
                                        >
                                            {isLoading(toggleTelescope.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Power className="size-3.5" />
                                            )}
                                            {systemInfo.telescope_enabled ? 'Desabilitar' : 'Habilitar'}
                                        </Button>
                                    </div>

                                    <div className="flex items-center justify-between rounded-lg border p-3">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-muted flex size-8 items-center justify-center rounded-full">
                                                <Database className="text-muted-foreground size-4" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">Modo de Captura</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {systemInfo.telescope_capture_all
                                                        ? 'Capturando todas as requisições'
                                                        : 'Apenas exceções e falhas'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant={systemInfo.telescope_capture_all ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => handleAction(toggleTelescopeCaptureAll.url())}
                                            disabled={loading !== null}
                                        >
                                            {isLoading(toggleTelescopeCaptureAll.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Power className="size-3.5" />
                                            )}
                                            {systemInfo.telescope_capture_all ? 'Modo Filtrado' : 'Capturar Tudo'}
                                        </Button>
                                    </div>

                                    <p className="bg-muted/50 text-muted-foreground rounded-md px-3 py-2 text-xs">
                                        <strong>Nota:</strong> Alterações no Telescope requerem reinicialização para efeito completo.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Cache Management */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Database className="text-muted-foreground size-4" />
                                    Gerenciamento de Cache
                                </CardTitle>
                                <CardDescription>Limpe caches específicos ou otimize a aplicação</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-muted-foreground mb-2.5 text-xs font-medium uppercase tracking-wide">Limpeza individual</p>
                                    <div className="flex flex-wrap gap-2">
                                        {(
                                            [
                                                { type: 'cache', label: 'Cache', icon: <Database className="size-3.5" /> },
                                                { type: 'config', label: 'Config', icon: <Settings className="size-3.5" /> },
                                                { type: 'route', label: 'Rotas', icon: <FileCode className="size-3.5" /> },
                                                { type: 'view', label: 'Views', icon: <Trash2 className="size-3.5" /> },
                                            ] as const
                                        ).map(({ type, label, icon }) => (
                                            <Button
                                                key={type}
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleAction(clearSpecificCache.url(), { type })}
                                                disabled={loading !== null}
                                            >
                                                {isLoading(clearSpecificCache.url()) ? (
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
                                    <p className="text-muted-foreground mb-2.5 text-xs font-medium uppercase tracking-wide">Ações globais</p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => handleAction(clearAllCaches.url())}
                                            disabled={loading !== null}
                                        >
                                            {isLoading(clearAllCaches.url()) ? (
                                                <RefreshCw className="size-3.5 animate-spin" />
                                            ) : (
                                                <Trash2 className="size-3.5" />
                                            )}
                                            Limpar Todos os Caches
                                        </Button>
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={() => handleAction(optimize.url())}
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

                        {/* Environment Variables */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Shield className="text-muted-foreground size-4" />
                                    Variáveis de Ambiente
                                </CardTitle>
                                <CardDescription>Visualize e atualize variáveis do arquivo .env</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <p className="text-muted-foreground text-sm">{Object.keys(envVariables).length} variáveis carregadas</p>
                                    <Button variant="ghost" size="sm" onClick={() => setShowEnvValues(!showEnvValues)}>
                                        {showEnvValues ? (
                                            <>
                                                <EyeOff className="size-3.5" />
                                                Ocultar valores
                                            </>
                                        ) : (
                                            <>
                                                <Eye className="size-3.5" />
                                                Mostrar valores
                                            </>
                                        )}
                                    </Button>
                                </div>

                                <div className="bg-muted/30 max-h-64 overflow-y-auto rounded-lg border">
                                    <div className="divide-y">
                                        {Object.entries(envVariables).map(([key, value]) => (
                                            <div
                                                key={key}
                                                className="hover:bg-muted/50 flex items-center justify-between px-4 py-2 transition-colors"
                                            >
                                                <span className="font-mono text-xs font-semibold">{key}</span>
                                                <span className="text-muted-foreground ml-4 max-w-xs truncate font-mono text-xs">
                                                    {showEnvValues ? value : '••••••••'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <p className="text-sm font-medium">Atualizar variável</p>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="env-key" className="text-xs">
                                                Chave
                                            </Label>
                                            <Input
                                                id="env-key"
                                                value={envKey}
                                                onChange={(e) => setEnvKey(e.target.value)}
                                                placeholder="APP_DEBUG"
                                                className="font-mono text-sm"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="env-value" className="text-xs">
                                                Valor
                                            </Label>
                                            <Input
                                                id="env-value"
                                                value={envValue}
                                                onChange={(e) => setEnvValue(e.target.value)}
                                                placeholder="true"
                                                className="font-mono text-sm"
                                            />
                                        </div>
                                    </div>
                                    <Button size="sm" onClick={handleUpdateEnv} disabled={loading !== null || !envKey || !envValue}>
                                        {isLoading(updateEnv.url()) ? (
                                            <RefreshCw className="size-3.5 animate-spin" />
                                        ) : (
                                            <Settings className="size-3.5" />
                                        )}
                                        Atualizar Variável
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                {/* ── Tab: Rotas Laravel ───────────────────────────── */}
                {activeTab === 'routes' && (
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <List className="text-muted-foreground size-4" />
                                        Rotas Registradas
                                    </CardTitle>
                                    <CardDescription>
                                        {routes ? (
                                            <>
                                                {filteredRoutes.length} de {routes.length} rotas
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
                                        onChange={(e) => setRouteSearch(e.target.value)}
                                        className="h-8 w-56 text-sm"
                                    />
                                    <div className="flex gap-1">
                                        {availableMethods.map((m) => (
                                            <button
                                                key={m}
                                                onClick={() => setMethodFilter(m)}
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
                                        <div key={i} className="flex items-center gap-4 px-6 py-3">
                                            <Skeleton className="h-5 w-14 rounded" />
                                            <Skeleton className="h-4 w-48 rounded" />
                                            <Skeleton className="h-4 w-32 rounded" />
                                        </div>
                                    ))}
                                </div>
                            ) : filteredRoutes.length === 0 ? (
                                <div className="text-muted-foreground py-12 text-center text-sm">Nenhuma rota encontrada.</div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="bg-muted/40 border-b">
                                                <th className="text-muted-foreground px-6 py-2.5 text-left font-medium">Método</th>
                                                <th className="text-muted-foreground px-6 py-2.5 text-left font-medium">URI</th>
                                                <th className="text-muted-foreground px-6 py-2.5 text-left font-medium">Nome</th>
                                                <th className="text-muted-foreground px-6 py-2.5 text-left font-medium">Action</th>
                                                <th className="text-muted-foreground px-6 py-2.5 text-left font-medium">Middleware</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {filteredRoutes.map((route, i) => (
                                                <tr key={i} className="hover:bg-muted/30 transition-colors">
                                                    <td className="px-6 py-2.5">
                                                        <div className="flex flex-wrap gap-1">
                                                            {route.method.split('|').map((m) => (
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
                                                        <span className="font-mono text-xs">{route.uri}</span>
                                                    </td>
                                                    <td className="px-6 py-2.5">
                                                        {route.name ? (
                                                            <span className="text-muted-foreground font-mono text-xs">{route.name}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground/40 text-xs">—</span>
                                                        )}
                                                    </td>
                                                    <td className="max-w-xs px-6 py-2.5">
                                                        <span className="text-muted-foreground block truncate font-mono text-xs">
                                                            {route.action}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-2.5">
                                                        <div className="flex flex-wrap gap-1">
                                                            {route.middleware.map((m) => (
                                                                <span
                                                                    key={m}
                                                                    className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-xs"
                                                                >
                                                                    {m}
                                                                </span>
                                                            ))}
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
                )}
            </div>
        </>
    );
}

/* ── Componentes auxiliares ───────────────────────────────────────────────── */

function TabButton({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                active
                    ? 'border-foreground text-foreground'
                    : 'text-muted-foreground hover:text-foreground border-transparent hover:border-border'
            }`}
        >
            {icon}
            {children}
        </button>
    );
}

function InfoItem({ label, icon, value }: { label: string; icon: React.ReactNode; value: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <div className="text-muted-foreground flex items-center gap-1 text-xs">
                {icon}
                {label}
            </div>
            <div className="text-sm font-medium">{value}</div>
        </div>
    );
}
