import { Head, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Activity,
    ArrowRight,
    CheckCircle,
    Clock,
    Eye,
    History,
    KeyRound,
    LogIn,
    LogOut,
    Pencil,
    Plus,
    ShieldCheck,
    ShieldOff,
    TableProperties,
    Trash2,
    User,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

import { index } from '@/actions/App/Http/Controllers/ActivityLogController';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
    DataTableFilterConfig,
    DataTableQuickFilter,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { apiRoutes } from '@/lib/api/api-routes';
import { formatDate } from '@/lib/utils';
import type {
    ActivityActionDefinition,
    ActivityLog,
} from '@/types/app/activity-log';

type ActivityActionCatalog = Record<string, ActivityActionDefinition>;
type Properties = Record<string, unknown>;

interface ActivityLogIndexProps {
    activityActionCatalog: ActivityActionCatalog;
}

const ICONS: Record<string, LucideIcon> = {
    Activity,
    CheckCircle,
    Eye,
    History,
    KeyRound,
    LogIn,
    LogOut,
    Pencil,
    Plus,
    ShieldCheck,
    ShieldOff,
    TableProperties,
    Trash2,
    User,
    XCircle,
};

const SEVERITY_CLASS: Record<string, string> = {
    success: 'border-emerald-200 bg-emerald-100 text-emerald-700',
    info: 'border-blue-200 bg-blue-100 text-blue-700',
    warning: 'border-amber-200 bg-amber-100 text-amber-700',
    danger: 'border-red-200 bg-red-100 text-red-700',
    muted: 'border-slate-200 bg-slate-100 text-slate-600',
};

const FALLBACK_ACTIONS: Record<string, ActivityActionDefinition> = {
    created: {
        key: 'model.created',
        label: 'Criado',
        log_name: 'default',
        event: 'created',
        description: 'Registro criado.',
        icon: 'Plus',
        severity: 'success',
        subject_type: null,
        enabled: true,
    },
    updated: {
        key: 'model.updated',
        label: 'Atualizado',
        log_name: 'default',
        event: 'updated',
        description: 'Registro atualizado.',
        icon: 'Pencil',
        severity: 'warning',
        subject_type: null,
        enabled: true,
    },
    deleted: {
        key: 'model.deleted',
        label: 'Removido',
        log_name: 'default',
        event: 'deleted',
        description: 'Registro removido.',
        icon: 'Trash2',
        severity: 'danger',
        subject_type: null,
        enabled: true,
    },
    approved: {
        key: 'workflow.approved',
        label: 'Aprovado',
        log_name: 'approved',
        event: 'approved',
        description: 'Registro aprovado.',
        icon: 'CheckCircle',
        severity: 'success',
        subject_type: null,
        enabled: true,
    },
    rejected: {
        key: 'workflow.rejected',
        label: 'Rejeitado',
        log_name: 'rejected',
        event: 'rejected',
        description: 'Registro rejeitado.',
        icon: 'XCircle',
        severity: 'danger',
        subject_type: null,
        enabled: true,
    },
    password_changed: {
        key: 'auth.password_changed',
        label: 'Senha alterada',
        log_name: 'default',
        event: 'password_changed',
        description: 'Senha alterada.',
        icon: 'ShieldCheck',
        severity: 'info',
        subject_type: null,
        enabled: true,
    },
};

function resolveAction(
    log: ActivityLog,
    catalog: ActivityActionCatalog,
): ActivityActionDefinition | null {
    if (log.action_key && catalog[log.action_key]) {
        return catalog[log.action_key];
    }

    if (log.action) {
        return log.action;
    }

    return log.event ? (FALLBACK_ACTIONS[log.event] ?? null) : null;
}

function ActionBadge({
    action,
    event,
}: {
    action: ActivityActionDefinition | null;
    event: string | null;
}) {
    if (!event && !action) {
        return <span className="text-xs text-muted-foreground">-</span>;
    }

    const Icon = ICONS[action?.icon ?? 'Activity'] ?? Activity;
    const className =
        SEVERITY_CLASS[action?.severity ?? 'muted'] ?? SEVERITY_CLASS.muted;

    return (
        <span
            className={`inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-xs font-medium ${className}`}
        >
            <Icon className="size-3" />
            {action?.label ?? event}
        </span>
    );
}

function LogNameBadge({ logName }: { logName: string }) {
    return (
        <span className="inline-flex items-center rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">
            {logName}
        </span>
    );
}

function CauserCell({ log }: { log: ActivityLog }) {
    if (!log.causer) {
        return <span className="text-xs text-muted-foreground">Sistema</span>;
    }

    const initials = log.causer.name
        .split(' ')
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

    return (
        <div className="flex items-center gap-2">
            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-semibold text-primary">
                {initials}
            </div>
            <div className="flex min-w-0 flex-col">
                <span className="truncate text-sm leading-tight font-medium">
                    {log.causer.name}
                </span>
                {log.causer.email && (
                    <span className="truncate text-xs leading-tight text-muted-foreground">
                        {log.causer.email}
                    </span>
                )}
            </div>
        </div>
    );
}

function SubjectCell({ log }: { log: ActivityLog }) {
    if (!log.subject_type) {
        return <span className="text-xs text-muted-foreground">-</span>;
    }

    return (
        <div className="flex flex-col">
            <span className="text-xs font-medium">{log.subject_type}</span>
            <span className="text-xs text-muted-foreground">
                {log.subject?.name ?? `#${log.subject_id}`}
            </span>
        </div>
    );
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '(vazio)';
    }

    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Nao';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function DiffTable({
    old: oldValues,
    attrs,
}: {
    old: Properties;
    attrs: Properties;
}) {
    const keys = Array.from(
        new Set([...Object.keys(oldValues), ...Object.keys(attrs)]),
    );

    return (
        <div className="overflow-hidden rounded-md border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/50">
                        <th className="w-1/4 px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                            Campo
                        </th>
                        <th className="w-[37%] px-3 py-2 text-left text-xs font-medium text-red-600">
                            Antes
                        </th>
                        <th className="w-6 px-1 py-2 text-center text-xs text-muted-foreground">
                            -
                        </th>
                        <th className="w-[37%] px-3 py-2 text-left text-xs font-medium text-emerald-600">
                            Depois
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {keys.map((key, index) => {
                        const before = oldValues[key];
                        const after = attrs[key];
                        const changed =
                            JSON.stringify(before) !== JSON.stringify(after);

                        return (
                            <tr
                                key={key}
                                className={`border-b last:border-0 ${
                                    changed
                                        ? 'bg-amber-50/60'
                                        : index % 2 === 0
                                          ? 'bg-muted/20'
                                          : ''
                                }`}
                            >
                                <td className="px-3 py-2 align-top font-mono text-xs text-muted-foreground">
                                    {key}
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <span
                                        className={`font-mono text-xs break-all ${
                                            changed
                                                ? 'text-red-700'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {formatValue(before)}
                                    </span>
                                </td>
                                <td className="px-1 py-2 text-center align-middle">
                                    {changed && (
                                        <ArrowRight className="mx-auto size-3 text-amber-500" />
                                    )}
                                </td>
                                <td className="px-3 py-2 align-top">
                                    <span
                                        className={`font-mono text-xs break-all ${
                                            changed
                                                ? 'font-semibold text-emerald-700'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {formatValue(after)}
                                    </span>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function AttributesTable({ label, data }: { label: string; data: Properties }) {
    const entries = Object.entries(data);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div>
            <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                {label}
            </p>
            <div className="overflow-hidden rounded-md border">
                <table className="w-full">
                    <tbody>
                        {entries.map(([key, value], index) => (
                            <tr
                                key={key}
                                className={`border-b last:border-0 ${
                                    index % 2 === 0 ? 'bg-muted/20' : ''
                                }`}
                            >
                                <td className="w-1/3 px-3 py-2 font-mono text-xs text-muted-foreground">
                                    {key}
                                </td>
                                <td className="px-3 py-2 font-mono text-xs break-all">
                                    {formatValue(value)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function PropertiesSection({ log }: { log: ActivityLog }) {
    const props = log.properties;

    if (!props || Object.keys(props).length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nenhum dado adicional registrado.
            </p>
        );
    }

    const old = props.old as Properties | undefined;
    const attrs = props.attributes as Properties | undefined;

    if (old && attrs) {
        return <DiffTable old={old} attrs={attrs} />;
    }

    if (attrs && !old) {
        return <AttributesTable label="Valores criados" data={attrs} />;
    }

    if (!attrs && old) {
        return <AttributesTable label="Valores ao excluir" data={old} />;
    }

    return <AttributesTable label="Dados do evento" data={props} />;
}

function ActivityLogDetailSheet({
    log,
    action,
    open,
    onClose,
}: {
    log: ActivityLog | null;
    action: ActivityActionDefinition | null;
    open: boolean;
    onClose: () => void;
}) {
    if (!log) {
        return null;
    }

    return (
        <Sheet open={open} onOpenChange={(value) => !value && onClose()}>
            <SheetContent
                className="flex w-full flex-col sm:max-w-lg"
                side="right"
            >
                <SheetHeader className="shrink-0 pb-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <ActionBadge action={action} event={log.event} />
                        <LogNameBadge logName={log.log_name} />
                    </div>
                    <SheetTitle className="mt-1 text-base leading-snug">
                        {log.description}
                    </SheetTitle>
                    <SheetDescription className="flex items-center gap-1.5 text-xs">
                        <Clock className="size-3.5" />
                        {formatDate(log.created_at)}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto">
                    <div className="space-y-5 px-4 py-3">
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                Causador
                            </p>
                            <CauserCell log={log} />
                        </div>

                        {log.subject_type && (
                            <div>
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Alvo
                                </p>
                                <div className="flex items-center gap-3 rounded-md border p-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <User className="size-4" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">
                                            {log.subject?.name ??
                                                `#${log.subject_id}`}
                                        </p>
                                        {log.subject?.email && (
                                            <p className="text-xs text-muted-foreground">
                                                {log.subject.email}
                                            </p>
                                        )}
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="shrink-0 text-xs"
                                    >
                                        {log.subject_type}
                                    </Badge>
                                </div>
                            </div>
                        )}

                        <Separator />
                        <PropertiesSection log={log} />
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function buildColumns(
    catalog: ActivityActionCatalog,
): ColumnDef<ActivityLog>[] {
    return [
        {
            id: 'action_key',
            accessorKey: 'action_key',
            header: 'Acao',
            size: 180,
            cell: ({ row }) => {
                const action = resolveAction(row.original, catalog);

                return (
                    <ActionBadge action={action} event={row.original.event} />
                );
            },
            meta: { label: 'Acao' },
        },
        {
            id: 'log_name',
            accessorKey: 'log_name',
            header: 'Canal',
            size: 110,
            cell: ({ row }) => <LogNameBadge logName={row.original.log_name} />,
            meta: { label: 'Canal' },
        },
        {
            id: 'causer',
            header: 'Causador',
            size: 220,
            cell: ({ row }) => <CauserCell log={row.original} />,
            meta: { label: 'Causador' },
        },
        {
            id: 'subject',
            header: 'Alvo',
            size: 180,
            cell: ({ row }) => <SubjectCell log={row.original} />,
            meta: { label: 'Alvo' },
        },
        {
            id: 'description',
            accessorKey: 'description',
            header: 'Descrição',
            cell: ({ row }) => (
                <span className="text-sm">
                    {row.original.description || '-'}
                </span>
            ),
            meta: { label: 'Descrição' },
        },
        {
            id: 'created_at',
            accessorKey: 'created_at',
            header: 'Data',
            size: 160,
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.original.created_at)}
                </span>
            ),
            meta: { label: 'Data', type: 'date' },
        },
    ];
}

const filters: DataTableFilterConfig[] = [
    {
        id: 'causer_name',
        label: 'Causador',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por nome ou email',
    },
];

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Data do Evento' },
];

function buildQuickFilters(
    catalog: ActivityActionCatalog,
): DataTableQuickFilter[] {
    const actions = Object.values(catalog).filter((action) => action.enabled);
    const logs = Array.from(new Set(actions.map((action) => action.log_name)));
    const subjectTypes = Array.from(
        new Set(
            actions
                .map((action) => action.subject_type)
                .filter((subjectType): subjectType is string =>
                    Boolean(subjectType),
                ),
        ),
    );

    return [
        {
            id: 'action_key',
            label: 'Acao',
            placeholder: 'Acao',
            icon: Activity,
            options: actions.map((action) => ({
                label: action.label,
                value: action.key,
            })),
            clearable: true,
        },
        {
            id: 'log_name',
            label: 'Canal',
            placeholder: 'Canal',
            icon: History,
            options: logs.map((logName) => ({
                label: logName,
                value: logName,
            })),
            clearable: true,
        },
        {
            id: 'subject_type',
            label: 'Tipo de alvo',
            placeholder: 'Tipo de alvo',
            icon: User,
            options: subjectTypes.map((subjectType) => ({
                label: subjectType.split('\\').pop() ?? subjectType,
                value: subjectType,
            })),
            clearable: true,
        },
    ];
}

export default function ActivityLogIndex({
    activityActionCatalog,
}: ActivityLogIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Activity Log', href: index.url() }],
    });

    const [selectedLog, setSelectedLog] = useState<ActivityLog | null>(null);
    const selectedAction = selectedLog
        ? resolveAction(selectedLog, activityActionCatalog)
        : null;

    const columns = useMemo(
        () => buildColumns(activityActionCatalog),
        [activityActionCatalog],
    );
    const quickFilters = useMemo(
        () => buildQuickFilters(activityActionCatalog),
        [activityActionCatalog],
    );
    const actions = useMemo<DataTableAction<ActivityLog>[]>(
        () => [
            {
                label: 'Ver detalhes',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Ver detalhes',
                onClick: (log) => setSelectedLog(log),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Activity Log" />

            <ActivityLogDetailSheet
                log={selectedLog}
                action={selectedAction}
                open={selectedLog !== null}
                onClose={() => setSelectedLog(null)}
            />

            <div className="page-wrapper-lg">
                <div className="page-section">
                    <Heading
                        title="Activity Log"
                        description="Historico de todas as acoes realizadas no sistema"
                    />

                    <DataTable<ActivityLog>
                        queryKey="activity-log"
                        endpoint={apiRoutes.activityLog.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(log) => log.id}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                        }}
                        filters={filters}
                        quickFilters={quickFilters}
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{
                            display: 'inline',
                            label: 'Ações',
                            buttonSize: 'icon',
                            showTooltips: true,
                        }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                            loadMoreThreshold: '150px',
                        }}
                        searchPlaceholder="Buscar por Descrição ou evento..."
                        appearance={{
                            size: 'md',
                            gridLines: 'none',
                            rowStyle: 'striped',
                        }}
                    />
                </div>
            </div>
        </>
    );
}
