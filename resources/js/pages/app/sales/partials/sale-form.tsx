import { Link, useForm } from '@inertiajs/react';
import { ListPlus, Music2, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
    store,
    update,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn, formatMoney } from '@/lib/utils';
import type {
    Sale,
    SaleFormOption,
    SaleFormOptions,
    SaleItem,
} from '@/types/app/sale';

import { ClientQuickCreateDialog } from './client-quick-create-dialog';
import { PlaylistItemDialog } from './playlist-item-dialog';
import type { ItemOptionMaps, PlaylistLineForm } from './playlist-item-dialog';

interface Props {
    sale?: Sale;
    formOptions: SaleFormOptions;
    cancelHref: string;
}

interface FormSectionProps {
    title: string;
    description: string;
    children: ReactNode;
}

interface ServiceLineForm {
    id: string;
    uuid?: string;
    type: 'service';
    service_id: string;
    quantity: string;
    unit_amount: string;
}

type SaleItemLineForm = ServiceLineForm | PlaylistLineForm;

interface SaleFormData {
    company_id: string;
    client_id: string;
    title: string;
    notes: string;
    discount_amount: string;
    items: SaleItemLineForm[];
}

type FieldErrors = Record<string, string | undefined>;

const emptyServiceLine = (): ServiceLineForm => ({
    id: uniqueId('service'),
    type: 'service',
    service_id: '',
    quantity: '1',
    unit_amount: '0.00',
});

function firstFormError(errors: FieldErrors): string {
    return (
        Object.values(errors).find(
            (error): error is string =>
                typeof error === 'string' && error.trim() !== '',
        ) ?? 'Verifique os campos obrigatórios.'
    );
}

function uniqueId(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function centsToDecimal(value: number | undefined): string {
    return ((value ?? 0) / 100).toFixed(2);
}

function centsFromDecimal(value: string): number {
    const trimmed = value.trim();

    if (trimmed === '') {
        return 0;
    }

    const normalized = trimmed.includes(',')
        ? trimmed.replace(/\./g, '').replace(',', '.')
        : trimmed;
    const amount = Number(normalized);

    return Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100)) : 0;
}

function quantityFromInput(value: string): number {
    const quantity = Number.parseInt(value, 10);

    return Number.isFinite(quantity) ? Math.max(1, quantity) : 1;
}

function FormSection({ title, description, children }: FormSectionProps) {
    return (
        <section className="grid grid-cols-1 gap-x-8 gap-y-4 py-6 md:grid-cols-[minmax(0,240px)_minmax(0,1fr)] lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
            <div className="px-1 md:px-0">
                <h2 className="text-sm leading-6 font-semibold">{title}</h2>
                <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>

            <div className="min-w-0">{children}</div>
        </section>
    );
}

function lineTotal(line: SaleItemLineForm): number {
    return (
        quantityFromInput(line.quantity) * centsFromDecimal(line.unit_amount)
    );
}

function serviceLabel(service: SaleFormOption): string {
    return `${service.name} - ${formatMoney(service.base_price ?? 0, {
        fromCents: true,
        fallback: 'R$ 0,00',
    })}`;
}

function clientDescription(client: SaleFormOption): string | undefined {
    const description = [client.phone, client.email]
        .filter((value): value is string => Boolean(value))
        .join(' · ');

    return description || undefined;
}

function clientToSelectOption(client: SaleFormOption): AsyncSelectOption {
    return {
        value: String(client.id),
        label: client.name,
        description: clientDescription(client),
    };
}

function transformClientOption(item: unknown): AsyncSelectOption {
    if (typeof item === 'object' && item !== null) {
        const record = item as Partial<SaleFormOption> & {
            value?: number | string;
            label?: string;
        };
        const client: SaleFormOption = {
            id: Number(record.id ?? record.value ?? 0),
            uuid: String(record.uuid ?? ''),
            name: String(record.name ?? record.label ?? ''),
            email: record.email,
            phone: record.phone,
        };

        return clientToSelectOption(client);
    }

    return {
        value: String(item),
        label: String(item),
    };
}

function itemTitle(
    line: SaleItemLineForm,
    optionsById: ItemOptionMaps,
): string {
    if (line.type === 'service') {
        return optionsById.services.get(line.service_id)?.name ?? 'Serviço';
    }

    return (
        optionsById.playlistTops.get(line.playlist_top_id)?.name ?? 'Playlist'
    );
}

function itemSubtitle(
    line: SaleItemLineForm,
    optionsById: ItemOptionMaps,
): string {
    if (line.type === 'service') {
        return `${quantityFromInput(line.quantity)} x ${formatMoney(
            centsFromDecimal(line.unit_amount),
            { fromCents: true },
        )}`;
    }

    const playlist = optionsById.playlists.get(line.playlist_id)?.name;

    return `${playlist ?? 'Playlist'} · ${line.track_name || 'Música sem nome'}`;
}

function itemArtworkUrl(
    line: SaleItemLineForm,
    optionsById: ItemOptionMaps,
): string | null {
    if (line.type === 'service') {
        return null;
    }

    const playlist = optionsById.playlists.get(line.playlist_id);
    const playlistTop = optionsById.playlistTops.get(line.playlist_top_id);

    return (
        playlist?.cover_image_url ??
        playlistTop?.cover_image_url ??
        playlist?.spotify_image_url ??
        playlistTop?.spotify_image_url ??
        null
    );
}

function saleItemToLine(
    item: SaleItem,
    optionsById: ItemOptionMaps,
): SaleItemLineForm {
    if (item.type === 'service') {
        return {
            id: `service-${item.uuid}`,
            uuid: item.uuid,
            type: 'service',
            service_id: item.service_id ? String(item.service_id) : '',
            quantity: String(item.quantity),
            unit_amount: centsToDecimal(item.unit_amount),
        };
    }

    const playlistTop = item.playlist_top_id
        ? optionsById.playlistTops.get(String(item.playlist_top_id))
        : undefined;

    return {
        id: `playlist-${item.uuid}`,
        uuid: item.uuid,
        type: 'playlist_slot',
        playlist_id: playlistTop?.playlist_id
            ? String(playlistTop.playlist_id)
            : '',
        playlist_top_id: item.playlist_top_id
            ? String(item.playlist_top_id)
            : '',
        quantity: String(item.quantity),
        unit_amount: centsToDecimal(item.unit_amount),
        starts_on: item.starts_on ?? '',
        ends_on: item.ends_on ?? '',
        track_name: item.track_name ?? '',
        track_spotify_url: item.track_spotify_url ?? '',
        track_artists: item.track_artists ?? undefined,
        track_album_name: item.track_album_name ?? null,
        track_duration_ms: item.track_duration_ms ?? null,
        track_isrc: item.track_isrc ?? null,
        track_image_url: item.track_image_url ?? null,
    };
}

function lineToPayload(item: SaleItemLineForm) {
    if (item.type === 'service') {
        return {
            uuid: item.uuid,
            type: item.type,
            service_id: Number(item.service_id),
            quantity: quantityFromInput(item.quantity),
            unit_amount: centsFromDecimal(item.unit_amount),
        };
    }

    return {
        uuid: item.uuid,
        type: item.type,
        playlist_top_id: Number(item.playlist_top_id),
        quantity: quantityFromInput(item.quantity),
        unit_amount: centsFromDecimal(item.unit_amount),
        starts_on: item.starts_on.trim() === '' ? null : item.starts_on,
        ends_on: item.ends_on.trim() === '' ? null : item.ends_on,
        track_name: item.track_name,
        track_spotify_url: item.track_spotify_url,
        track_artists: item.track_artists,
        track_album_name: item.track_album_name,
        track_duration_ms: item.track_duration_ms,
        track_isrc: item.track_isrc,
        track_image_url: item.track_image_url,
        track_metadata: item.track_image_url
            ? { image_url: item.track_image_url }
            : undefined,
    };
}

export default function SaleForm({ sale, formOptions, cancelHref }: Props) {
    const isEditing = Boolean(sale);
    const [clientDialogOpen, setClientDialogOpen] = useState(false);
    const [createdClients, setCreatedClients] = useState<SaleFormOption[]>([]);
    const [playlistDialogOpen, setPlaylistDialogOpen] = useState(false);
    const [editingPlaylistLine, setEditingPlaylistLine] =
        useState<PlaylistLineForm | null>(null);
    const optionsById = useMemo<ItemOptionMaps>(
        () => ({
            services: new Map(
                formOptions.services.map((service) => [
                    String(service.id),
                    service,
                ]),
            ),
            playlists: new Map(
                formOptions.playlists.map((playlist) => [
                    String(playlist.id),
                    playlist,
                ]),
            ),
            playlistTops: new Map(
                formOptions.playlist_tops.map((playlistTop) => [
                    String(playlistTop.id),
                    playlistTop,
                ]),
            ),
        }),
        [
            formOptions.playlist_tops,
            formOptions.playlists,
            formOptions.services,
        ],
    );
    const initialItems = useMemo(
        () =>
            (sale?.sale_items ?? []).map((item) =>
                saleItemToLine(item, optionsById),
            ),
        [optionsById, sale?.sale_items],
    );

    const form = useForm<SaleFormData>({
        company_id: sale?.company_id ? String(sale.company_id) : '',
        client_id: sale?.client_id ? String(sale.client_id) : '',
        title: sale?.title ?? '',
        notes: sale?.notes ?? '',
        discount_amount: centsToDecimal(sale?.discount_amount),
        items: initialItems,
    });

    const fieldErrors = form.errors as FieldErrors;
    const subtotalAmount = form.data.items.reduce(
        (total, item) => total + lineTotal(item),
        0,
    );
    const clientOptions = useMemo(
        () =>
            [...createdClients, ...formOptions.clients].map(
                clientToSelectOption,
            ),
        [createdClients, formOptions.clients],
    );
    const selectedClientLabel = clientOptions.find(
        (client) => client.value === form.data.client_id,
    )?.label;

    function selectCreatedClient(client: SaleFormOption): void {
        setCreatedClients((currentClients) => [
            client,
            ...currentClients.filter(
                (currentClient) => currentClient.id !== client.id,
            ),
        ]);
        form.setData('client_id', String(client.id));
    }

    function addServiceLine(): void {
        form.setData('items', [...form.data.items, emptyServiceLine()]);
    }

    function addPlaylistLine(line: PlaylistLineForm): void {
        form.setData('items', [...form.data.items, line]);
    }

    function savePlaylistLine(line: PlaylistLineForm): void {
        if (editingPlaylistLine) {
            form.setData(
                'items',
                form.data.items.map((item) =>
                    item.id === editingPlaylistLine.id ? line : item,
                ),
            );
            setEditingPlaylistLine(null);

            return;
        }

        addPlaylistLine(line);
    }

    function openNewPlaylistDialog(): void {
        setEditingPlaylistLine(null);
        setPlaylistDialogOpen(true);
    }

    function openEditPlaylistDialog(line: PlaylistLineForm): void {
        setEditingPlaylistLine(line);
        setPlaylistDialogOpen(true);
    }

    function closePlaylistDialog(open: boolean): void {
        setPlaylistDialogOpen(open);

        if (!open) {
            setEditingPlaylistLine(null);
        }
    }

    function removeItem(lineId: string): void {
        form.setData(
            'items',
            form.data.items.filter((item) => item.id !== lineId),
        );
    }

    function updateServiceLine(
        lineId: string,
        field: 'service_id' | 'quantity' | 'unit_amount',
        value: string,
    ): void {
        form.setData(
            'items',
            form.data.items.map((item) => {
                if (item.id !== lineId) {
                    return item;
                }

                if (item.type !== 'service') {
                    return item;
                }

                if (field === 'service_id') {
                    const service = optionsById.services.get(String(value));

                    return {
                        ...item,
                        service_id: String(value),
                        unit_amount: centsToDecimal(service?.base_price),
                    };
                }

                return { ...item, [field]: value };
            }),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform((data) => {
            const basePayload = {
                company_id: Number(data.company_id),
                client_id: Number(data.client_id),
                title: data.title,
                notes: data.notes,
            };
            const items = data.items
                .filter((item) => {
                    if (item.type === 'service') {
                        return item.service_id !== '';
                    }

                    return (
                        item.playlist_top_id !== '' &&
                        item.track_name.trim() !== '' &&
                        item.track_spotify_url.trim() !== ''
                    );
                })
                .map(lineToPayload);

            if (isEditing) {
                return {
                    ...basePayload,
                    discount_amount: centsFromDecimal(data.discount_amount),
                    items,
                };
            }

            return {
                ...basePayload,
                items,
            };
        });

        const options = {
            preserveScroll: true,
            onError: (errors: FieldErrors) =>
                toast.error(firstFormError(errors)),
        };

        if (isEditing && sale) {
            form.patch(update.url(sale.uuid), options);

            return;
        }

        form.post(store.url(), options);
    }

    return (
        <>
            <form onSubmit={submit} className="divide-y divide-border">
                <FormSection
                    title="Dados comerciais"
                    description="Defina a empresa, o cliente e a identificação do rascunho comercial antes de compor os itens."
                >
                    <Card className="overflow-hidden py-0 shadow-xs">
                        <CardContent className="grid gap-4 p-4 sm:p-6">
                            <div className="grid gap-4 lg:grid-cols-2">
                                <div className="grid gap-2">
                                    <div className="flex min-h-7 items-center">
                                        <Label htmlFor="company_id">
                                            Empresa
                                        </Label>
                                    </div>
                                    <Select
                                        value={form.data.company_id}
                                        onValueChange={(value) =>
                                            form.setData('company_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="company_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Selecione a empresa" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {formOptions.companies.map(
                                                (company) => (
                                                    <SelectItem
                                                        key={company.id}
                                                        value={String(
                                                            company.id,
                                                        )}
                                                    >
                                                        {company.nickname ??
                                                            company.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={fieldErrors.company_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex min-h-7 items-center justify-between gap-2">
                                        <Label>Cliente</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 shrink-0 px-2 text-xs"
                                            onClick={() =>
                                                setClientDialogOpen(true)
                                            }
                                        >
                                            <Plus className="size-3.5" />
                                            Novo cliente
                                        </Button>
                                    </div>
                                    <AsyncSelect
                                        endpoint={apiRoutes.clients.index}
                                        options={clientOptions}
                                        value={form.data.client_id || null}
                                        initialLabel={selectedClientLabel}
                                        placeholder="Selecione um cliente"
                                        searchPlaceholder="Buscar cliente..."
                                        emptyMessage="Nenhum cliente encontrado."
                                        clearable
                                        transformOption={transformClientOption}
                                        onChange={(value) =>
                                            form.setData(
                                                'client_id',
                                                value ?? '',
                                            )
                                        }
                                    />
                                    <InputError
                                        message={fieldErrors.client_id}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Campanha, pacote ou negociação"
                                />
                                <InputError message={fieldErrors.title} />
                            </div>

                            {isEditing ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="discount_amount">
                                        Desconto da venda
                                    </Label>
                                    <InputMoney
                                        id="discount_amount"
                                        value={form.data.discount_amount}
                                        onChange={(value) =>
                                            form.setData(
                                                'discount_amount',
                                                value,
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Total atual:{' '}
                                        {formatMoney(sale?.total_amount, {
                                            fromCents: true,
                                            fallback: '-',
                                        })}
                                    </p>
                                    <InputError
                                        message={fieldErrors.discount_amount}
                                    />
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observações</Label>
                                <Textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    rows={5}
                                />
                                <InputError message={fieldErrors.notes} />
                            </div>
                        </CardContent>
                    </Card>
                </FormSection>

                <FormSection
                    title="Itens da venda"
                    description={
                        isEditing
                            ? 'Atualize serviços do catálogo ou vagas de playlist antes de salvar.'
                            : 'Adicione serviços ou vagas de playlist com a música e a referência do Spotify.'
                    }
                >
                    <Card className="overflow-hidden py-0 shadow-xs">
                        <CardHeader className="flex flex-col gap-3 border-b bg-muted/20 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addServiceLine}
                                >
                                    <ListPlus className="size-4" />
                                    Adicionar serviço
                                </Button>
                                <Button
                                    type="button"
                                    onClick={openNewPlaylistDialog}
                                >
                                    <Music2 className="size-4" />
                                    Adicionar playlist
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3 p-4 sm:p-6">
                            {form.data.items.length === 0 ? (
                                <div className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                    Nenhum item adicionado.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {form.data.items.map((item, index) => (
                                        <div
                                            key={item.id}
                                            className="rounded-md border bg-muted/20 p-3"
                                        >
                                            <div className="flex items-start gap-3">
                                                <span
                                                    className={cn(
                                                        'relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-background text-muted-foreground',
                                                        item.type ===
                                                            'playlist_slot' &&
                                                            'text-primary',
                                                    )}
                                                >
                                                    {item.type === 'service' ? (
                                                        <ListPlus className="size-4" />
                                                    ) : (
                                                        <>
                                                            <Music2 className="size-4" />
                                                            {itemArtworkUrl(
                                                                item,
                                                                optionsById,
                                                            ) ? (
                                                                <img
                                                                    src={
                                                                        itemArtworkUrl(
                                                                            item,
                                                                            optionsById,
                                                                        ) ?? ''
                                                                    }
                                                                    alt=""
                                                                    loading="lazy"
                                                                    decoding="async"
                                                                    className="absolute inset-0 size-full object-cover"
                                                                    onError={(
                                                                        event,
                                                                    ) =>
                                                                        event.currentTarget.classList.add(
                                                                            'hidden',
                                                                        )
                                                                    }
                                                                />
                                                            ) : null}
                                                        </>
                                                    )}
                                                </span>

                                                <div className="min-w-0 flex-1 space-y-3">
                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-semibold">
                                                                {itemTitle(
                                                                    item,
                                                                    optionsById,
                                                                )}
                                                            </p>
                                                            <p className="truncate text-xs text-muted-foreground">
                                                                {itemSubtitle(
                                                                    item,
                                                                    optionsById,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <Badge variant="secondary">
                                                            {formatMoney(
                                                                lineTotal(item),
                                                                {
                                                                    fromCents: true,
                                                                    fallback:
                                                                        'R$ 0,00',
                                                                },
                                                            )}
                                                        </Badge>
                                                    </div>

                                                    {item.type === 'service' ? (
                                                        <div className="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_110px_180px]">
                                                            <div className="grid gap-2">
                                                                <Label
                                                                    htmlFor={`items_${index}_service_id`}
                                                                >
                                                                    Serviço
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        item.service_id
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateServiceLine(
                                                                            item.id,
                                                                            'service_id',
                                                                            value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger
                                                                        id={`items_${index}_service_id`}
                                                                        className="w-full"
                                                                    >
                                                                        <SelectValue placeholder="Selecione" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {formOptions.services.map(
                                                                            (
                                                                                service,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        service.id
                                                                                    }
                                                                                    value={String(
                                                                                        service.id,
                                                                                    )}
                                                                                >
                                                                                    {serviceLabel(
                                                                                        service,
                                                                                    )}
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                                <InputError
                                                                    message={
                                                                        fieldErrors[
                                                                            `items.${index}.service_id`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label
                                                                    htmlFor={`items_${index}_quantity`}
                                                                >
                                                                    Qtd.
                                                                </Label>
                                                                <Input
                                                                    id={`items_${index}_quantity`}
                                                                    type="number"
                                                                    min="1"
                                                                    value={
                                                                        item.quantity
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateServiceLine(
                                                                            item.id,
                                                                            'quantity',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        fieldErrors[
                                                                            `items.${index}.quantity`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label
                                                                    htmlFor={`items_${index}_unit_amount`}
                                                                >
                                                                    Unitário
                                                                </Label>
                                                                <InputMoney
                                                                    id={`items_${index}_unit_amount`}
                                                                    value={
                                                                        item.unit_amount
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateServiceLine(
                                                                            item.id,
                                                                            'unit_amount',
                                                                            value,
                                                                        )
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        fieldErrors[
                                                                            `items.${index}.unit_amount`
                                                                        ]
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            <div className="grid gap-2 text-xs text-muted-foreground md:grid-cols-2">
                                                                <span className="truncate">
                                                                    TOP:{' '}
                                                                    {optionsById.playlistTops.get(
                                                                        item.playlist_top_id,
                                                                    )
                                                                        ?.top_name ??
                                                                        'Selecionado'}
                                                                </span>
                                                                <span className="truncate">
                                                                    Spotify:{' '}
                                                                    {
                                                                        item.track_spotify_url
                                                                    }
                                                                </span>
                                                            </div>
                                                            <InputError
                                                                message={
                                                                    fieldErrors[
                                                                        `items.${index}.playlist_top_id`
                                                                    ]
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    fieldErrors[
                                                                        `items.${index}.track_name`
                                                                    ]
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    fieldErrors[
                                                                        `items.${index}.track_spotify_url`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex shrink-0 flex-col gap-1">
                                                    {item.type ===
                                                    'playlist_slot' ? (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Editar playlist"
                                                            title="Editar playlist"
                                                            onClick={() =>
                                                                openEditPlaylistDialog(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                    ) : null}
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Remover item"
                                                        title="Remover item"
                                                        onClick={() =>
                                                            removeItem(item.id)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div className="flex flex-col gap-2 rounded-md border bg-background p-3 sm:flex-row sm:items-center sm:justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Subtotal previsto
                                </span>
                                <span className="text-lg font-semibold tabular-nums">
                                    {formatMoney(subtotalAmount, {
                                        fromCents: true,
                                        fallback: 'R$ 0,00',
                                    })}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </FormSection>

                <div className="sticky bottom-0 flex items-center justify-end gap-2 bg-background/95 py-4 backdrop-blur supports-backdrop-filter:bg-background/80 md:pl-[calc(240px+2rem)] lg:pl-[calc(300px+2rem)]">
                    <Button
                        type="button"
                        variant="ghost"
                        asChild
                        disabled={form.processing}
                    >
                        <Link href={cancelHref}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        <Save className="size-4" />
                        {form.processing ? 'Salvando...' : 'Salvar'}
                    </Button>
                </div>
            </form>

            {playlistDialogOpen ? (
                <PlaylistItemDialog
                    key={editingPlaylistLine?.id ?? 'new-playlist-item'}
                    open={playlistDialogOpen}
                    initialLine={editingPlaylistLine}
                    onOpenChange={closePlaylistDialog}
                    formOptions={formOptions}
                    optionsById={optionsById}
                    onSave={savePlaylistLine}
                />
            ) : null}

            <ClientQuickCreateDialog
                open={clientDialogOpen}
                onOpenChange={setClientDialogOpen}
                onCreated={selectCreatedClient}
            />
        </>
    );
}
