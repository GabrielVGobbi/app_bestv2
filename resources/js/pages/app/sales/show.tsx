import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    CheckCircle2,
    ChevronDown,
    Clock3,
    ExternalLink,
    ListPlus,
    Music2,
    Pencil,
    RefreshCw,
    Send,
    Undo2,
    UserRound,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import {
    edit,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import { syncSale } from '@/actions/App/Modules/Sales/Http/Controllers/SaleItemSpotifyPresenceController';
import {
    cancel,
    confirm,
    finish,
    quote,
    revertToDraft,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleLifecycleController';
import Header from '@/components/dashboard/header';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PanelTableHead } from '@/components/ui/panel-table';
import { cn, formatDate, formatDateOnly, formatMoney } from '@/lib/utils';
import type {
    Sale,
    SaleItem,
    SalePagePermissions,
    SaleResource,
} from '@/types/app/sale';

import { SpotifyPresenceControl } from './components/spotify-presence-control';

interface Props {
    saleData: SaleResource;
    permissions: SalePagePermissions;
    timelineSubject: {
        type: string;
        id: string | number;
    };
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

function moneyOrRestricted(value: number | null | undefined): string {
    return formatMoney(value, {
        fromCents: true,
        fallback: 'Restrito',
    });
}

function itemOriginLabel(item: SaleItem): string {
    if (item.type !== 'playlist_slot') {
        return 'Serviço';
    }

    if (item.playlist_top?.name) {
        return item.playlist_top.name;
    }

    return item.playlist_top_id ? `TOP #${item.playlist_top_id}` : 'TOP';
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

function DetailLine({
    label,
    value,
    icon: Icon,
    className,
}: {
    label: string;
    value: ReactNode;
    icon?: LucideIcon;
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

function SpecPanel({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="grid h-full min-w-0 grid-rows-[auto_minmax(0,1fr)]">
            <h4 className="mb-2 text-xs font-medium text-muted-foreground">
                {title}
            </h4>
            <dl className="h-full divide-y divide-border/70 overflow-hidden rounded-lg border border-border/70 bg-background/70">
                {children}
            </dl>
        </section>
    );
}

function SpecRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-3 px-3 py-2">
            <dt className="text-[11px] text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-xs font-medium break-words text-foreground">
                {children}
            </dd>
        </div>
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

            {sale.status === 'quoted' && permissions.can_update ? (
                <Form action={revertToDraft.url(sale.uuid)} method="patch">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            <Undo2 className="size-4" />
                            Voltar para rascunho
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

function ItemOriginVisual({ item }: { item: SaleItem }) {
    const coverUrl =
        item.type === 'playlist_slot'
            ? (item.playlist_top?.cover_image_url ?? null)
            : null;

    if (coverUrl) {
        return (
            <img
                src={coverUrl}
                alt={item.playlist_top?.playlist_name ?? item.description}
                className="size-12 rounded-md border object-cover"
                loading="lazy"
            />
        );
    }

    const Icon = item.type === 'playlist_slot' ? Music2 : ListPlus;

    return (
        <span
            className={cn(
                'flex size-12 shrink-0 items-center justify-center rounded-md border',
                item.type === 'playlist_slot'
                    ? 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300'
                    : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            )}
        >
            <Icon className="size-5" />
        </span>
    );
}

function ItemMusicDetails({ item }: { item: SaleItem }) {
    if (item.type !== 'playlist_slot') {
        return (
            <>
                <SpecRow label="Tipo">Serviço</SpecRow>
                <SpecRow label="Nome">
                    {valueOrDash(item.service?.name ?? item.description)}
                </SpecRow>
            </>
        );
    }

    const spotifyLabel =
        item.track_spotify_id ?? item.track_spotify_url ?? 'Não informado';

    return (
        <>
            {item.track_image_url ? (
                <SpecRow label="Capa">
                    <img
                        src={item.track_image_url}
                        alt={item.track_name ?? 'Faixa'}
                        className="size-14 rounded-md border object-cover"
                        loading="lazy"
                    />
                </SpecRow>
            ) : null}
            <SpecRow label="Faixa">{valueOrDash(item.track_name)}</SpecRow>
            <SpecRow label="Artista">
                {valueOrDash(item.track_artists?.join(', '))}
            </SpecRow>
            <SpecRow label="Duração">
                {formatDuration(item.track_duration_ms)}
            </SpecRow>
            <SpecRow label="ISRC">{valueOrDash(item.track_isrc)}</SpecRow>
            <SpecRow label="Spotify">
                {item.track_spotify_url ? (
                    <a
                        href={item.track_spotify_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex max-w-full items-center gap-1 rounded-sm font-mono text-[11px] text-primary underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="truncate">{spotifyLabel}</span>
                        <ExternalLink className="size-3 shrink-0" />
                    </a>
                ) : (
                    <span className="font-mono text-[11px] text-muted-foreground">
                        {spotifyLabel}
                    </span>
                )}
            </SpecRow>
        </>
    );
}

function SaleOverview({ sale, items }: { sale: Sale; items: SaleItem[] }) {
    return (
        <section
            aria-label="Resumo da venda"
            className="overflow-hidden rounded-lg border bg-card"
        >
            <div className="border-b bg-muted/30 px-4 py-5 sm:px-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0 space-y-3">
                        <div className="min-w-0">
                            <h1 className="truncate text-2xl font-semibold tracking-tight sm:text-3xl">
                                {sale.order_number}
                            </h1>
                            <span className="text-xs text-muted-foreground">
                                Criada em {formatDate(sale.created_at)}
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-1">
                        <div className="rounded-lg border bg-background px-4 py-3">
                            <span className="text-xs font-medium text-muted-foreground">
                                Total
                            </span>
                            <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                                {moneyOrRestricted(sale.total_amount)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {itemCountLabel(items.length)} Serviços
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

function SaleComposition({
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
    const canVerifySpotify =
        permissions.can_verify_spotify_playlist &&
        items.some((item) => item.type === 'playlist_slot');

    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <header className="flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex items-baseline gap-2">
                    <h2 className="text-base font-semibold">Itens da venda</h2>
                    <span className="text-xs text-muted-foreground">
                        {itemCountLabel(items.length)}
                    </span>
                </div>

                <div className="flex flex-wrap gap-2">
                    {canVerifySpotify ? (
                        <Form
                            action={syncSale({ sale: sale.uuid })}
                            options={{
                                preserveScroll: true,
                                only: ['saleData', 'permissions', 'flash'],
                            }}
                            disableWhileProcessing
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    <RefreshCw
                                        className={cn(
                                            'size-4',
                                            processing && 'animate-spin',
                                        )}
                                    />
                                    Sincronizar Spotify
                                </Button>
                            )}
                        </Form>
                    ) : null}

                    {canEdit ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={edit.url(sale.uuid)}>
                                <Pencil className="size-4" />
                                Editar itens
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </header>

            {items.length > 0 ? (
                <>
                    <PanelTableHead
                        className="grid-cols-[minmax(0,1fr)_auto_9rem] gap-4 px-5 py-2.5"
                        columns={[
                            { label: 'Item' },
                            { label: 'Spotify' },
                            {
                                label: 'Valor',
                                align: 'right',
                            },
                        ]}
                    />

                    <div className="divide-y">
                        {items.map((item) => {
                            const itemHeadingId = `sale-item-${item.uuid}`;
                            const presence = item.spotify_playlist_presence;

                            return (
                                <article
                                    key={item.uuid}
                                    aria-labelledby={itemHeadingId}
                                    className="@container"
                                >
                                    <div className="grid gap-4 px-4 py-4 sm:px-5 @4xl:grid-cols-[minmax(0,1fr)_auto_9rem] @4xl:items-center">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <ItemOriginVisual item={item} />
                                            <div className="min-w-0">
                                                <h3
                                                    id={itemHeadingId}
                                                    className="truncate text-sm font-semibold"
                                                >
                                                    {item.description ||
                                                        itemPrimaryReference(
                                                            item,
                                                        )}
                                                </h3>
                                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                                    {item.type ===
                                                        'playlist_slot' &&
                                                    item.track_name
                                                        ? item.track_name
                                                        : itemPrimaryReference(
                                                              item,
                                                          )}
                                                </p>
                                                <p className="mt-2 text-[11px] text-muted-foreground">
                                                    {itemOriginLabel(item)}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="min-w-0">
                                            <p className="text-xs font-medium text-muted-foreground @4xl:hidden">
                                                Spotify
                                            </p>
                                            <div className="mt-1 @4xl:mt-0">
                                                <SpotifyPresenceControl
                                                    saleUuid={sale.uuid}
                                                    item={item}
                                                    canVerify={
                                                        permissions.can_verify_spotify_playlist
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="@4xl:text-right">
                                            <p className="text-xs font-medium text-muted-foreground @4xl:hidden">
                                                Valor
                                            </p>
                                            <p className="mt-1 font-semibold tabular-nums @4xl:mt-0">
                                                {moneyOrRestricted(
                                                    item.total_amount,
                                                )}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                                                {item.quantity} ×{' '}
                                                {moneyOrRestricted(
                                                    item.unit_amount,
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <details className="group border-t bg-muted/15">
                                        <summary className="flex min-h-9 cursor-pointer list-none items-center gap-2 px-4 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/35 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:px-5 [&::-webkit-details-marker]:hidden">
                                            <ChevronDown className="size-3.5 transition-transform group-open:rotate-180" />
                                            Ver detalhes
                                        </summary>
                                        <div className="grid items-stretch gap-4 px-4 pt-1 pb-4 sm:px-5 lg:grid-cols-3">
                                            <SpecPanel
                                                title={
                                                    item.type ===
                                                    'playlist_slot'
                                                        ? 'Faixa'
                                                        : 'Serviço'
                                                }
                                            >
                                                <ItemMusicDetails item={item} />
                                            </SpecPanel>

                                            <SpecPanel title="Entrega operacional">
                                                {item.sellable ? (
                                                    <>
                                                        <SpecRow label="Status">
                                                            <StatusBadge
                                                                presenter={
                                                                    item
                                                                        .sellable
                                                                        .status_presenter
                                                                }
                                                            />
                                                        </SpecRow>
                                                        <SpecRow label="Período">
                                                            {formatPeriod(
                                                                item.sellable
                                                                    .starts_on,
                                                                item.sellable
                                                                    .ends_on,
                                                            )}
                                                        </SpecRow>
                                                        {presence.track_position ? (
                                                            <SpecRow label="Posição">
                                                                #
                                                                {
                                                                    presence.track_position
                                                                }
                                                            </SpecRow>
                                                        ) : null}
                                                        {presence.match_type ? (
                                                            <SpecRow label="Match">
                                                                {presence.match_type ===
                                                                'track_id'
                                                                    ? 'ID Spotify'
                                                                    : 'ISRC'}
                                                            </SpecRow>
                                                        ) : null}
                                                    </>
                                                ) : (
                                                    <SpecRow label="Status">
                                                        Ainda não vinculada.
                                                    </SpecRow>
                                                )}
                                            </SpecPanel>

                                            <SpecPanel title="Comercial">
                                                <SpecRow label="Desconto">
                                                    <span className="tabular-nums">
                                                        {moneyOrRestricted(
                                                            item.discount_amount,
                                                        )}
                                                    </span>
                                                </SpecRow>
                                                <SpecRow label="Moeda">
                                                    {item.currency}
                                                </SpecRow>
                                                <SpecRow label="Item">
                                                    #{item.id}
                                                </SpecRow>
                                                <SpecRow label="Origem">
                                                    {item.service_id
                                                        ? `Serviço #${item.service_id}`
                                                        : `TOP #${item.playlist_top_id}`}
                                                </SpecRow>
                                            </SpecPanel>
                                        </div>
                                    </details>
                                </article>
                            );
                        })}
                    </div>
                </>
            ) : (
                <div className="p-4">
                    <div className="rounded-lg border border-dashed bg-muted/20 p-8 text-center">
                        <ListPlus className="mx-auto size-8 text-muted-foreground" />
                        <p className="mt-3 text-sm font-medium">
                            Nenhum serviço adicionado nesta venda.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Adicione itens para montar a composição comercial.
                        </p>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function ShowSale({ saleData, permissions }: Props) {
    const sale = saleData.data;
    const items = sale.sale_items ?? [];

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
                            <h1 className="page-heading-title">
                                {sale.order_number}
                            </h1>
                            <StatusBadge presenter={sale.status_presenter} />
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
                    <SaleOverview sale={sale} items={items} />
                    <SaleComposition
                        sale={sale}
                        items={items}
                        permissions={permissions}
                    />
                </div>
            </div>
        </>
    );
}
