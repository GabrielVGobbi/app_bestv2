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
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import { index, resend, updateSettings } from '@/actions/App/Modules/Email/Http/Controllers/EmailLogController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatDate } from '@/lib/utils';
import type { EmailLog, EmailSettings, EmailStatus } from '@/types/app/email-log';

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

type StatusConfig = { label: string; icon: React.ReactNode; className: string };

const STATUS_CONFIG: Record<EmailStatus, StatusConfig> = {
    queued: {
        label: 'Na fila',
        icon: <Clock className="size-3" />,
        className: 'bg-amber-100 text-amber-700 border-amber-200',
    },
    processing: {
        label: 'Processando',
        icon: <Loader2 className="size-3 animate-spin" />,
        className: 'bg-blue-100 text-blue-700 border-blue-200',
    },
    sent: {
        label: 'Enviado',
        icon: <Send className="size-3" />,
        className: 'bg-green-100 text-green-700 border-green-200',
    },
    failed: {
        label: 'Falhou',
        icon: <X className="size-3" />,
        className: 'bg-red-100 text-red-700 border-red-200',
    },
    bounced: {
        label: 'Rejeitado',
        icon: <AlertTriangle className="size-3" />,
        className: 'bg-orange-100 text-orange-700 border-orange-200',
    },
    delivered: {
        label: 'Entregue',
        icon: <CheckCircle className="size-3" />,
        className: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    },
    delivery_delayed: {
        label: 'Entrega atrasada',
        icon: <Clock className="size-3" />,
        className: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    },
    complained: {
        label: 'Spam',
        icon: <AlertTriangle className="size-3" />,
        className: 'bg-rose-100 text-rose-700 border-rose-200',
    },
    opened: {
        label: 'Aberto',
        icon: <Eye className="size-3" />,
        className: 'bg-cyan-100 text-cyan-700 border-cyan-200',
    },
    clicked: {
        label: 'Clicado',
        icon: <MousePointerClick className="size-3" />,
        className: 'bg-violet-100 text-violet-700 border-violet-200',
    },
    received: {
        label: 'Recebido',
        icon: <Mail className="size-3" />,
        className: 'bg-sky-100 text-sky-700 border-sky-200',
    },
    scheduled: {
        label: 'Agendado',
        icon: <Clock className="size-3" />,
        className: 'bg-slate-100 text-slate-700 border-slate-200',
    },
    suppressed: {
        label: 'Suprimido',
        icon: <ShieldOff className="size-3" />,
        className: 'bg-stone-100 text-stone-700 border-stone-200',
    },
};

function StatusBadge({ status }: { status: EmailStatus }) {
    const cfg = STATUS_CONFIG[status];
    return (
        <span className={`inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-xs font-medium ${cfg.className}`}>
            {cfg.icon}
            {cfg.label}
        </span>
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
        <Badge variant="secondary" className="text-[10px]">
            {CATEGORY_LABELS[category] ?? category}
        </Badge>
    );
}

// ─── Kill Switch Panel ────────────────────────────────────────────────────────

interface KillSwitchPanelProps {
    settings: EmailSettings;
    globalEnabled: boolean;
}

function KillSwitchPanel({ settings, globalEnabled: initialGlobal }: KillSwitchPanelProps) {
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
        <div className="rounded-lg border bg-card p-5">
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div className={`flex size-8 items-center justify-center rounded-md ${globalOn ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                        {globalOn ? <Shield className="size-4" /> : <ShieldOff className="size-4" />}
                    </div>
                    <div>
                        <p className="text-sm font-semibold">Kill Switch de E-mail</p>
                        <p className="text-muted-foreground text-xs">Ative ou desative o envio em tempo real</p>
                    </div>
                </div>
                <Button size="sm" onClick={save} disabled={processing}>
                    {processing ? <Loader2 className="size-3.5 animate-spin" /> : wasSuccessful ? <Check className="size-3.5" /> : null}
                    <span className="ml-1">Salvar</span>
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {[
                    { key: 'enabled' as const, label: 'Global', description: 'Desativa TODOS os envios' },
                    { key: 'auth_enabled' as const, label: 'Autenticação', description: 'Reset de senha, boas-vindas' },
                    { key: 'export_enabled' as const, label: 'Exportação', description: 'Notificações de arquivo pronto' },
                    { key: 'notification_enabled' as const, label: 'Notificações', description: 'Alertas internos por e-mail' },
                    { key: 'system_enabled' as const, label: 'Sistema', description: 'Alertas críticos do sistema' },
                ].map(({ key, label, description }) => {
                    const isOn = data[key];
                    const isDisabled = key !== 'enabled' && !data.enabled;
                    return (
                        <div
                            key={key}
                            className={`flex items-center gap-3 rounded-md border p-3 transition-colors ${
                                key === 'enabled'
                                    ? isOn ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'
                                    : 'bg-muted/30'
                            }`}
                        >
                            <button
                                type="button"
                                role="switch"
                                aria-checked={isOn}
                                disabled={isDisabled}
                                onClick={() => !isDisabled && setData(key, !isOn)}
                                className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${
                                    isOn ? 'bg-primary' : 'bg-input'
                                }`}
                            >
                                <span
                                    className={`pointer-events-none block size-4 rounded-full bg-white shadow-lg ring-0 transition-transform ${
                                        isOn ? 'translate-x-4' : 'translate-x-0'
                                    }`}
                                />
                            </button>
                            <Label className="cursor-pointer" onClick={() => !isDisabled && setData(key, !isOn)}>
                                <span className="block text-sm font-medium">{label}</span>
                                <span className="text-muted-foreground block text-xs">{description}</span>
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
    if (!log) return null;

    function handleResend() {
        router.post(resend.url(log!), {}, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Sheet open={open} onOpenChange={(v) => !v && onClose()}>
            <SheetContent className="flex w-full flex-col sm:max-w-lg" side="right">
                <SheetHeader className="shrink-0 pb-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={log.status} />
                        <CategoryBadge category={log.category} />
                    </div>
                    <SheetTitle className="mt-1 break-all text-base leading-snug">{log.subject}</SheetTitle>
                    <SheetDescription className="flex items-center gap-1.5 text-xs">
                        <Mail className="size-3.5" />
                        {log.to_email}
                        {log.to_name && <span className="text-muted-foreground">({log.to_name})</span>}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto">
                    <div className="space-y-5 px-4 py-3">

                        {/* Meta */}
                        <div className="grid grid-cols-2 gap-3">
                            {[
                                { label: 'Template', value: log.template },
                                { label: 'Mailer', value: log.mailer ?? '—' },
                                { label: 'Tentativas', value: String(log.attempts) },
                                { label: 'Message ID', value: log.message_id ? log.message_id.slice(0, 16) + '…' : '—' },
                                { label: 'Enviado em', value: log.sent_at ? formatDate(log.sent_at) : '—' },
                                { label: 'Falhou em', value: log.failed_at ? formatDate(log.failed_at) : '—' },
                            ].map(({ label, value }) => (
                                <div key={label} className="rounded-md border p-2.5">
                                    <p className="text-muted-foreground text-xs font-medium">{label}</p>
                                    <p className="mt-0.5 break-all font-mono text-xs">{value}</p>
                                </div>
                            ))}
                        </div>

                        {/* Error */}
                        {log.error_message && (
                            <div className="rounded-md border border-red-200 bg-red-50 p-3">
                                <p className="mb-1 text-xs font-medium text-red-700">Erro</p>
                                <p className="break-all font-mono text-xs text-red-600">{log.error_message}</p>
                            </div>
                        )}

                        <Separator />

                        {/* Actions */}
                        {log.status === 'failed' && (
                            <Button variant="outline" size="sm" onClick={handleResend} className="w-full">
                                <RefreshCw className="size-3.5" />
                                Reenviar e-mail
                            </Button>
                        )}

                        <p className="text-muted-foreground text-center text-xs">
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
        router.get(index.url(), { ...filters, ...overrides }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function handleSearchSubmit(e: React.FormEvent) {
        e.preventDefault();
        applyFilters({ search });
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {/* Search */}
            <form onSubmit={handleSearchSubmit} className="flex items-center gap-1.5">
                <div className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por e-mail ou assunto..."
                        className="h-8 w-56 pl-8 text-xs"
                    />
                </div>
            </form>

            {/* Status filter */}
            <select
                value={filters.status ?? ''}
                onChange={(e) => applyFilters({ status: e.target.value || undefined })}
                className="border-input bg-background h-8 rounded-md border px-2 py-1 text-xs focus:outline-none"
            >
                <option value="">Todos os status</option>
                {Object.entries(statuses).map(([value, label]) => (
                    <option key={value} value={value}>{label}</option>
                ))}
            </select>

            {/* Category filter */}
            <select
                value={filters.category ?? ''}
                onChange={(e) => applyFilters({ category: e.target.value || undefined })}
                className="border-input bg-background h-8 rounded-md border px-2 py-1 text-xs focus:outline-none"
            >
                <option value="">Todas as categorias</option>
                {Object.entries(categories).map(([value, label]) => (
                    <option key={value} value={value}>{label}</option>
                ))}
            </select>

            {/* Clear filters */}
            {(filters.status || filters.category || filters.search) && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 px-2 text-xs"
                    onClick={() => { setSearch(''); applyFilters({ status: undefined, category: undefined, search: undefined }); }}
                >
                    <X className="size-3" />
                    Limpar
                </Button>
            )}
        </div>
    );
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function PaginationBar({ logs }: { logs: LaravelPaginator<EmailLog> }) {
    if (logs.last_page <= 1) return null;

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
                    onClick={() => logs.prev_page_url && router.visit(logs.prev_page_url, { preserveState: true })}
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
                    onClick={() => logs.next_page_url && router.visit(logs.next_page_url, { preserveState: true })}
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

export default function EmailLogsIndex({ logs, filters, statuses, categories, settings }: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'E-mails', href: index.url() }],
    });

    const [selectedLog, setSelectedLog] = useState<EmailLog | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const globalEnabled = settings.enabled ?? true;

    return (
        <>
            <Head title="Logs de E-mail" />

            <EmailLogDetailSheet
                log={selectedLog}
                open={selectedLog !== null}
                onClose={() => setSelectedLog(null)}
            />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            title="Logs de E-mail"
                            description="Histórico de todos os e-mails enviados pelo sistema."
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowSettings(!showSettings)}
                            className="shrink-0"
                        >
                            <Settings2 className="size-3.5" />
                            Kill Switch
                            {!globalEnabled && (
                                <Badge variant="destructive" className="ml-1 px-1.5 py-0 text-[10px]">OFF</Badge>
                            )}
                        </Button>
                    </div>

                    {/* Kill Switch */}
                    {showSettings && (
                        <KillSwitchPanel settings={settings} globalEnabled={globalEnabled} />
                    )}

                    {/* Global OFF alert */}
                    {!globalEnabled && !showSettings && (
                        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ShieldOff className="size-4 shrink-0" />
                            <span>O envio de e-mails está <strong>desativado</strong> globalmente. Clique em "Kill Switch" para reativar.</span>
                        </div>
                    )}

                    {/* Filters */}
                    <FiltersBar filters={filters} statuses={statuses} categories={categories} />

                    {/* Table */}
                    {logs.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                            <Mail className="text-muted-foreground mb-3 size-10" />
                            <p className="text-muted-foreground text-sm">Nenhum e-mail encontrado.</p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Status</th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Destinatário</th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Assunto</th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Categoria</th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Tentativas</th>
                                        <th className="text-muted-foreground px-4 py-3 text-left text-xs font-medium">Data</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log, i) => (
                                        <tr
                                            key={log.uuid}
                                            className={i % 2 === 0 ? 'bg-background' : 'bg-muted/20'}
                                        >
                                            <td className="px-4 py-3">
                                                <StatusBadge status={log.status} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">{log.to_email}</span>
                                                    {log.to_name && (
                                                        <span className="text-muted-foreground text-xs">{log.to_name}</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="max-w-xs px-4 py-3">
                                                <span className="block truncate text-sm">{log.subject}</span>
                                                <span className="text-muted-foreground font-mono text-xs">{log.template}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <CategoryBadge category={log.category} />
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {log.status === 'failed' ? (
                                                    <span className="text-xs font-medium text-red-600">{log.attempts}×</span>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">{log.attempts}×</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-muted-foreground text-xs">{formatDate(log.created_at)}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    {log.status === 'failed' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2 text-xs text-red-600 hover:text-red-700"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                router.post(resend.url(log), {}, { preserveScroll: true });
                                                            }}
                                                        >
                                                            <RefreshCw className="size-3" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 px-2 text-xs"
                                                        onClick={() => setSelectedLog(log)}
                                                    >
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
