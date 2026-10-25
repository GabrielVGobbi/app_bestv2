import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Clock3,
    Database,
    PlugZap,
    RadioTower,
    ShieldAlert,
    Sparkles,
    XCircle,
} from 'lucide-react';

import diagnostics from '@/actions/App/Modules/ExternalApi/Http/Controllers/ExternalApiDiagnosticsController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ModuleInfo = {
    key: string;
    name: string;
    enabled: boolean;
    mode: 'fake' | 'real';
    provider: string | null;
    manager: string | null;
    configuration: string | null;
    http_client: string | null;
    mock_client: string | null;
    credentials: Record<string, boolean>;
    settings: {
        timeout: number;
        connect_timeout: number;
        retry_attempts: number;
        rate_limit: number;
        endpoints: Record<string, unknown>;
    };
    connections_count: number;
};

type RecentRequest = {
    id: string;
    module: string;
    mode: string;
    method: string;
    endpoint: string;
    status: string;
    status_code: number;
    duration_ms: number;
    cache_hit: boolean;
    retry_count: number;
    recorded_at: string;
    error?: string | null;
};

interface PageProps {
    modules: ModuleInfo[];
    recentRequests: RecentRequest[];
    global: {
        fake: boolean;
        cache: Record<string, unknown>;
        retry: Record<string, unknown>;
        rate_limit: Record<string, unknown>;
        logging: {
            enabled: boolean;
            channel: string;
        };
    };
}

function shortClass(value: string | null): string {
    if (!value) {
        return 'Não configurado';
    }

    return value.split('\\').pop() ?? value;
}

function StatusBadge({ status }: { status: string }) {
    if (status === 'success' || status === 'cache_hit') {
        return (
            <Badge variant="secondary" className="gap-1 rounded-full">
                <CheckCircle2 className="size-3" />
                {status === 'cache_hit' ? 'Cache' : 'Sucesso'}
            </Badge>
        );
    }

    if (status === 'rate_limited') {
        return (
            <Badge variant="outline" className="gap-1 rounded-full">
                <ShieldAlert className="size-3" />
                Rate limit
            </Badge>
        );
    }

    return (
        <Badge variant="destructive" className="gap-1 rounded-full">
            <XCircle className="size-3" />
            Falha
        </Badge>
    );
}

export default function ExternalApiDiagnostics({
    modules,
    recentRequests,
    global,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'ExternalApi Diagnóstico', href: diagnostics.url() },
        ],
    });

    const enabledModules = modules.filter((module) => module.enabled).length;
    const realModules = modules.filter(
        (module) => module.mode === 'real',
    ).length;

    return (
        <>
            <Head title="Diagnóstico das APIs externas" />

            <Header
                title="Diagnóstico das APIs externas"
                subtitle="Visão operacional dos módulos, configurações, cache, rate limit e requests recentes."
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={global.fake ? 'secondary' : 'default'}
                        className="h-8 rounded-full px-3"
                    >
                        <Sparkles className="size-3.5" />
                        {global.fake ? 'Fake global' : 'Real global'}
                    </Badge>
                    <Badge variant="outline" className="h-8 rounded-full px-3">
                        <RadioTower className="size-3.5" />
                        Log {global.logging.enabled ? 'ativo' : 'inativo'}
                    </Badge>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <Card className="rounded-lg">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex size-10 items-center justify-center rounded-md bg-muted">
                                    <PlugZap className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Modulos ativos
                                    </p>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {enabledModules}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="rounded-lg">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex size-10 items-center justify-center rounded-md bg-muted">
                                    <Activity className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Modulos em API real
                                    </p>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {realModules}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card className="rounded-lg">
                            <CardContent className="flex items-center gap-4 p-5">
                                <div className="flex size-10 items-center justify-center rounded-md bg-muted">
                                    <Database className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Requests recentes
                                    </p>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {recentRequests.length}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                        {modules.map((module) => (
                            <Card key={module.key} className="rounded-lg">
                                <CardHeader className="gap-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                {module.name}
                                            </CardTitle>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {module.key}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                module.mode === 'fake'
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                            className="rounded-full"
                                        >
                                            {module.mode === 'fake'
                                                ? 'Fake'
                                                : 'Real'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Status
                                            </p>
                                            <p className="font-medium">
                                                {module.enabled
                                                    ? 'Ativo'
                                                    : 'Inativo'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Conexoes OAuth
                                            </p>
                                            <p className="font-medium tabular-nums">
                                                {module.connections_count}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Timeout
                                            </p>
                                            <p className="font-medium tabular-nums">
                                                {module.settings.timeout}s
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Rate limit
                                            </p>
                                            <p className="font-medium tabular-nums">
                                                {module.settings.rate_limit}/min
                                            </p>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-xs text-muted-foreground">
                                            Classes
                                        </p>
                                        <div className="grid gap-1 font-mono text-xs">
                                            <span>
                                                {shortClass(module.provider)}
                                            </span>
                                            <span>
                                                {shortClass(module.manager)}
                                            </span>
                                            <span>
                                                {shortClass(module.http_client)}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5">
                                        {Object.entries(module.credentials).map(
                                            ([key, present]) => (
                                                <Badge
                                                    key={key}
                                                    variant={
                                                        present
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    className="rounded-full text-2xs"
                                                >
                                                    {key}:{' '}
                                                    {present ? 'ok' : 'vazio'}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Clock3 className="size-4" />
                                Requests recentes
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentRequests.length === 0 ? (
                                <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
                                    <RadioTower className="mb-3 size-9 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum request externo registrado ainda.
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-hidden rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                    Modulo
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                    Endpoint
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                    Tempo
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                    Retries
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentRequests.map(
                                                (request, index) => (
                                                    <tr
                                                        key={request.id}
                                                        className={
                                                            index % 2 === 0
                                                                ? 'bg-background'
                                                                : 'bg-muted/20'
                                                        }
                                                    >
                                                        <td className="px-4 py-3">
                                                            <StatusBadge
                                                                status={
                                                                    request.status
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        request.module
                                                                    }
                                                                </span>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="rounded-full text-2xs"
                                                                >
                                                                    {
                                                                        request.mode
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="min-w-0">
                                                                <p className="font-mono text-xs">
                                                                    {
                                                                        request.method
                                                                    }{' '}
                                                                    {
                                                                        request.endpoint
                                                                    }
                                                                </p>
                                                                {request.error && (
                                                                    <p className="mt-1 truncate text-xs text-destructive">
                                                                        {
                                                                            request.error
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 tabular-nums">
                                                            {
                                                                request.duration_ms
                                                            }
                                                            ms
                                                        </td>
                                                        <td className="px-4 py-3 tabular-nums">
                                                            {
                                                                request.retry_count
                                                            }
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
