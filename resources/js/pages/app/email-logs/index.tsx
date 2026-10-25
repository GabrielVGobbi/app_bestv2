import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    Loader2,
    Mail,
    MousePointerClick,
    RefreshCw,
    Search,
    Send,
    Settings2,
    Shield,
    ShieldOff,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    index,
    resend,
    updateSettings,
} from '@/actions/App/Modules/Email/Http/Controllers/EmailLogController';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate } from '@/lib/utils';
import type {
    EmailLog,
    EmailSettings,
    EmailStatus,
} from '@/types/app/email-log';

// Laravel's raw paginator shape (returned by ->paginate() passed directly to Inertia)
interface LaravelPaginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

type StatusConfig = {
    label: string;
    icon: React.ReactNode;
    variant: 'success' | 'warning' | 'destructive' | 'info' | 'secondary';
};

const STATUS_CONFIG: Record<EmailStatus, StatusConfig> = {
    queued: {
        label: 'Na fila',
        icon: <Clock className="size-3" />,
        variant: 'warning',
    },
    processing: {
        label: 'Processando',
        icon: <Loader2 className="size-3 animate-spin" />,
        variant: 'info',
    },
    sent: {
        label: 'Enviado',
        icon: <Send className="size-3" />,
        variant: 'success',
    },
    failed: {
        label: 'Falhou',
        icon: <X className="size-3" />,
        variant: 'destructive',
    },
    bounced: {
        label: 'Rejeitado',
        icon: <AlertTriangle className="size-3" />,
        variant: 'destructive',
    },
    delivered: {
        label: 'Entregue',
        icon: <CheckCircle className="size-3" />,
        variant: 'info',
    },
    delivery_delayed: {
        label: 'Entrega atrasada',
        icon: <Clock className="size-3" />,
        variant: 'warning',
    },
    complained: {
        label: 'Spam',
        icon: <AlertTriangle className="size-3" />,
        variant: 'destructive',
    },
    opened: {
        label: 'Aberto',
        icon: <Eye className="size-3" />,
        variant: 'info',
    },
    clicked: {
        label: 'Clicado',
        icon: <MousePointerClick className="size-3" />,
        variant: 'info',
    },
    received: {
        label: 'Recebido',
        icon: <Mail className="size-3" />,
        variant: 'info',
    },
    scheduled: {
        label: 'Agendado',
        icon: <Clock className="size-3" />,
        variant: 'secondary',
    },
    suppressed: {
        label: 'Suprimido',
        icon: <ShieldOff className="size-3" />,
        variant: 'secondary',
    },
};

function StatusBadge({ status }: { status: EmailStatus }) {
    const cfg = STATUS_CONFIG[status];

    return (
        <Badge variant={cfg.variant}>
            {cfg.icon}
            {cfg.label}
        </Badge>
    );
}

// ─── Category Badge ───────────────────────────────────────────────────────────

const CATEGORY_LABELS: Record<string, string> = {
    auth: 'Auth',
    export: 'Exportação',
    notification: 'Notificação',
    system: 'Sistema',
};

function CategoryBadge({ category }: { category: string }) {
    return (
        <Badge variant="secondary" className="text-2xs">
            {CATEGORY_LABELS[category] ?? category}
        </Badge>
    );
}

// ─── Kill Switch Panel ────────────────────────────────────────────────────────

interface KillSwitchPanelProps {
    settings: EmailSettings;
}

function KillSwitchPanel({ settings }: KillSwitchPanelProps) {
    const { data, setData, patch, processing, wasSuccessful } = useForm({
        enabled: settings.enabled ?? true,
        auth_enabled: settings.auth_enabled ?? true,
        export_enabled: settings.export_enabled ?? true,
        notification_enabled: settings.notification_enabled ?? true,
        system_enabled: settings.system_enabled ?? true,
    });

    function save() {
        patch(updateSettings.url(), {
            preserveScroll: true,
        });
    }

    const globalOn = data.enabled;

    return (
        <div className="rounded-lg border border-border bg-card p-5">
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div
                        className={`flex size-8 items-center justify-center rounded-md ${globalOn ? 'bg-success-soft text-success-soft-foreground' : 'bg-destructive-soft text-destructive-soft-foreground'}`}
                    >
                        {globalOn ? (
                            <Shield className="size-4" />
                        ) : (
                            <ShieldOff className="size-4" />
                        )}
                    </div>
                    <div>
                        <p className="text-sm font-semibold">
                            Controle de envios
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Ative ou desative o envio em tempo real
                        </p>
                    </div>
                </div>
                <Button size="sm" onClick={save} disabled={processing}>
                    {processing ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : wasSuccessful ? (
                        <Check className="size-3.5" />
                    ) : null}
                    <span className="ml-1">Salvar</span>
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {[
                    {
                        key: 'enabled' as const,
                        label: 'Global',
                        description: 'Desativa TODOS os envios',
                    },
                    {
                        key: 'auth_enabled' as const,
                        label: 'Autenticação',
                        description: 'Reset de senha, boas-vindas',
                    },
                    {
                        key: 'export_enabled' as const,
                        label: 'Exportação',
                        description: 'Notificações de arquivo pronto',
                    },
                    {
                        key: 'notification_enabled' as const,
                        label: 'Notificações',
                        description: 'Alertas internos por e-mail',
                    },
                    {
                        key: 'system_enabled' as const,
                        label: 'Sistema',
                        description: 'Alertas críticos do sistema',
                    },
                ].map(({ key, label, description }) => {
                    const isOn = data[key];
                    const isDisabled = key !== 'enabled' && !data.enabled;

                    return (
                        <div
                            key={key}
                            className={`flex items-center gap-3 rounded-md border p-3 transition-colors ${
                                key === 'enabled'
                                    ? isOn
                                        ? 'border-success-soft-foreground/20 bg-success-soft'
                                        : 'border-destructive-soft-foreground/20 bg-destructive-soft'
                                    : 'bg-muted'
                            }`}
                        >
                            <button
                                type="button"
                                role="switch"
                                aria-checked={isOn}
                                disabled={isDisabled}
                                onClick={() =>
                                    !isDisabled && setData(key, !isOn)
                                }
                                className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 ${
                                    isOn ? 'bg-primary' : 'bg-input'
                                }`}
                            >
                                <span
                                    className={`pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform ${
                                        isOn ? 'translate-x-4' : 'translate-x-0'
                                    }`}
                                />
                            </button>
                            <Label
                                className="cursor-pointer"
                                onClick={() =>
                                    !isDisabled && setData(key, !isOn)
                                }
                            >
                                <span className="block text-sm font-medium">
                                    {label}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {description}
                                </span>
                            </Label>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Detail Sheet ─────────────────────────────────────────────────────────────

function EmailLogDetailSheet({
    log,
    open,
    onClose,
}: {
    log: EmailLog | null;
    open: boolean;
    onClose: () => void;
}) {
    if (!log) {
        return null;
    }

    function handleResend() {
        router.post(
            resend.url(log!),
            {},
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    }

    return (
        <Sheet open={open} onOpenChange={(v) => !v && onClose()}>
            <SheetContent
                className="flex w-full flex-col sm:max-w-lg"
                side="right"
            >
                <SheetHeader className="shrink-0 pb-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={log.status} />
                        <CategoryBadge category={log.category} />
                    </div>
                    <SheetTitle className="mt-1 text-base leading-snug break-all">
                        {log.subject}
                    </SheetTitle>
                    <SheetDescription className="flex items-center gap-1.5 text-xs">
                        <Mail className="size-3.5" />
                        {log.to_email}
                        {log.to_name && (
                            <span className="text-muted-foreground">
                                ({log.to_name})
                            </span>
                        )}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto">
                    <div className="space-y-5 px-4 py-3">
                        {/* Meta */}
                        <div className="grid grid-cols-2 gap-3">
                            {[
                                { label: 'Template', value: log.template },
                                { label: 'Mailer', value: log.mailer ?? '-' },
                                {
                                    label: 'Tentativas',
                                    value: String(log.attempts),
                                },
                                {
                                    label: 'Message ID',
                                    value: log.message_id
                                        ? log.message_id.slice(0, 16) + '…'
                                        : '-',
                                },
                                {
                                    label: 'Enviado em',
                                    value: log.sent_at
                                        ? formatDate(log.sent_at)
                                        : '-',
                                },
                                {
                                    label: 'Falhou em',
                                    value: log.failed_at
                                        ? formatDate(log.failed_at)
                                        : '-',
                                },
                            ].map(({ label, value }) => (
                                <div
                                    key={label}
                                    className="rounded-md border p-2.5"
                                >
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-0.5 font-mono text-xs break-all">
                                        {value}
                                    </p>
                                </div>
                            ))}
                        </div>

                        {/* Error */}
                        {log.error_message && (
                            <div className="rounded-md border border-destructive-soft-foreground/20 bg-destructive-soft p-3">
                                <p className="mb-1 text-xs font-medium text-destructive-soft-foreground">
                                    Erro
                                </p>
                                <p className="font-mono text-xs break-all text-destructive-soft-foreground">
                                    {log.error_message}
                                </p>
                            </div>
                        )}

                        <Separator />

                        {/* Actions */}
                        {log.status === 'failed' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleResend}
                                className="w-full"
                            >
                                <RefreshCw className="size-3.5" />
                                Reenviar e-mail
                            </Button>
                        )}

                        <p className="text-center text-xs text-muted-foreground">
                            Criado em {formatDate(log.created_at)}
                        </p>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

// ─── Filters Bar ─────────────────────────────────────────────────────────────

interface FiltersBarProps {
    filters: { status?: string; category?: string; search?: string };
    statuses: Record<string, string>;
    categories: Record<string, string>;
}

function FiltersBar({ filters, statuses, categories }: FiltersBarProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilters(overrides: Record<string, string | undefined>) {
        router.get(
            index.url(),
            { ...filters, ...overrides },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function handleSearchSubmit(e: React.FormEvent) {
        e.preventDefault();
        applyFilters({ search });
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {/* Search */}
            <form
                onSubmit={handleSearchSubmit}
                className="flex items-center gap-1.5"
            >
                <div className="relative">
                    <Search className="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por e-mail ou assunto"
                        className="h-9 w-64 pl-8"
                    />
                </div>
            </form>

            {/* Status filter */}
            <Select
                value={filters.status ?? 'all'}
                onValueChange={(value) =>
                    applyFilters({
                        status: value === 'all' ? undefined : value,
                    })
                }
            >
                <SelectTrigger
                    className="w-44"
                    aria-label="Filtrar por status"
                    placeholder="Todos os status"
                />
                <SelectContent>
                    <SelectItem index={0} value="all">
                        Todos os status
                    </SelectItem>
                    {Object.entries(statuses).map(([value, label], index) => (
                        <SelectItem key={value} index={index + 1} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Category filter */}
            <Select
                value={filters.category ?? 'all'}
                onValueChange={(value) =>
                    applyFilters({
                        category: value === 'all' ? undefined : value,
                    })
                }
            >
                <SelectTrigger
                    className="w-44"
                    aria-label="Filtrar por categoria"
                    placeholder="Todas as categorias"
                />
                <SelectContent>
                    <SelectItem index={0} value="all">
                        Todas as categorias
                    </SelectItem>
                    {Object.entries(categories).map(([value, label], index) => (
                        <SelectItem key={value} index={index + 1} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Clear filters */}
            {(filters.status || filters.category || filters.search) && (
                <Button
                    variant="ghost"
                    onClick={() => {
                        setSearch('');
                        applyFilters({
                            status: undefined,
                            category: undefined,
                            search: undefined,
                        });
                    }}
                >
                    <X className="size-3.5" />
                    Limpar
                </Button>
            )}
        </div>
    );
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function PaginationBar({ logs }: { logs: LaravelPaginator<EmailLog> }) {
    if (logs.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between text-xs">
            <span className="text-muted-foreground">
                {logs.from}–{logs.to} de {logs.total} registros
            </span>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-7 w-7 p-0"
                    disabled={!logs.prev_page_url}
                    onClick={() =>
                        logs.prev_page_url &&
                        router.visit(logs.prev_page_url, {
                            preserveState: true,
                        })
                    }
                >
                    <ChevronLeft className="size-3.5" />
                </Button>
                <span className="px-2 text-xs">
                    {logs.current_page} / {logs.last_page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-7 w-7 p-0"
                    disabled={!logs.next_page_url}
                    onClick={() =>
                        logs.next_page_url &&
                        router.visit(logs.next_page_url, {
                            preserveState: true,
                        })
                    }
                >
                    <ChevronRight className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

interface PageProps {
    logs: LaravelPaginator<EmailLog>;
    filters: { status?: string; category?: string; search?: string };
    statuses: Record<string, string>;
    categories: Record<string, string>;
    settings: EmailSettings;
}

export default function EmailLogsIndex({
    logs,
    filters,
    statuses,
    categories,
    settings,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'E-mails', href: index.url() }],
    });

    const [selectedLog, setSelectedLog] = useState<EmailLog | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const globalEnabled = settings.enabled ?? true;

    return (
        <>
            <Head title="E-mails" />

            <EmailLogDetailSheet
                log={selectedLog}
                open={selectedLog !== null}
                onClose={() => setSelectedLog(null)}
            />

            <Header
                title="E-mails"
                subtitle="Histórico de todos os e-mails enviados pelo sistema."
            >
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowSettings(!showSettings)}
                    aria-expanded={showSettings}
                >
                    <Settings2 className="size-3.5" />
                    Controle de envios
                    {!globalEnabled && (
                        <Badge variant="destructive" className="ml-1">
                            Desligado
                        </Badge>
                    )}
                </Button>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {/* Kill Switch */}
                    {showSettings && <KillSwitchPanel settings={settings} />}

                    {/* Global OFF alert */}
                    {!globalEnabled && !showSettings && (
                        <div className="flex items-center gap-2 rounded-md border border-destructive-soft-foreground/20 bg-destructive-soft px-4 py-3 text-sm text-destructive-soft-foreground">
                            <ShieldOff className="size-4 shrink-0" />
                            <span>
                                O envio de e-mails está{' '}
                                <strong>desativado</strong> globalmente. Abra
                                "Controle de envios" para reativar.
                            </span>
                        </div>
                    )}

                    {/* Filters */}
                    <FiltersBar
                        filters={filters}
                        statuses={statuses}
                        categories={categories}
                    />

                    {/* Table */}
                    {logs.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-border">
                            <EmptyState
                                icon={Mail}
                                title="Nenhum e-mail encontrado"
                            />
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-border bg-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted">
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Destinatário
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Assunto
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Categoria
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Tentativas
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                            Data
                                        </th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log, i) => (
                                        <tr
                                            key={log.uuid}
                                            className={
                                                i % 2 === 0
                                                    ? 'bg-card'
                                                    : 'bg-muted/30'
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={log.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">
                                                        {log.to_email}
                                                    </span>
                                                    {log.to_name && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {log.to_name}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="max-w-xs px-4 py-3">
                                                <span className="block truncate text-sm">
                                                    {log.subject}
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {log.template}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <CategoryBadge
                                                    category={log.category}
                                                />
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {log.status === 'failed' ? (
                                                    <span className="text-xs font-medium text-destructive-soft-foreground">
                                                        {log.attempts}×
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        {log.attempts}×
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDate(log.created_at)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    {log.status ===
                                                        'failed' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2 text-xs"
                                                            aria-label="Reenviar e-mail"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                router.post(
                                                                    resend.url(
                                                                        log,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            <RefreshCw className="size-3" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        onClick={() =>
                                                            setSelectedLog(log)
                                                        }
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Ver
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <PaginationBar logs={logs} />
                </div>
            </div>
        </>
    );
}
