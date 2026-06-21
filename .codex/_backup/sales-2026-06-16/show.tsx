import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    ListPlus,
    Music2,
    Pencil,
    Plus,
    Send,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { schedule as scheduleSlot } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistSlotController';
import {
    edit,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import {
    destroy as destroyItem,
    store as storeItem,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleItemController';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn, formatDate, formatMoney } from '@/lib/utils';
import type {
    Sale,
    SaleFormOption,
    SaleFormOptions,
    SaleItem,
    SalePagePermissions,
    SaleResource,
} from '@/types/app/sale';

interface Props {
    saleData: SaleResource;
    formOptions: SaleFormOptions;
    permissions: SalePagePermissions;
}

type ItemType = 'service' | 'playlist_slot';

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

function centsToDecimal(value: number | undefined): string {
    return ((value ?? 0) / 100).toFixed(2);
}

function centsFromDecimal(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '0';
    }

    const normalized = trimmed.includes(',')
        ? trimmed.replace(/\./g, '').replace(',', '.')
        : trimmed;
    const amount = Number(normalized);

    if (!Number.isFinite(amount)) {
        return '0';
    }

    return String(Math.round(amount * 100));
}

function centsFromDecimalNumber(value: string): number {
    const cents = Number(centsFromDecimal(value));

    return Number.isFinite(cents) ? cents : 0;
}

function itemCountLabel(count: number): string {
    return `${count} ${count === 1 ? 'item' : 'itens'}`;
}

function LifecycleActions({
    sale,
    permissions,
}: {
    sale: Sale;
    permissions: SalePagePermissions;
}) {
    return (
        <div className="flex flex-wrap gap-2">
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

function MoneyField({
    id,
    name,
    label,
    value,
    onChange,
    error,
    includeHidden = true,
}: {
    id: string;
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    includeHidden?: boolean;
}) {
    return (
        <div className="grid gap-2">
            {includeHidden ? (
                <input
                    type="hidden"
                    name={name}
                    value={centsFromDecimal(value)}
                />
            ) : null}
            <Label htmlFor={id}>{label}</Label>
            <InputMoney
                id={id}
                value={value === '' ? '0.00' : value}
                onChange={onChange}
                aria-invalid={!!error}
            />
            <InputError message={error} />
        </div>
    );
}

function PlaylistTopPicker({
    playlists,
    playlistTops,
    selectedPlaylistId,
    selectedPlaylistTopId,
    onPlaylistChange,
    onPlaylistTopChange,
    playlistTopError,
}: {
    playlists: SaleFormOption[];
    playlistTops: SaleFormOption[];
    selectedPlaylistId: string;
    selectedPlaylistTopId: string;
    onPlaylistChange: (playlistId: string) => void;
    onPlaylistTopChange: (playlistTopId: string) => void;
    playlistTopError?: string;
}) {
    const filteredPlaylistTops = playlistTops.filter(
        (playlistTop) =>
            selectedPlaylistId !== '' &&
            String(playlistTop.playlist_id) === selectedPlaylistId,
    );

    return (
        <div className="grid gap-3 md:grid-cols-2">
            <div className="grid min-w-0 gap-2">
                <Label htmlFor="playlist_id">Playlist</Label>
                <Select
                    value={selectedPlaylistId}
                    onValueChange={onPlaylistChange}
                >
                    <SelectTrigger
                        id="playlist_id"
                        className="w-full min-w-0 [&_[data-slot=select-value]]:min-w-0 [&_[data-slot=select-value]]:truncate"
                    >
                        <SelectValue placeholder="Selecione a playlist" />
                    </SelectTrigger>
                    <SelectContent className="max-w-[32rem]">
                        {playlists.map((playlist) => (
                            <SelectItem
                                key={playlist.id}
                                value={String(playlist.id)}
                            >
                                {playlist.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid min-w-0 gap-2">
                <Label htmlFor="playlist_top_id">TOP</Label>
                <Select
                    name="playlist_top_id"
                    value={selectedPlaylistTopId}
                    onValueChange={onPlaylistTopChange}
                    disabled={selectedPlaylistId === ''}
                >
                    <SelectTrigger
                        id="playlist_top_id"
                        className="w-full min-w-0 [&_[data-slot=select-value]]:min-w-0 [&_[data-slot=select-value]]:truncate"
                        aria-invalid={!!playlistTopError}
                    >
                        <SelectValue
                            placeholder={
                                selectedPlaylistId === ''
                                    ? 'Escolha a playlist'
                                    : 'Selecione o TOP'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent className="max-w-[32rem]">
                        {filteredPlaylistTops.map((playlistTop) => (
                            <SelectItem
                                key={playlistTop.id}
                                value={String(playlistTop.id)}
                            >
                                {playlistTop.top_name ?? playlistTop.name} -{' '}
                                {formatMoney(playlistTop.price, {
                                    fromCents: true,
                                })}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={playlistTopError} />
            </div>
        </div>
    );
}

function ItemForm({
    sale,
    formOptions,
}: {
    sale: Sale;
    formOptions: SaleFormOptions;
}) {
    const [type, setType] = useState<ItemType>('service');
    const [formVersion, setFormVersion] = useState(0);
    const [selectedServiceId, setSelectedServiceId] = useState('');
    const [selectedPlaylistId, setSelectedPlaylistId] = useState('');
    const [selectedPlaylistTopId, setSelectedPlaylistTopId] = useState('');
    const [quantity, setQuantity] = useState('1');
    const [unitAmount, setUnitAmount] = useState('');
    const [discountAmount, setDiscountAmount] = useState('0.00');

    const selectedService = formOptions.services.find(
        (service) => String(service.id) === selectedServiceId,
    );
    const selectedPlaylistTop = formOptions.playlist_tops.find(
        (playlistTop) => String(playlistTop.id) === selectedPlaylistTopId,
    );
    const selectedOption =
        type === 'service' ? selectedService : selectedPlaylistTop;
    const selectedOptionAmount =
        type === 'service'
            ? selectedService?.base_price
            : selectedPlaylistTop?.price;
    const unitAmountWasChosen = unitAmount.trim() !== '';
    const quantityNumber = Math.max(1, Number.parseInt(quantity, 10) || 1);
    const itemPreviewAmount = Math.max(
        0,
        quantityNumber * centsFromDecimalNumber(unitAmount) -
            centsFromDecimalNumber(discountAmount),
    );
    const currentTypeLabel =
        type === 'service' ? 'Serviço' : 'Vaga de playlist';

    function resetItemForm(): void {
        setType('service');
        setSelectedServiceId('');
        setSelectedPlaylistId('');
        setSelectedPlaylistTopId('');
        setQuantity('1');
        setUnitAmount('');
        setDiscountAmount('0.00');
        setFormVersion((current) => current + 1);
    }

    function changeType(nextType: ItemType): void {
        if (type === nextType) {
            return;
        }

        setType(nextType);
        setSelectedServiceId('');
        setSelectedPlaylistId('');
        setSelectedPlaylistTopId('');
        setQuantity('1');
        setUnitAmount('');
        setDiscountAmount('0.00');
        setFormVersion((current) => current + 1);
    }

    function selectService(serviceId: string): void {
        setSelectedServiceId(serviceId);

        const service = formOptions.services.find(
            (item) => String(item.id) === serviceId,
        );
        setUnitAmount(centsToDecimal(service?.base_price));
    }

    function selectPlaylistTop(playlistTopId: string): void {
        setSelectedPlaylistTopId(playlistTopId);

        const playlistTop = formOptions.playlist_tops.find(
            (item) => String(item.id) === playlistTopId,
        );
        setUnitAmount(centsToDecimal(playlistTop?.price));
    }

    function selectPlaylist(playlistId: string): void {
        setSelectedPlaylistId(playlistId);
        setSelectedPlaylistTopId('');
        setUnitAmount('');
    }

    if (!['draft', 'quoted'].includes(sale.status)) {
        return null;
    }

    return (
        <Card className="gap-0 overflow-hidden">
            <CardHeader className="border-b pb-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle className="text-base">
                                Adicionar item à venda
                            </CardTitle>
                            <Badge variant="outline">Venda rápida</Badge>
                        </div>
                        <CardDescription>
                            Lançamento direto para escolher, precificar e salvar
                            sem trocar de tela.
                        </CardDescription>
                    </div>
                    <div className="rounded-md border bg-muted/30 px-3 py-2 text-right">
                        <span className="block text-[11px] font-medium text-muted-foreground">
                            Prévia do item
                        </span>
                        <span className="block text-lg font-semibold tabular-nums">
                            {formatMoney(itemPreviewAmount, {
                                fromCents: true,
                            })}
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-5">
                <Form
                    key={formVersion}
                    action={storeItem.url(sale.uuid)}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={resetItemForm}
                    onError={() => toast.error('Verifique os dados do item.')}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="type" value={type} />

                            <fieldset className="space-y-4">
                                <legend className="px-1 text-xs font-semibold text-muted-foreground">
                                    Item vendido
                                </legend>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        aria-pressed={type === 'service'}
                                        onClick={() => changeType('service')}
                                        className={cn(
                                            'flex h-14 items-center gap-3 rounded-md border bg-background px-3 text-left transition-[border-color,box-shadow,background-color]',
                                            type === 'service'
                                                ? 'border-primary bg-primary/5 shadow-xs'
                                                : 'border-input hover:bg-muted/60',
                                        )}
                                    >
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md border bg-card">
                                            <ListPlus className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold">
                                                Serviço
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                Catálogo e valor negociado
                                            </span>
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        aria-pressed={type === 'playlist_slot'}
                                        onClick={() =>
                                            changeType('playlist_slot')
                                        }
                                        className={cn(
                                            'flex h-14 items-center gap-3 rounded-md border bg-background px-3 text-left transition-[border-color,box-shadow,background-color]',
                                            type === 'playlist_slot'
                                                ? 'border-primary bg-primary/5 shadow-xs'
                                                : 'border-input hover:bg-muted/60',
                                        )}
                                    >
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md border bg-card">
                                            <Music2 className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold">
                                                Vaga de playlist
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                Playlist, TOP e música
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem]">
                                    <div className="grid gap-3 xl:grid-cols-[minmax(0,1fr)_6.5rem]">
                                        {type === 'service' ? (
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="service_id">
                                                    Serviço
                                                </Label>
                                                <Select
                                                    name="service_id"
                                                    value={selectedServiceId}
                                                    onValueChange={
                                                        selectService
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="service_id"
                                                        className="w-full min-w-0 [&_[data-slot=select-value]]:min-w-0 [&_[data-slot=select-value]]:truncate"
                                                        aria-invalid={
                                                            !!errors.service_id
                                                        }
                                                    >
                                                        <SelectValue placeholder="Selecione" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-w-[32rem]">
                                                        {formOptions.services.map(
                                                            (service) => (
                                                                <SelectItem
                                                                    key={
                                                                        service.id
                                                                    }
                                                                    value={String(
                                                                        service.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        service.name
                                                                    }{' '}
                                                                    -{' '}
                                                                    {formatMoney(
                                                                        service.base_price,
                                                                        {
                                                                            fromCents: true,
                                                                        },
                                                                    )}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.service_id}
                                                />
                                            </div>
                                        ) : (
                                            <div className="xl:col-span-2">
                                                <PlaylistTopPicker
                                                    playlists={
                                                        formOptions.playlists
                                                    }
                                                    playlistTops={
                                                        formOptions.playlist_tops
                                                    }
                                                    selectedPlaylistId={
                                                        selectedPlaylistId
                                                    }
                                                    selectedPlaylistTopId={
                                                        selectedPlaylistTopId
                                                    }
                                                    onPlaylistChange={
                                                        selectPlaylist
                                                    }
                                                    onPlaylistTopChange={
                                                        selectPlaylistTop
                                                    }
                                                    playlistTopError={
                                                        errors.playlist_top_id
                                                    }
                                                />
                                            </div>
                                        )}

                                        <div className="grid gap-2">
                                            <Label htmlFor="quantity">
                                                Qtd.
                                            </Label>
                                            <Input
                                                id="quantity"
                                                name="quantity"
                                                type="number"
                                                min="1"
                                                value={quantity}
                                                inputMode="numeric"
                                                onChange={(event) =>
                                                    setQuantity(
                                                        event.target.value,
                                                    )
                                                }
                                                aria-invalid={!!errors.quantity}
                                            />
                                            <InputError
                                                message={errors.quantity}
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-md bg-muted/30 p-3">
                                        <div className="text-sm font-semibold">
                                            Referência
                                        </div>
                                        <dl className="mt-2 space-y-2 text-sm">
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-muted-foreground">
                                                    Selecionado
                                                </dt>
                                                <dd className="min-w-0 truncate text-right font-medium">
                                                    {selectedOption?.name ??
                                                        '-'}
                                                </dd>
                                            </div>
                                            <div className="flex justify-between gap-3">
                                                <dt className="text-muted-foreground">
                                                    Sugerido
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {selectedOptionAmount !==
                                                    undefined
                                                        ? formatMoney(
                                                              selectedOptionAmount,
                                                              {
                                                                  fromCents: true,
                                                              },
                                                          )
                                                        : '-'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset className="space-y-3 border-t pt-4">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <MoneyField
                                        id="unit_amount"
                                        name="unit_amount"
                                        label="Valor unitário"
                                        value={unitAmount}
                                        onChange={setUnitAmount}
                                        error={errors.unit_amount}
                                        includeHidden={unitAmountWasChosen}
                                    />
                                    <MoneyField
                                        id="discount_amount"
                                        name="discount_amount"
                                        label="Desconto"
                                        value={discountAmount}
                                        onChange={setDiscountAmount}
                                        error={errors.discount_amount}
                                    />
                                </div>
                            </fieldset>

                            {type === 'playlist_slot' ? (
                                <fieldset className="rounded-md bg-muted/30 p-3">
                                    <legend className="mb-3 flex items-center gap-2 text-sm font-semibold">
                                        <Music2 className="size-4 text-muted-foreground" />
                                        Música
                                    </legend>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="track_name">
                                                Música
                                            </Label>
                                            <Input
                                                id="track_name"
                                                name="track_name"
                                                autoComplete="off"
                                                aria-invalid={
                                                    !!errors.track_name
                                                }
                                            />
                                            <InputError
                                                message={errors.track_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="track_spotify_url">
                                                Spotify
                                            </Label>
                                            <Input
                                                id="track_spotify_url"
                                                name="track_spotify_url"
                                                placeholder="Link, URI ou ID"
                                                autoComplete="off"
                                                aria-invalid={
                                                    !!errors.track_spotify_url
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.track_spotify_url
                                                }
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                            ) : null}

                            <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {selectedOption?.name ??
                                            `${currentTypeLabel} ainda não selecionado`}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {quantityNumber} x{' '}
                                        {unitAmountWasChosen
                                            ? formatMoney(
                                                  centsFromDecimalNumber(
                                                      unitAmount,
                                                  ),
                                                  { fromCents: true },
                                              )
                                            : 'R$ 0,00'}{' '}
                                        com{' '}
                                        {formatMoney(
                                            centsFromDecimalNumber(
                                                discountAmount,
                                            ),
                                            { fromCents: true },
                                        )}{' '}
                                        de desconto
                                    </p>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto sm:min-w-40"
                                >
                                    <Plus className="size-4" />
                                    Adicionar item
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function itemSecondaryText(item: SaleItem): string {
    if (item.type === 'playlist_slot') {
        return `${item.track_name ?? 'Música'} · ${item.playlist_top?.playlist_name ?? 'Playlist'}`;
    }

    return item.service?.name ?? 'Serviço';
}

function ItemRow({ sale, item }: { sale: Sale; item: SaleItem }) {
    const slot = item.sellable;

    return (
        <div className="rounded-md border bg-background p-3">
            <div className="flex items-start gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                    {item.type === 'playlist_slot' ? (
                        <Music2 className="size-4" />
                    ) : (
                        <ListPlus className="size-4" />
                    )}
                </span>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {item.description}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {itemSecondaryText(item)}
                            </p>
                        </div>
                        <Badge
                            variant={statusVariant(item.status)}
                            className="max-w-28 truncate"
                        >
                            {item.status}
                        </Badge>
                    </div>

                    <div className="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span className="text-muted-foreground">
                            {item.quantity} x{' '}
                            {formatMoney(item.unit_amount, {
                                fromCents: true,
                            })}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {formatMoney(item.total_amount, {
                                fromCents: true,
                            })}
                        </span>
                    </div>
                </div>

                {['draft', 'quoted'].includes(sale.status) ? (
                    <Form
                        action={destroyItem.url({
                            sale: sale.uuid,
                            saleItem: item.uuid,
                        })}
                        method="delete"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="icon"
                                variant="ghost"
                                disabled={processing}
                                aria-label="Remover item"
                                className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </Form>
                ) : null}
            </div>

            {slot?.status === 'pending_schedule' ? (
                <Form
                    action={scheduleSlot.url(slot.uuid)}
                    method="patch"
                    className="mt-3 grid gap-2 border-t pt-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`slot-${slot.uuid}-starts_on`}>
                                    Início
                                </Label>
                                <DatePicker
                                    id={`slot-${slot.uuid}-starts_on`}
                                    name="starts_on"
                                    aria-invalid={!!errors.starts_on}
                                />
                                <InputError message={errors.starts_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`slot-${slot.uuid}-ends_on`}>
                                    Fim
                                </Label>
                                <DatePicker
                                    id={`slot-${slot.uuid}-ends_on`}
                                    name="ends_on"
                                    aria-invalid={!!errors.ends_on}
                                />
                                <InputError message={errors.ends_on} />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                className="w-full"
                            >
                                <CalendarClock className="size-4" />
                                Agendar
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}
        </div>
    );
}

function SaleItemsPanel({ sale, items }: { sale: Sale; items: SaleItem[] }) {
    return (
        <Card className="gap-0 overflow-hidden">
            <CardHeader className="border-b pb-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle>Venda atual</CardTitle>
                        <CardDescription>
                            {itemCountLabel(items.length)} na composição
                        </CardDescription>
                    </div>
                    <Badge variant={statusVariant(sale.status)}>
                        {sale.status_presenter.label}
                    </Badge>
                </div>

                <div className="mt-3 rounded-md bg-muted/40 p-3">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-xs font-medium text-muted-foreground">
                            Total da venda
                        </span>
                        <span className="text-xl font-semibold tabular-nums">
                            {formatMoney(sale.total_amount, {
                                fromCents: true,
                                fallback: '-',
                            })}
                        </span>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-3 pt-4">
                {items.length > 0 ? (
                    <div className="space-y-2">
                        {items.map((item) => (
                            <ItemRow key={item.uuid} sale={sale} item={item} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Nenhum item adicionado.
                    </div>
                )}

                <div className="space-y-2 border-t pt-3 text-sm">
                    <div className="flex justify-between gap-3">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span className="tabular-nums">
                            {formatMoney(sale.subtotal_amount, {
                                fromCents: true,
                                fallback: '-',
                            })}
                        </span>
                    </div>
                    <div className="flex justify-between gap-3">
                        <span className="text-muted-foreground">Desconto</span>
                        <span className="tabular-nums">
                            {formatMoney(sale.discount_amount, {
                                fromCents: true,
                                fallback: '-',
                            })}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function SummaryLine({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex justify-between gap-3',
                strong && 'border-t pt-3 text-base font-semibold',
            )}
        >
            <span className={cn(!strong && 'text-muted-foreground')}>
                {label}
            </span>
            <span className="min-w-0 truncate text-right tabular-nums">
                {value}
            </span>
        </div>
    );
}

function SaleSummaryCard({ sale, items }: { sale: Sale; items: SaleItem[] }) {
    return (
        <Card className="gap-0">
            <CardHeader className="border-b pb-4">
                <CardTitle>Resumo</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 pt-4 text-sm">
                <SummaryLine label="Cliente" value={sale.client?.name ?? '-'} />
                <SummaryLine
                    label="Empresa"
                    value={sale.company?.nickname ?? sale.company?.name ?? '-'}
                />
                <SummaryLine
                    label="Vendedor"
                    value={sale.seller?.name ?? '-'}
                />
                <SummaryLine
                    label="Itens"
                    value={itemCountLabel(items.length)}
                />
                <SummaryLine
                    label="Subtotal"
                    value={formatMoney(sale.subtotal_amount, {
                        fromCents: true,
                        fallback: '-',
                    })}
                />
                <SummaryLine
                    label="Desconto"
                    value={formatMoney(sale.discount_amount, {
                        fromCents: true,
                        fallback: '-',
                    })}
                />
                <SummaryLine
                    label="Total"
                    value={formatMoney(sale.total_amount, {
                        fromCents: true,
                        fallback: '-',
                    })}
                    strong
                />
            </CardContent>
        </Card>
    );
}

function SaleHistoryCard({ sale }: { sale: Sale }) {
    return (
        <Card className="gap-0">
            <CardHeader className="border-b pb-4">
                <CardTitle>Histórico</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 pt-4 text-sm text-muted-foreground">
                <p>Criada {formatDate(sale.created_at)}</p>
                {sale.confirmed_at ? (
                    <p>Confirmada {formatDate(sale.confirmed_at)}</p>
                ) : null}
                {sale.finished_at ? (
                    <p>Finalizada {formatDate(sale.finished_at)}</p>
                ) : null}
                {sale.canceled_at ? (
                    <p>Cancelada {formatDate(sale.canceled_at)}</p>
                ) : null}
                {sale.notes ? (
                    <div className="border-t pt-3">
                        <p className="font-medium text-foreground">
                            Observações
                        </p>
                        <p className="mt-1 whitespace-pre-wrap">{sale.notes}</p>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function ShowSale({
    saleData,
    formOptions,
    permissions,
}: Props) {
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
                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_400px] 2xl:grid-cols-[minmax(0,1fr)_430px]">
                        <main className="min-w-0">
                            <ItemForm sale={sale} formOptions={formOptions} />
                        </main>

                        <aside className="min-w-0 xl:sticky xl:top-4 xl:self-start">
                            <div className="xl:max-h-[calc(100dvh-2rem)] xl:overflow-y-auto xl:pr-1">
                                <SaleItemsPanel sale={sale} items={items} />
                            </div>

                            <div className="mt-5">
                                <SaleSummaryCard sale={sale} items={items} />
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </>
    );
}
