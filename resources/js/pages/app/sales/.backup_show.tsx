import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    Ban,
    Building2,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Disc3,
    ExternalLink,
    FileText,
    Hash,
    Info,
    Link2,
    ListChecks,
    ListPlus,
    Mail,
    Music2,
    Pencil,
    Phone,
    ReceiptText,
    Send,
    Tag,
    UserRound,
    WalletCards,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { show as showClient } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import {
    edit,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import {
    cancel,
    confirm,
    finish,
    quote,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleLifecycleController';
import Header from '@/components/dashboard/header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    cn,
    formatCnpj,
    formatDate,
    formatMoney,
    formatPhone,
} from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type {
    Sale,
    SaleItem,
    SalePagePermissions,
    SaleResource,
} from '@/types/app/sale';

interface Props {
    saleData: SaleResource;
    permissions: SalePagePermissions;
    timelineSubject: {
        type: string;
        id: string | number;
    };
}

function statusVariant(
    status: string,
): 'default' | 'destructive' | 'outline' | 'secondary' {
    if (status === 'campaign_started' || status === 'active') {
        return 'default';
    }

    if (status === 'canceled') {
        return 'destructive';
    }

    if (status === 'pending_schedule' || status === 'quoted') {
        return 'secondary';
    }

    return 'outline';
}

function itemCountLabel(count: number): string {
    return `${count} ${count === 1 ? 'item' : 'itens'}`;
}

function valueOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return formatDate(value);
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

function formatPeriod(
    startsOn: string | null | undefined,
    endsOn: string | null | undefined,
): string {
    if (!startsOn && !endsOn) {
        return 'Sem período definido';
    }

    if (startsOn && endsOn) {
        return `${formatDateOnly(startsOn)} até ${formatDateOnly(endsOn)}`;
    }

    return startsOn
        ? `Inicia em ${formatDateOnly(startsOn)}`
        : `Finaliza em ${formatDateOnly(endsOn)}`;
}

function formatDuration(durationMs: number | null | undefined): string {
    if (!durationMs) {
        return '-';
    }

    const totalSeconds = Math.round(durationMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        active: 'Ativo',
        canceled: 'Cancelado',
        campaign_finished: 'Campanha finalizada',
        campaign_started: 'Campanha iniciada',
        draft: 'Rascunho',
        finished: 'Finalizado',
        pending_schedule: 'Pendente de agenda',
        quoted: 'Orçamento',
        reserved: 'Reservado',
    };

    return labels[status] ?? status;
}

function itemTypeLabel(type: SaleItem['type']): string {
    return type === 'playlist_slot' ? 'Vaga de playlist' : 'Serviço';
}

function itemPrimaryReference(item: SaleItem): string {
    if (item.type === 'playlist_slot') {
        return [
            item.playlist_top?.playlist_name ?? 'Playlist',
            item.playlist_top?.name ?? 'TOP',
        ].join(' / ');
    }

    return item.service?.name ?? 'Serviço';
}

function statusTone(status: string): string {
    if (status === 'campaign_started' || status === 'active') {
        return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'canceled') {
        return 'border-destructive/25 bg-destructive/10 text-destructive';
    }

    if (status === 'pending_schedule' || status === 'quoted') {
        return 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (status === 'campaign_finished' || status === 'finished') {
        return 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function moneyOrRestricted(value: number | null | undefined): string {
    return formatMoney(value, {
        fromCents: true,
        fallback: 'Restrito',
    });
}

function nextActionLabel(
    sale: Sale,
    permissions: SalePagePermissions,
    pendingSlotsCount: number,
): string {
    if (sale.status === 'draft') {
        return permissions.can_quote
            ? 'Pronta para orçamento'
            : 'Rascunho em montagem';
    }

    if (sale.status === 'quoted') {
        return permissions.can_confirm
            ? 'Aguardando confirmação'
            : 'Orçamento enviado';
    }

    if (sale.status === 'campaign_started') {
        if (pendingSlotsCount > 0) {
            return `${pendingSlotsCount} ${pendingSlotsCount === 1 ? 'slot pendente' : 'slots pendentes'}`;
        }

        return permissions.can_finish
            ? 'Campanha em execução'
            : 'Acompanhamento operacional';
    }

    if (sale.status === 'campaign_finished') {
        return 'Campanha finalizada';
    }

    if (sale.status === 'canceled') {
        return 'Venda cancelada';
    }

    return statusLabel(sale.status);
}

function DetailLine({
    label,
    value,
    icon: Icon,
    className,
}: {
    label: string;
    value: ReactNode;
    icon?: typeof Info;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                {Icon ? <Icon className="size-3.5" /> : null}
                {label}
            </dt>
            <dd className="mt-1 min-w-0 text-sm font-medium break-words">
                {value ?? '-'}
            </dd>
        </div>
    );
}

function Panel({
    title,
    description,
    icon: Icon,
    children,
    className,
    action,
}: {
    title: string;
    description?: string;
    icon?: typeof Info;
    children: ReactNode;
    className?: string;
    action?: ReactNode;
}) {
    return (
        <section className={cn('rounded-lg border bg-card', className)}>
            <header className="flex min-h-14 items-start justify-between gap-3 border-b px-4 py-4">
                <div className="flex min-w-0 gap-3">
                    {Icon ? (
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
                            <Icon className="size-4" />
                        </span>
                    ) : null}
                    <div className="min-w-0">
                        <h2 className="truncate text-sm font-semibold">
                            {title}
                        </h2>
                        {description ? (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                </div>
                {action}
            </header>
            <div className="p-4">{children}</div>
        </section>
    );
}

function LifecycleActions({
    sale,
    permissions,
    className,
}: {
    sale: Sale;
    permissions: SalePagePermissions;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            {sale.status === 'draft' && permissions.can_quote ? (
                <Form action={quote.url(sale.uuid)} method="patch">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            <Send className="size-4" />
                            Orçar
                        </Button>
                    )}
                </Form>
            ) : null}

            {sale.status === 'quoted' && permissions.can_confirm ? (
                <Form action={confirm.url(sale.uuid)} method="patch">
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="status_confirmed"
                                value="1"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                <CheckCircle2 className="size-4" />
                                Confirmar
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}

            {sale.status === 'campaign_started' && permissions.can_finish ? (
                <Form action={finish.url(sale.uuid)} method="patch">
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="status_confirmed"
                                value="1"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                <CheckCircle2 className="size-4" />
                                Finalizar
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}

            {['draft', 'quoted', 'campaign_started'].includes(sale.status) &&
                permissions.can_cancel ? (
                <Form
                    action={cancel.url(sale.uuid)}
                    method="patch"
                    className="flex flex-wrap items-center gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="status_confirmed"
                                value="1"
                            />
                            {sale.status === 'campaign_started' ? (
                                <Input
                                    name="status_reason"
                                    placeholder="Motivo do cancelamento"
                                    className="h-8 w-56"
                                />
                            ) : null}
                            <Button
                                type="submit"
                                size="sm"
                                variant="destructive"
                                disabled={processing}
                            >
                                <XCircle className="size-4" />
                                Cancelar
                            </Button>
                            <InputError message={errors.status_reason} />
                        </>
                    )}
                </Form>
            ) : null}
        </div>
    );
}

function SaleHero({
    sale,
    items,
    pendingSlotsCount,
    permissions,
}: {
    sale: Sale;
    items: SaleItem[];
    pendingSlotsCount: number;
    permissions: SalePagePermissions;
}) {
    const subtitle = sale.title ?? sale.client?.name ?? 'Venda sem título';
    const nextAction = nextActionLabel(sale, permissions, pendingSlotsCount);

    return (
        <section className="overflow-hidden rounded-lg border bg-card">
            <div className="border-b bg-muted/30 px-4 py-5 sm:px-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0 space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className={cn(
                                    'rounded-md',
                                    statusTone(sale.status),
                                )}
                            >
                                {sale.status_presenter.label}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                Criada em {formatDate(sale.created_at)}
                            </span>
                        </div>
                        <div className="min-w-0">
                            <h1 className="truncate text-2xl font-semibold tracking-tight sm:text-3xl">
                                {sale.order_number}
                            </h1>
                            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                {subtitle}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 lg:min-w-[30rem]">
                        <div className="rounded-lg border bg-background px-4 py-3">
                            <span className="text-xs font-medium text-muted-foreground">
                                Total fechado
                            </span>
                            <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                                {moneyOrRestricted(sale.total_amount)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {itemCountLabel(items.length)} na composição
                            </p>
                        </div>
                        <div className="rounded-lg border bg-background px-4 py-3">
                            <span className="text-xs font-medium text-muted-foreground">
                                Próxima leitura
                            </span>
                            <p className="mt-1 text-lg font-semibold">
                                {nextAction}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Status comercial, sem financeiro nesta fase
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-4">
                <div className="bg-card p-4">
                    <DetailLine
                        label="Cliente"
                        value={sale.client?.name}
                        icon={UserRound}
                    />
                </div>
                <div className="bg-card p-4">
                    <DetailLine
                        label="Empresa"
                        value={sale.company?.nickname ?? sale.company?.name}
                        icon={Building2}
                    />
                </div>
                <div className="bg-card p-4">
                    <DetailLine
                        label="Vendedor"
                        value={sale.seller?.name}
                        icon={BadgeCheck}
                    />
                </div>
                <div className="bg-card p-4">
                    <DetailLine
                        label="Última atualização"
                        value={formatDate(sale.updated_at)}
                        icon={Clock3}
                    />
                </div>
            </div>
        </section>
    );
}

function SaleOverview({
    sale,
    items,
    pendingSlotsCount,
}: {
    sale: Sale;
    items: SaleItem[];
    pendingSlotsCount: number;
}) {
    const playlistItems = items.filter((item) => item.type === 'playlist_slot');
    const serviceItems = items.filter((item) => item.type === 'service');

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <Panel title="Leitura rápida" icon={ListChecks}>
                <dl className="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <DetailLine
                        label="Status"
                        value={sale.status_presenter.label}
                        icon={BadgeCheck}
                    />
                    <DetailLine
                        label="Itens"
                        value={itemCountLabel(items.length)}
                        icon={ReceiptText}
                    />
                    <DetailLine
                        label="Serviços"
                        value={serviceItems.length}
                        icon={ListPlus}
                    />
                    <DetailLine
                        label="Vagas de playlist"
                        value={playlistItems.length}
                        icon={Music2}
                    />
                    <DetailLine
                        label="Slots pendentes"
                        value={pendingSlotsCount}
                        icon={CalendarClock}
                    />
                    <DetailLine
                        label="Criada em"
                        value={formatDate(sale.created_at)}
                        icon={CalendarDays}
                    />
                </dl>
            </Panel>

            <Panel title="Partes envolvidas" icon={UserRound}>
                <dl className="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <DetailLine
                        label="Cliente"
                        value={sale.client?.name}
                        icon={UserRound}
                    />
                    <DetailLine
                        label="Telefone"
                        value={formatPhone(sale.client?.phone)}
                        icon={Phone}
                    />
                    <DetailLine
                        label="Email"
                        value={sale.client?.email}
                        icon={Mail}
                    />
                    <DetailLine
                        label="Vendedor"
                        value={sale.seller?.name}
                        icon={BadgeCheck}
                    />
                    <DetailLine
                        label="Empresa"
                        value={sale.company?.nickname ?? sale.company?.name}
                        icon={Building2}
                    />
                    <DetailLine
                        label="CNPJ"
                        value={formatCnpj(sale.company?.cnpj)}
                        icon={FileText}
                    />
                </dl>
            </Panel>

            <Panel title="Observações internas" icon={Info}>
                <div className="min-h-28 rounded-lg border bg-muted/20 p-3 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                    {sale.notes || 'Nenhuma observação cadastrada.'}
                </div>
            </Panel>

            <SaleTimelineSummary sale={sale} />
        </div>
    );
}

function ReadonlySaleItemCard({ item }: { item: SaleItem }) {
    const slot = item.sellable;
    const spotifyLabel =
        item.track_spotify_id ?? item.track_spotify_url ?? 'Não informado';

    return (
        <article className="overflow-hidden rounded-lg border bg-background">
            <div className="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_15rem]">
                <div className="min-w-0">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-card text-muted-foreground">
                            {item.type === 'playlist_slot' ? (
                                <Music2 className="size-5" />
                            ) : (
                                <ListPlus className="size-5" />
                            )}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {itemTypeLabel(item.type)}
                                </Badge>
                                <Badge
                                    variant="secondary"
                                    className={cn(
                                        'rounded-md',
                                        statusTone(item.status),
                                    )}
                                >
                                    {statusLabel(item.status)}
                                </Badge>
                                {slot ? (
                                    <Badge variant="secondary">
                                        Slot {statusLabel(slot.status)}
                                    </Badge>
                                ) : null}
                            </div>
                            <h3 className="mt-2 text-base leading-6 font-semibold">
                                {item.description || itemPrimaryReference(item)}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {itemPrimaryReference(item)}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                        <DetailLine
                            label="Período do item"
                            value={formatPeriod(item.starts_on, item.ends_on)}
                            icon={CalendarDays}
                        />
                        <DetailLine
                            label="Quantidade"
                            value={item.quantity}
                            icon={Hash}
                        />
                        <DetailLine
                            label="Valor unitário"
                            value={moneyOrRestricted(item.unit_amount)}
                            icon={Tag}
                        />

                        {item.type === 'playlist_slot' ? (
                            <>
                                <DetailLine
                                    label="Música"
                                    value={valueOrDash(item.track_name)}
                                    icon={Disc3}
                                />
                                <DetailLine
                                    label="Spotify"
                                    value={
                                        item.track_spotify_url ? (
                                            <a
                                                href={item.track_spotify_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex max-w-full min-w-0 items-center gap-1 text-primary hover:underline"
                                            >
                                                <span className="truncate">
                                                    {spotifyLabel}
                                                </span>
                                                <ExternalLink className="size-3.5 shrink-0" />
                                            </a>
                                        ) : (
                                            spotifyLabel
                                        )
                                    }
                                    icon={Link2}
                                />
                                <DetailLine
                                    label="Artistas"
                                    value={item.track_artists?.join(', ')}
                                    icon={Music2}
                                />
                                <DetailLine
                                    label="Álbum"
                                    value={item.track_album_name}
                                    icon={Disc3}
                                />
                                <DetailLine
                                    label="Duração"
                                    value={formatDuration(
                                        item.track_duration_ms,
                                    )}
                                    icon={Clock3}
                                />
                                <DetailLine
                                    label="ISRC"
                                    value={item.track_isrc}
                                    icon={Hash}
                                />
                            </>
                        ) : null}

                        {slot ? (
                            <>
                                <DetailLine
                                    label="Período do slot"
                                    value={formatPeriod(
                                        slot.starts_on,
                                        slot.ends_on,
                                    )}
                                    icon={CalendarClock}
                                />
                                <DetailLine
                                    label="Qtd. do slot"
                                    value={slot.quantity}
                                    icon={Hash}
                                />
                            </>
                        ) : null}
                    </dl>
                </div>

                <aside className="rounded-lg border bg-card p-4">
                    <div className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">Bruto</span>
                            <span className="font-medium tabular-nums">
                                {moneyOrRestricted(
                                    item.quantity * item.unit_amount,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">
                                Desconto
                            </span>
                            <span className="font-medium tabular-nums">
                                {moneyOrRestricted(item.discount_amount)}
                            </span>
                        </div>
                        <Separator className="my-3" />
                        <div className="flex items-center justify-between gap-3">
                            <span className="font-semibold">Total</span>
                            <span className="text-lg font-semibold tabular-nums">
                                {moneyOrRestricted(item.total_amount)}
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        </article>
    );
}

function SaleItemsPanel({
    sale,
    items,
    permissions,
}: {
    sale: Sale;
    items: SaleItem[];
    permissions: SalePagePermissions;
}) {
    const canEdit =
        permissions.can_update && ['draft', 'quoted'].includes(sale.status);

    return (
        <Panel
            title="Itens & entrega"
            description="Composição da venda somente leitura nesta tela."
            icon={ReceiptText}
            action={
                canEdit ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={edit.url(sale.uuid)}>
                            <Pencil className="size-4" />
                            Editar composição
                        </Link>
                    </Button>
                ) : null
            }
        >
            {items.length > 0 ? (
                <div className="space-y-3">
                    {items.map((item) => (
                        <ReadonlySaleItemCard key={item.uuid} item={item} />
                    ))}
                </div>
            ) : (
                <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Nenhum item adicionado nesta venda.
                </div>
            )}
        </Panel>
    );
}

function ContactPanel({ sale }: { sale: Sale }) {
    return (
        <Panel
            title="Cliente"
            description="Snapshot de contato usado nesta venda."
            icon={UserRound}
            action={
                sale.client?.uuid ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={showClient.url(sale.client.uuid)}>
                            <ExternalLink className="size-4" />
                            Abrir cliente
                        </Link>
                    </Button>
                ) : null
            }
        >
            <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                <DetailLine
                    label="Cliente"
                    value={sale.client?.name}
                    icon={UserRound}
                />
                <DetailLine
                    label="Email"
                    value={sale.client?.email}
                    icon={Mail}
                />
                <DetailLine
                    label="Telefone"
                    value={formatPhone(sale.client?.phone)}
                    icon={Phone}
                />
                <DetailLine
                    label="Empresa"
                    value={sale.company?.nickname ?? sale.company?.name}
                    icon={Building2}
                />
                <DetailLine
                    label="CNPJ"
                    value={formatCnpj(sale.company?.cnpj)}
                    icon={FileText}
                />
                <DetailLine
                    label="Vendedor"
                    value={sale.seller?.name}
                    icon={BadgeCheck}
                />
            </dl>
        </Panel>
    );
}

function FinancialPanel({ sale, items }: { sale: Sale; items: SaleItem[] }) {
    return (
        <Panel
            title="Financeiro"
            description="Valores negociados da venda; pagamento e baixa ficam fora deste status."
            icon={WalletCards}
        >
            <div className="grid gap-4 lg:grid-cols-3">
                <div className="rounded-lg border bg-background p-4">
                    <p className="text-xs font-medium text-muted-foreground">
                        Subtotal
                    </p>
                    <p className="mt-2 text-xl font-semibold tabular-nums">
                        {moneyOrRestricted(sale.subtotal_amount)}
                    </p>
                </div>
                <div className="rounded-lg border bg-background p-4">
                    <p className="text-xs font-medium text-muted-foreground">
                        Desconto
                    </p>
                    <p className="mt-2 text-xl font-semibold tabular-nums">
                        {moneyOrRestricted(sale.discount_amount)}
                    </p>
                </div>
                <div className="rounded-lg border bg-background p-4">
                    <p className="text-xs font-medium text-muted-foreground">
                        Total
                    </p>
                    <p className="mt-2 text-xl font-semibold tabular-nums">
                        {moneyOrRestricted(sale.total_amount)}
                    </p>
                </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-lg border">
                {items.length > 0 ? (
                    <div className="divide-y">
                        {items.map((item) => (
                            <div
                                key={item.uuid}
                                className="grid gap-3 p-4 text-sm md:grid-cols-[minmax(0,1fr)_8rem_8rem]"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium break-words">
                                        {item.description ||
                                            itemPrimaryReference(item)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {itemTypeLabel(item.type)} · qtd.{' '}
                                        {item.quantity}
                                    </p>
                                </div>
                                <DetailLine
                                    label="Unitário"
                                    value={moneyOrRestricted(item.unit_amount)}
                                />
                                <DetailLine
                                    label="Total"
                                    value={moneyOrRestricted(item.total_amount)}
                                />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="p-4 text-sm text-muted-foreground">
                        Nenhum item compõe o valor desta venda.
                    </div>
                )}
            </div>
        </Panel>
    );
}

function SaleTimelineSummary({ sale }: { sale: Sale }) {
    const entries = [
        {
            label: 'Criada',
            value: sale.created_at,
            icon: CalendarDays,
            active: true,
        },
        {
            label: 'Confirmada',
            value: sale.confirmed_at,
            icon: CheckCircle2,
            active: sale.confirmed_at !== null,
        },
        {
            label: 'Finalizada',
            value: sale.finished_at,
            icon: BadgeCheck,
            active: sale.finished_at !== null,
        },
        {
            label: 'Cancelada',
            value: sale.canceled_at,
            icon: Ban,
            active: sale.canceled_at !== null,
        },
    ];

    return (
        <Panel title="Ciclo comercial" icon={Clock3}>
            <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                {entries.map((entry) => {
                    const Icon = entry.icon;

                    return (
                        <li key={entry.label} className="flex gap-3">
                            <span
                                className={cn(
                                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground',
                                    entry.active &&
                                    'border-primary/25 bg-primary/10 text-primary',
                                )}
                            >
                                <Icon className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    {entry.label}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {entry.value
                                        ? formatDate(entry.value)
                                        : 'Ainda não ocorreu'}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ol>

            {sale.cancellation_reason ? (
                <div className="mt-4 rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm">
                    <p className="font-medium text-destructive">
                        Motivo do cancelamento
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        {sale.cancellation_reason}
                    </p>
                </div>
            ) : null}
        </Panel>
    );
}

function TimelinePanel({
    sale,
    timelineSubject,
}: {
    sale: Sale;
    timelineSubject: Props['timelineSubject'];
}) {
    return (
        <div className="space-y-4">
            <SaleTimelineSummary sale={sale} />
            <ActivityTimeline
                showHeader={false}
                modelType={timelineSubject.type}
                modelId={timelineSubject.id}
                title=""
                allowAttachments
                className="border bg-card p-4 shadow-none"
            />
        </div>
    );
}

function TechnicalPanel({ sale }: { sale: Sale }) {
    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <Panel title="Identificadores" icon={Hash}>
                <dl className="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <DetailLine label="UUID" value={sale.uuid} icon={Hash} />
                    <DetailLine
                        label="ID interno"
                        value={sale.id}
                        icon={Hash}
                    />
                    <DetailLine
                        label="Empresa ID"
                        value={sale.company_id}
                        icon={Building2}
                    />
                    <DetailLine
                        label="Cliente ID"
                        value={sale.client_id}
                        icon={UserRound}
                    />
                    <DetailLine
                        label="Vendedor ID"
                        value={sale.seller_id}
                        icon={BadgeCheck}
                    />
                    <DetailLine
                        label="Moeda"
                        value={sale.currency}
                        icon={Tag}
                    />
                </dl>
            </Panel>

            <Panel title="Datas técnicas" icon={Clock3}>
                <dl className="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    <DetailLine
                        label="Criada em"
                        value={formatDate(sale.created_at)}
                        icon={CalendarDays}
                    />
                    <DetailLine
                        label="Atualizada em"
                        value={formatDate(sale.updated_at)}
                        icon={Clock3}
                    />
                    <DetailLine
                        label="Confirmada em"
                        value={
                            sale.confirmed_at
                                ? formatDate(sale.confirmed_at)
                                : '-'
                        }
                        icon={CheckCircle2}
                    />
                    <DetailLine
                        label="Cancelada em"
                        value={
                            sale.canceled_at
                                ? formatDate(sale.canceled_at)
                                : '-'
                        }
                        icon={Ban}
                    />
                </dl>
            </Panel>
        </div>
    );
}

function SaleSidebar({
    sale,
    items,
    pendingSlotsCount,
    permissions,
}: {
    sale: Sale;
    items: SaleItem[];
    pendingSlotsCount: number;
    permissions: SalePagePermissions;
}) {
    const canEdit =
        permissions.can_update && ['draft', 'quoted'].includes(sale.status);
    const nextAction = nextActionLabel(sale, permissions, pendingSlotsCount);

    return (
        <aside className="min-w-0 xl:sticky xl:top-4 xl:self-start">
            <div className="space-y-4 xl:max-h-[calc(100dvh-2rem)] xl:overflow-y-auto xl:pr-1">
                <section className="rounded-lg border bg-card">
                    <div className="p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Venda atual
                                </p>
                                <h2 className="mt-1 truncate text-base font-semibold">
                                    {sale.order_number}
                                </h2>
                            </div>
                            <Badge
                                variant="outline"
                                className={cn('rounded-md', statusTone(sale.status))}>
                                {sale.status_presenter.label}
                            </Badge>
                        </div>

                        <div className="mt-5 rounded-lg border bg-muted/20 p-4">
                            <span className="text-xs font-medium text-muted-foreground">
                                Total
                            </span>
                            <p className="mt-1 text-2xl font-semibold tabular-nums">
                                {moneyOrRestricted(sale.total_amount)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {itemCountLabel(items.length)}
                            </p>
                        </div>

                        <div className="mt-5 space-y-2 text-sm">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <UserRound className="size-4 shrink-0" />
                                <span className="truncate">
                                    {sale.client?.name ?? '-'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Building2 className="size-4 shrink-0" />
                                <span className="truncate">
                                    {sale.company?.nickname ??
                                        sale.company?.name ??
                                        '-'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <BadgeCheck className="size-4 shrink-0" />
                                <span className="truncate">
                                    {sale.seller?.name ?? '-'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="border-t px-5 py-4">
                        <p className="text-xs font-medium text-muted-foreground">
                            Próximo ponto de atenção
                        </p>
                        <p className="mt-1 text-sm font-medium">{nextAction}</p>
                    </div>
                </section>

                <Panel title="Ações" icon={ListChecks}>
                    <div className="space-y-3">
                        {canEdit ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={edit.url(sale.uuid)}>
                                    <Pencil className="size-4" />
                                    Editar venda
                                </Link>
                            </Button>
                        ) : null}
                        <LifecycleActions
                            sale={sale}
                            permissions={permissions}
                            className="flex-col items-stretch [&_button]:w-full"
                        />
                        {!canEdit &&
                            !permissions.can_quote &&
                            !permissions.can_confirm &&
                            !permissions.can_finish &&
                            !permissions.can_cancel ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma ação disponível para o status atual.
                            </p>
                        ) : null}
                    </div>
                </Panel>

                {pendingSlotsCount > 0 ? (
                    <Panel title="Pendências operacionais" icon={AlertTriangle}>
                        <div className="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300">
                            {pendingSlotsCount}{' '}
                            {pendingSlotsCount === 1
                                ? 'vaga de playlist ainda precisa de agenda.'
                                : 'vagas de playlist ainda precisam de agenda.'}
                        </div>
                    </Panel>
                ) : null}
            </div>
        </aside>
    );
}

export default function ShowSale({
    saleData,
    permissions,
    timelineSubject,
}: Props) {
    const sale = saleData.data;
    const items = sale.sale_items ?? [];
    const pendingSlotsCount = items.filter(
        (item) => item.sellable?.status === 'pending_schedule',
    ).length;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vendas', href: index.url() },
            { title: sale.order_number, href: show.url(sale.uuid) },
        ],
    });

    return (
        <>
            <Head title={sale.order_number} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {sale.order_number}
                            </h1>
                            <Badge variant={statusVariant(sale.status)}>
                                {sale.status_presenter.label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {sale.title ??
                                sale.client?.name ??
                                'Venda sem título'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {permissions.can_update &&
                        ['draft', 'quoted'].includes(sale.status) ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={edit.url(sale.uuid)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    ) : null}
                    <LifecycleActions sale={sale} permissions={permissions} />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="space-y-4 p-4">
                    <SaleHero
                        sale={sale}
                        items={items}
                        pendingSlotsCount={pendingSlotsCount}
                        permissions={permissions}
                    />

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px] 2xl:grid-cols-[minmax(0,1fr)_390px]">
                        <main className="min-w-0">
                            <Tabs defaultValue="overview" className="gap-4">
                                <div className="overflow-x-auto rounded-lg border bg-card px-4">
                                    <TabsList className="h-auto gap-6 rounded-none bg-transparent p-0">
                                        <TabsTrigger
                                            value="overview"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Visão geral
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="items"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Itens & entrega
                                            <span className="rounded-full bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                                {items.length}
                                            </span>
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="client"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Cliente
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="finance"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Financeiro
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="timeline"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Timeline
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="technical"
                                            className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        >
                                            Técnico
                                        </TabsTrigger>
                                    </TabsList>
                                </div>

                                <TabsContent
                                    value="overview"
                                    className="space-y-4"
                                >
                                    <SaleOverview
                                        sale={sale}
                                        items={items}
                                        pendingSlotsCount={pendingSlotsCount}
                                    />
                                </TabsContent>

                                <TabsContent value="items">
                                    <SaleItemsPanel
                                        sale={sale}
                                        items={items}
                                        permissions={permissions}
                                    />
                                </TabsContent>

                                <TabsContent value="client">
                                    <ContactPanel sale={sale} />
                                </TabsContent>

                                <TabsContent value="finance">
                                    <FinancialPanel sale={sale} items={items} />
                                </TabsContent>

                                <TabsContent value="timeline">
                                    <TimelinePanel
                                        sale={sale}
                                        timelineSubject={timelineSubject}
                                    />
                                </TabsContent>

                                <TabsContent value="technical">
                                    <TechnicalPanel sale={sale} />
                                </TabsContent>
                            </Tabs>
                        </main>

                        <SaleSidebar
                            sale={sale}
                            items={items}
                            pendingSlotsCount={pendingSlotsCount}
                            permissions={permissions}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
