import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Disc3,
    ExternalLink,
    Hash,
    Link2,
    ListPlus,
    Music2,
    Pencil,
    ReceiptText,
    RefreshCw,
    Send,
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
import {
    syncItem,
    syncSale,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleItemSpotifyPresenceController';
import {
    cancel,
    confirm,
    finish,
    quote,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleLifecycleController';
import Header from '@/components/dashboard/header';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn, formatDate, formatMoney } from '@/lib/utils';
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

function moneyOrRestricted(value: number | null | undefined): string {
    return formatMoney(value, {
        fromCents: true,
        fallback: 'Restrito',
    });
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

function itemGrossAmount(item: SaleItem): number {
    return item.quantity * item.unit_amount;
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

function ItemOriginVisual({ item }: { item: SaleItem }) {
    if (item.track_image_url) {
        return (
            <img
                src={item.track_image_url}
                alt={item.track_name ?? item.description}
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

function ItemDeliveryDetails({ item }: { item: SaleItem }) {
    const slot = item.sellable;

    return (
        <div className="space-y-2">
            <div className="flex min-w-0 items-start gap-2">
                <CalendarDays className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span className="min-w-0 text-sm">
                    {formatPeriod(item.starts_on, item.ends_on)}
                </span>
            </div>

            {slot ? (
                <div className="flex min-w-0 items-start gap-2 text-xs text-muted-foreground">
                    <Clock3 className="mt-0.5 size-3.5 shrink-0" />
                    <span className="min-w-0">
                        Slot: {slot.status_presenter.label} ·{' '}
                        {formatPeriod(slot.starts_on, slot.ends_on)}
                    </span>
                </div>
            ) : (
                <div className="flex min-w-0 items-start gap-2 text-xs text-muted-foreground">
                    <ReceiptText className="mt-0.5 size-3.5 shrink-0" />
                    <span className="min-w-0">
                        Entrega operacional ainda não vinculada
                    </span>
                </div>
            )}
        </div>
    );
}

function ItemMusicDetails({ item }: { item: SaleItem }) {
    if (item.type !== 'playlist_slot') {
        return null;
    }

    const spotifyLabel =
        item.track_spotify_id ?? item.track_spotify_url ?? 'Não informado';

    return (
        <div className="mt-3 grid gap-2 rounded-md bg-muted/35 p-3 text-xs text-muted-foreground sm:grid-cols-2">
            <div className="flex min-w-0 items-center gap-2">
                <Disc3 className="size-3.5 shrink-0" />
                <span className="truncate">{valueOrDash(item.track_name)}</span>
            </div>
            <div className="flex min-w-0 items-center gap-2">
                <Music2 className="size-3.5 shrink-0" />
                <span className="truncate">
                    {valueOrDash(item.track_artists?.join(', '))}
                </span>
            </div>
            <div className="flex min-w-0 items-center gap-2">
                <Clock3 className="size-3.5 shrink-0" />
                <span>{formatDuration(item.track_duration_ms)}</span>
            </div>
            <div className="flex min-w-0 items-center gap-2">
                <Hash className="size-3.5 shrink-0" />
                <span className="truncate">{valueOrDash(item.track_isrc)}</span>
            </div>
            <div className="flex min-w-0 items-center gap-2 sm:col-span-2">
                <Link2 className="size-3.5 shrink-0" />
                {item.track_spotify_url ? (
                    <a
                        href={item.track_spotify_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex min-w-0 items-center gap-1 text-primary hover:underline"
                    >
                        <span className="truncate">{spotifyLabel}</span>
                        <ExternalLink className="size-3.5 shrink-0" />
                    </a>
                ) : (
                    <span className="truncate">{spotifyLabel}</span>
                )}
            </div>
        </div>
    );
}

function SpotifyPresenceCell({
    sale,
    item,
    canVerify,
}: {
    sale: Sale;
    item: SaleItem;
    canVerify: boolean;
}) {
    if (item.type !== 'playlist_slot') {
        return <span className="text-xs text-muted-foreground">-</span>;
    }

    const presence = item.spotify_playlist_presence;

    return (
        <div className="space-y-2">
            <StatusBadge presenter={presence.status_presenter} />
            <div className="space-y-1 text-xs text-muted-foreground">
                {presence.track_position ? (
                    <p>Posição #{presence.track_position}</p>
                ) : null}
                {presence.match_type ? (
                    <p>
                        Match por{' '}
                        {presence.match_type === 'track_id'
                            ? 'ID Spotify'
                            : 'ISRC'}
                    </p>
                ) : null}
                {presence.error ? (
                    <p className="line-clamp-2 text-red-600 dark:text-red-300">
                        {presence.error}
                    </p>
                ) : null}
            </div>
            {canVerify ? (
                <Form
                    action={syncItem({
                        sale: sale.uuid,
                        saleItem: item.uuid,
                    })}
                    options={{ preserveScroll: true, only: ['saleData', 'permissions', 'flash'] }}
                    disableWhileProcessing
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            className="h-8 px-2 text-xs"
                            disabled={processing}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-3.5',
                                    processing && 'animate-spin',
                                )}
                            />
                            Revalidar
                        </Button>
                    )}
                </Form>
            ) : null}
        </div>
    );
}

function ServiceStructureTable({
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
    const serviceItemsCount = items.filter(
        (item) => item.type === 'service',
    ).length;
    const playlistItemsCount = items.filter(
        (item) => item.type === 'playlist_slot',
    ).length;
    const totalQuantity = items.reduce(
        (total, item) => total + item.quantity,
        0,
    );
    const canVerifySpotify =
        permissions.can_verify_spotify_playlist && playlistItemsCount > 0;

    return (
        <section className="overflow-hidden rounded-lg border bg-card">
            <header className="flex flex-col gap-4 border-b bg-muted/20 px-4 py-4 sm:px-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
                        <ReceiptText className="size-5" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold">
                            Estrutura de serviços
                        </h2>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Composição comercial da venda, com origem, período,
                            entrega e valores congelados por item.
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <dl className="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Serviços
                            </dt>
                            <dd className="font-semibold">
                                {serviceItemsCount}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Playlists
                            </dt>
                            <dd className="font-semibold">
                                {playlistItemsCount}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Qtd.
                            </dt>
                            <dd className="font-semibold">{totalQuantity}</dd>
                        </div>
                    </dl>

                    <div className="flex flex-wrap gap-2">
                        {canVerifySpotify ? (
                            <Form
                                action={syncSale({ sale: sale.uuid })}
                                options={{ preserveScroll: true, only: ['saleData', 'permissions', 'flash'] }}
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
                                    Editar composição
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>
            </header>

            {items.length > 0 ? (
                <>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1120px] table-fixed text-sm">
                            <colgroup>
                                <col className="w-[30%]" />
                                <col className="w-[18%]" />
                                <col className="w-[17%]" />
                                <col className="w-[7%]" />
                                <col className="w-[10%]" />
                                <col className="w-[8%]" />
                                <col className="w-[10%]" />
                            </colgroup>
                            <thead className="bg-muted/35 text-xs text-muted-foreground">
                                <tr className="border-b">
                                    <th className="px-4 py-3 text-left font-medium">
                                        Serviço / origem
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Entrega
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Spotify na playlist
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Qtd.
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Unitário
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Desconto
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {items.map((item) => (
                                    <tr
                                        key={item.uuid}
                                        className="align-top transition-colors hover:bg-muted/20"
                                    >
                                        <td className="px-4 py-4">
                                            <div className="flex min-w-0 gap-3">
                                                <ItemOriginVisual item={item} />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-md"
                                                        >
                                                            {itemTypeLabel(
                                                                item.type,
                                                            )}
                                                        </Badge>
                                                        <StatusBadge
                                                            presenter={
                                                                item.status_presenter
                                                            }
                                                        />
                                                    </div>
                                                    <p className="mt-2 line-clamp-2 font-semibold">
                                                        {item.description ||
                                                            itemPrimaryReference(
                                                                item,
                                                            )}
                                                    </p>
                                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                                        {itemPrimaryReference(
                                                            item,
                                                        )}
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap gap-1.5 text-[11px] text-muted-foreground">
                                                        <span className="rounded-md bg-muted px-1.5 py-0.5">
                                                            Item #{item.id}
                                                        </span>
                                                        {item.service_id ? (
                                                            <span className="rounded-md bg-muted px-1.5 py-0.5">
                                                                Serviço #
                                                                {
                                                                    item.service_id
                                                                }
                                                            </span>
                                                        ) : null}
                                                        {item.playlist_top_id ? (
                                                            <span className="rounded-md bg-muted px-1.5 py-0.5">
                                                                TOP #
                                                                {
                                                                    item.playlist_top_id
                                                                }
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                    <ItemMusicDetails
                                                        item={item}
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <ItemDeliveryDetails item={item} />
                                        </td>
                                        <td className="px-4 py-4">
                                            <SpotifyPresenceCell
                                                sale={sale}
                                                item={item}
                                                canVerify={
                                                    permissions.can_verify_spotify_playlist
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-4 text-right font-medium tabular-nums">
                                            {item.quantity}
                                        </td>
                                        <td className="px-4 py-4 text-right tabular-nums">
                                            <div className="font-medium">
                                                {moneyOrRestricted(
                                                    item.unit_amount,
                                                )}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Bruto{' '}
                                                {moneyOrRestricted(
                                                    itemGrossAmount(item),
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right tabular-nums">
                                            {moneyOrRestricted(
                                                item.discount_amount,
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <div className="text-base font-semibold tabular-nums">
                                                {moneyOrRestricted(
                                                    item.total_amount,
                                                )}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {item.currency}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <footer className="grid gap-px border-t bg-border sm:grid-cols-3">
                        <div className="bg-card px-4 py-3">
                            <span className="text-xs text-muted-foreground">
                                Subtotal
                            </span>
                            <p className="mt-1 font-semibold tabular-nums">
                                {moneyOrRestricted(sale.subtotal_amount)}
                            </p>
                        </div>
                        <div className="bg-card px-4 py-3">
                            <span className="text-xs text-muted-foreground">
                                Desconto comercial
                            </span>
                            <p className="mt-1 font-semibold tabular-nums">
                                {moneyOrRestricted(sale.discount_amount)}
                            </p>
                        </div>
                        <div className="bg-card px-4 py-3">
                            <span className="text-xs text-muted-foreground">
                                Total fechado
                            </span>
                            <p className="mt-1 text-lg font-semibold tabular-nums">
                                {moneyOrRestricted(sale.total_amount)}
                            </p>
                        </div>
                    </footer>
                </>
            ) : (
                <div className="p-4">
                    <div className="rounded-lg border border-dashed bg-muted/20 p-8 text-center">
                        <ListPlus className="mx-auto size-8 text-muted-foreground" />
                        <p className="mt-3 text-sm font-medium">
                            Nenhum serviço adicionado nesta venda.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            A composição aparecerá aqui assim que a venda tiver
                            itens comerciais.
                        </p>
                    </div>
                </div>
            )}
        </section>
    );
}

function SaleHero({ sale, items }: { sale: Sale; items: SaleItem[] }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-card">
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
                            <h1 className="text-xl font-semibold">
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
                    <SaleHero sale={sale} items={items} />
                    <ServiceStructureTable
                        sale={sale}
                        items={items}
                        permissions={permissions}
                    />
                </div>
            </div>
        </>
    );
}
