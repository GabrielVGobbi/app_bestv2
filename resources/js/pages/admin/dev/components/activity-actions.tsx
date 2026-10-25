import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Eye,
    History,
    LogIn,
    LogOut,
    Pencil,
    RefreshCw,
    TableProperties,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    activityActions,
    components,
    seedActivityAction,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { httpPost } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import type {
    ActivityActionDefinition,
    ActivityLog,
} from '@/types/app/activity-log';

type ActivityActionCatalog = Record<string, ActivityActionDefinition>;

interface Props {
    activityActionCatalog: ActivityActionCatalog;
    recentLogs: ActivityLog[];
}

const ICONS: Record<string, LucideIcon> = {
    Activity,
    Eye,
    History,
    LogIn,
    LogOut,
    Pencil,
    TableProperties,
};

const SEVERITY_CLASS: Record<string, string> = {
    success:
        'border-success-soft-foreground/20 bg-success-soft text-success-soft-foreground',
    info: 'border-info-soft-foreground/20 bg-info-soft text-info-soft-foreground',
    warning:
        'border-warning-soft-foreground/20 bg-warning-soft text-warning-soft-foreground',
    danger: 'border-destructive-soft-foreground/20 bg-destructive-soft text-destructive-soft-foreground',
    muted: 'border-border bg-muted text-muted-foreground',
};

export default function ActivityActionsPlayground({
    activityActionCatalog,
    recentLogs,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Activity Actions', href: activityActions.url() },
        ],
    });

    const [loading, setLoading] = useState<string | null>(null);
    const actions = Object.values(activityActionCatalog);

    async function seed(actionKey: string): Promise<void> {
        setLoading(actionKey);

        try {
            await httpPost(seedActivityAction.url(), { action_key: actionKey });
            toast.success('Activity log criado.');
            router.reload({
                only: ['recentLogs'],
                onFinish: () => setLoading(null),
            });
        } catch {
            toast.error('Erro ao criar activity log.');
            setLoading(null);
        }
    }

    return (
        <>
            <Head title="Activity Actions playground" />

            <Header
                title="Activity Actions"
                subtitle="Playground para testar a camada configurável de auditoria baseada no activity_log."
            >
                <Badge variant="secondary" className="w-fit">
                    config/activity-actions.php
                </Badge>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <History className="size-4" />
                                    Catálogo central
                                </CardTitle>
                                <CardDescription>
                                    Labels, eventos, canais, icones e severidade
                                    saem do backend.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CheckCircle2 className="size-4" />
                                    Logs reais
                                </CardTitle>
                                <CardDescription>
                                    Os testes gravam em activity_log com
                                    properties.test=true.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <TableProperties className="size-4" />
                                    Relatorios
                                </CardTitle>
                                <CardDescription>
                                    Os filtros por usuário, data, canal, evento
                                    e action_key usam a mesma API da tela
                                    principal.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Ações configuradas</CardTitle>
                            <CardDescription>
                                Clique para emitir um log de teste com o seu
                                usuário como causador.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {actions.map((action) => {
                                    const Icon = ICONS[action.icon] ?? Activity;
                                    const severityClass =
                                        SEVERITY_CLASS[action.severity] ??
                                        SEVERITY_CLASS.muted;

                                    return (
                                        <div
                                            key={action.key}
                                            className="flex items-start justify-between gap-3 rounded-lg border p-4"
                                        >
                                            <div className="min-w-0 space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-xs font-medium ${severityClass}`}
                                                    >
                                                        <Icon className="size-3" />
                                                        {action.label}
                                                    </span>
                                                    {!action.enabled && (
                                                        <Badge variant="outline">
                                                            desligada
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {action.key}
                                                    </p>
                                                    <p className="mt-1 text-sm">
                                                        {action.description}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={loading !== null}
                                                onClick={() =>
                                                    void seed(action.key)
                                                }
                                            >
                                                {loading === action.key ? (
                                                    <RefreshCw className="size-3.5 animate-spin" />
                                                ) : (
                                                    <Activity className="size-3.5" />
                                                )}
                                                Emitir
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle>Logs de teste recentes</CardTitle>
                            <CardDescription>
                                Últimos registros com properties.test=true.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentLogs.length === 0 ? (
                                <div className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                                    Nenhum log de teste criado ainda.
                                </div>
                            ) : (
                                <div className="overflow-hidden rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="border-b bg-muted/40">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                                    Ação
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                                    Causador
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                                    Alvo
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                                                    Data
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {recentLogs.map((log) => (
                                                <tr key={log.id}>
                                                    <td className="px-3 py-2">
                                                        {log.action?.label ??
                                                            log.action_key ??
                                                            log.event}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {log.causer?.name ??
                                                            'Não disponível'}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {log.subject?.name ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {formatDate(
                                                            log.created_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
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
