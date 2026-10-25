import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    BarChart3,
    Braces,
    CalendarDays,
    ExternalLink,
    Info,
    Link2,
    ListMusic,
    ListOrdered,
    Pencil,
    Plus,
    Radio,
    Save,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import {
    destroy as detachPlaylistTop,
    store as attachPlaylistTop,
    update as configurePlaylistTop,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistTopController';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SurfaceProvider } from '@/components/ui/fluid/lib/surface-context';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatMoney } from '@/lib/utils';
import type {
    PlaylistResource,
    PlaylistTop,
    PlaylistTopCollectionResource,
    Top,
    TopCollectionResource,
} from '@/types/app/playlist';

import { PlaylistStatusMenu } from './components/playlist-status-menu';
import { PlaylistSubNav } from './components/playlist-sub-nav';

interface Props {
    playlistData: PlaylistResource;
    playlistTops: PlaylistTopCollectionResource;
    topOptions: TopCollectionResource;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    playlistTopPermissions: {
        can_attach: boolean;
        can_configure: boolean;
        can_detach: boolean;
    };
}

function cleanValue(value: unknown): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '-';
}

function InfoField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm break-words">{value}</p>
        </div>
    );
}

function CompactTopField({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 lg:text-right">
            <p className="text-xs font-medium text-muted-foreground lg:hidden">
                {label}
            </p>
            <p className="mt-0.5 text-sm break-words tabular-nums">{value}</p>
        </div>
    );
}

function metadataSummary(
    metadata: PlaylistResource['data']['external_metadata'],
): string {
    if (!metadata) {
        return '{}';
    }

    return JSON.stringify(metadata, null, 2);
}

function centsToDecimal(value: number | undefined): string {
    return ((value ?? 0) / 100).toFixed(2);
}

function topLabel(top: Top): string {
    return `${top.name} (${top.default_slots_quantity} vagas padrão)`;
}

interface PlaylistTopFormData {
    top_id: string;
    slots_quantity: string;
    price: string;
    metrics: {
        estimated_streams: string;
        estimated_reach: string;
        average_retention_days: string;
        commercial_score: string;
    };
    notes: string;
    is_active: boolean;
}

function emptyPlaylistTopForm(top?: Top): PlaylistTopFormData {
    return {
        top_id: top ? String(top.id) : '',
        slots_quantity: top ? String(top.default_slots_quantity) : '',
        price: '0.00',
        metrics: {
            estimated_streams: '',
            estimated_reach: '',
            average_retention_days: '',
            commercial_score: '',
        },
        notes: '',
        is_active: true,
    };
}

function formFromPlaylistTop(playlistTop: PlaylistTop): PlaylistTopFormData {
    return {
        top_id: String(playlistTop.top_id),
        slots_quantity: String(playlistTop.slots_quantity),
        price: centsToDecimal(playlistTop.price),
        metrics: {
            estimated_streams:
                playlistTop.metrics?.estimated_streams?.toString() ?? '',
            estimated_reach:
                playlistTop.metrics?.estimated_reach?.toString() ?? '',
            average_retention_days:
                playlistTop.metrics?.average_retention_days?.toString() ?? '',
            commercial_score:
                playlistTop.metrics?.commercial_score?.toString() ?? '',
        },
        notes: playlistTop.notes ?? '',
        is_active: playlistTop.is_active,
    };
}

function PlaylistTopsPanel({
    playlist,
    playlistTops,
    topOptions,
    permissions,
}: {
    playlist: PlaylistResource['data'];
    playlistTops: PlaylistTop[];
    topOptions: Top[];
    permissions: Props['playlistTopPermissions'];
}) {
    const [isAttachOpen, setIsAttachOpen] = useState(false);
    const [editingPlaylistTop, setEditingPlaylistTop] =
        useState<PlaylistTop | null>(null);
    const [playlistTopToDelete, setPlaylistTopToDelete] =
        useState<PlaylistTop | null>(null);
    const attachForm = useForm<PlaylistTopFormData>(
        emptyPlaylistTopForm(topOptions[0]),
    );
    const configureForm = useForm<PlaylistTopFormData>(emptyPlaylistTopForm());

    function openAttachDialog(): void {
        attachForm.setDefaults(emptyPlaylistTopForm(topOptions[0]));
        attachForm.reset();
        setIsAttachOpen(true);
    }

    function openConfigureDialog(playlistTop: PlaylistTop): void {
        const payload = formFromPlaylistTop(playlistTop);

        configureForm.setDefaults(payload);
        configureForm.setData(payload);
        setEditingPlaylistTop(playlistTop);
    }

    function submitAttach(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        attachForm.post(attachPlaylistTop.url(playlist.uuid), {
            preserveScroll: true,
            onSuccess: () => setIsAttachOpen(false),
        });
    }

    function submitConfigure(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!editingPlaylistTop) {
            return;
        }

        configureForm.patch(
            configurePlaylistTop.url([playlist.uuid, editingPlaylistTop.uuid]),
            {
                preserveScroll: true,
                onSuccess: () => setEditingPlaylistTop(null),
            },
        );
    }

    function detachTop(): void {
        if (!playlistTopToDelete) {
            return;
        }

        router.delete(
            detachPlaylistTop.url([playlist.uuid, playlistTopToDelete.uuid]),
            {
                preserveScroll: true,
                onSuccess: () => setPlaylistTopToDelete(null),
            },
        );
    }

    function updateSelectedTop(topId: string): void {
        const selectedTop = topOptions.find((top) => String(top.id) === topId);

        attachForm.setData((data) => ({
            ...data,
            top_id: topId,
            slots_quantity: selectedTop
                ? String(selectedTop.default_slots_quantity)
                : data.slots_quantity,
        }));
    }

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>TOPs comercializáveis</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Capacidade, preço sugerido, métricas e ocupação por TOP.
                    </p>
                </div>
                {permissions.can_attach ? (
                    <Button type="button" size="sm" onClick={openAttachDialog}>
                        <Plus className="size-4" aria-hidden="true" />
                        Adicionar TOP
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent>
                {playlistTops.length === 0 ? (
                    <EmptyState
                        icon={ListOrdered}
                        title="Nenhum TOP vinculado"
                        description="Vincule um TOP para definir capacidade, preço e métricas desta playlist."
                        action={
                            permissions.can_attach ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={openAttachDialog}
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Adicionar TOP
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <div className="hidden grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(5rem,0.55fr))_4.5rem] gap-3 border-b border-separator bg-muted px-4 py-2 text-xs font-semibold text-muted-foreground lg:grid">
                            <span>TOP</span>
                            <span className="text-right">Capacidade</span>
                            <span className="text-right">Ocupadas</span>
                            <span className="text-right">Disponíveis</span>
                            <span className="text-right">Valor</span>
                            <span className="text-right">Score</span>
                            <span className="sr-only">Ações</span>
                        </div>
                        <ul className="divide-y divide-separator">
                            {playlistTops.map((playlistTop) => {
                                const topName =
                                    playlistTop.top?.name ?? 'TOP removido';

                                return (
                                    <li
                                        key={playlistTop.uuid}
                                        className="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(5rem,0.55fr))_4.5rem] lg:items-center"
                                    >
                                        <div className="min-w-0">
                                            <h3 className="truncate text-sm font-semibold">
                                                {topName}
                                            </h3>
                                            {playlistTop.notes ? (
                                                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {playlistTop.notes}
                                                </p>
                                            ) : null}
                                        </div>
                                        <CompactTopField
                                            label="Capacidade"
                                            value={String(
                                                playlistTop.slots_quantity,
                                            )}
                                        />
                                        <CompactTopField
                                            label="Ocupadas"
                                            value={String(
                                                playlistTop.occupied_slots_count,
                                            )}
                                        />
                                        <CompactTopField
                                            label="Disponíveis"
                                            value={String(
                                                playlistTop.available_slots_count,
                                            )}
                                        />
                                        <CompactTopField
                                            label="Valor"
                                            value={formatMoney(
                                                playlistTop.price,
                                                {
                                                    fromCents: true,
                                                },
                                            )}
                                        />
                                        <CompactTopField
                                            label="Score"
                                            value={cleanValue(
                                                playlistTop.metrics
                                                    ?.commercial_score,
                                            )}
                                        />
                                        <div className="flex items-center gap-1 lg:justify-end">
                                            {permissions.can_configure ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    onClick={() =>
                                                        openConfigureDialog(
                                                            playlistTop,
                                                        )
                                                    }
                                                    aria-label={`Configurar ${topName}`}
                                                    title="Configurar"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            ) : null}
                                            {permissions.can_detach ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 hover:bg-destructive-soft hover:text-destructive-soft-foreground focus-visible:bg-destructive-soft focus-visible:text-destructive-soft-foreground"
                                                    onClick={() =>
                                                        setPlaylistTopToDelete(
                                                            playlistTop,
                                                        )
                                                    }
                                                    aria-label={`Remover ${topName}`}
                                                    title="Remover"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </CardContent>

            <Dialog open={isAttachOpen} onOpenChange={setIsAttachOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Adicionar TOP à playlist</DialogTitle>
                        <DialogDescription>
                            O vínculo recebe a capacidade padrão do catálogo,
                            mas pode ser ajustado para esta playlist.
                        </DialogDescription>
                    </DialogHeader>
                    <SurfaceProvider value={3}>
                        <form onSubmit={submitAttach} className="space-y-4">
                            <TopConfigurationFields
                                form={attachForm}
                                topOptions={topOptions}
                                onTopChange={updateSelectedTop}
                                includeTopSelector
                            />
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsAttachOpen(false)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={attachForm.processing}
                                >
                                    <Link2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {attachForm.processing
                                        ? 'Salvando...'
                                        : 'Vincular TOP'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </SurfaceProvider>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingPlaylistTop !== null}
                onOpenChange={(open) => !open && setEditingPlaylistTop(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Configurar TOP da playlist</DialogTitle>
                        <DialogDescription>
                            Ocupação atual é somente leitura nesta fase.
                        </DialogDescription>
                    </DialogHeader>
                    <SurfaceProvider value={3}>
                        <form onSubmit={submitConfigure} className="space-y-4">
                            <TopConfigurationFields
                                form={configureForm}
                                topOptions={topOptions}
                            />
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditingPlaylistTop(null)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={configureForm.processing}
                                >
                                    <Save
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {configureForm.processing
                                        ? 'Salvando...'
                                        : 'Salvar configuração'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </SurfaceProvider>
                </DialogContent>
            </Dialog>

            <Dialog
                open={playlistTopToDelete !== null}
                onOpenChange={(open) => !open && setPlaylistTopToDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remover TOP da playlist</DialogTitle>
                        <DialogDescription>
                            O vínculo será arquivado e deixará de ser ofertado
                            para novas operações.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPlaylistTopToDelete(null)}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={detachTop}>
                            <Trash2 className="size-4" aria-hidden="true" />
                            Remover
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function TopConfigurationFields({
    form,
    topOptions,
    includeTopSelector = false,
    onTopChange,
}: {
    form: ReturnType<typeof useForm<PlaylistTopFormData>>;
    topOptions: Top[];
    includeTopSelector?: boolean;
    onTopChange?: (topId: string) => void;
}) {
    const errors = form.errors as Record<string, string>;

    return (
        <div className="space-y-4">
            {includeTopSelector ? (
                <FormField id="field-top-id" label="TOP" error={errors.top_id}>
                    <Select
                        value={form.data.top_id}
                        onValueChange={(value) =>
                            onTopChange
                                ? onTopChange(value)
                                : form.setData('top_id', value)
                        }
                    >
                        <SelectTrigger
                            id="field-top-id"
                            className="w-full"
                            placeholder="Selecione um TOP"
                        />
                        <SelectContent>
                            {topOptions.map((top, index) => (
                                <SelectItem
                                    key={top.id}
                                    index={index}
                                    value={String(top.id)}
                                >
                                    {topLabel(top)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Quantidade de vagas"
                    error={errors.slots_quantity}
                >
                    <Input
                        id="field-slots-quantity"
                        type="number"
                        min={1}
                        step={1}
                        value={form.data.slots_quantity}
                        onChange={(event) =>
                            form.setData('slots_quantity', event.target.value)
                        }
                        inputMode="numeric"
                    />
                </FormField>

                <FormField label="Valor sugerido" error={errors.price}>
                    <InputMoney
                        id="field-price"
                        value={form.data.price}
                        onChange={(value) => form.setData('price', value)}
                    />
                </FormField>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <MetricInput
                    label="Streams estimados"
                    value={form.data.metrics.estimated_streams}
                    error={errors['metrics.estimated_streams']}
                    onChange={(value) =>
                        form.setData('metrics', {
                            ...form.data.metrics,
                            estimated_streams: value,
                        })
                    }
                />
                <MetricInput
                    label="Alcance estimado"
                    value={form.data.metrics.estimated_reach}
                    error={errors['metrics.estimated_reach']}
                    onChange={(value) =>
                        form.setData('metrics', {
                            ...form.data.metrics,
                            estimated_reach: value,
                        })
                    }
                />
                <MetricInput
                    label="Retenção média em dias"
                    value={form.data.metrics.average_retention_days}
                    error={errors['metrics.average_retention_days']}
                    onChange={(value) =>
                        form.setData('metrics', {
                            ...form.data.metrics,
                            average_retention_days: value,
                        })
                    }
                />
                <MetricInput
                    label="Score comercial"
                    value={form.data.metrics.commercial_score}
                    error={errors['metrics.commercial_score']}
                    onChange={(value) =>
                        form.setData('metrics', {
                            ...form.data.metrics,
                            commercial_score: value,
                        })
                    }
                />
            </div>

            <FormField label="Observações" error={errors.notes}>
                <Textarea
                    id="field-playlist-top-notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    rows={3}
                />
            </FormField>

            <div className="flex min-h-16 items-center justify-between gap-4 rounded-lg border border-border bg-muted px-4 py-3">
                <div className="space-y-1">
                    <Label htmlFor="field-playlist-top-active">Ativo</Label>
                    <p className="text-xs text-muted-foreground">
                        Disponível para novas vendas e reservas futuras.
                    </p>
                </div>
                <Switch
                    id="field-playlist-top-active"
                    checked={form.data.is_active}
                    onCheckedChange={(value) =>
                        form.setData('is_active', value)
                    }
                />
            </div>
        </div>
    );
}

function MetricInput({
    label,
    value,
    error,
    onChange,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    const id = `metric-${label.toLowerCase().replaceAll(' ', '-')}`;

    return (
        <FormField label={label} error={error}>
            <Input
                id={id}
                type="number"
                min={0}
                step={1}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                inputMode="numeric"
            />
        </FormField>
    );
}

export default function ShowPlaylist({
    playlistData,
    playlistTops,
    topOptions,
    permissions,
    playlistTopPermissions,
}: Props) {
    const playlist = playlistData.data;
    const [tab, setTab] = useState('summary');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: index.url() },
            { title: playlist.name, href: show.url(playlist.uuid) },
        ],
    });

    return (
        <>
            <Head title={playlist.name} />

            <Header title={playlist.name} backHref={index.url()}>
                <div className="flex flex-wrap items-center gap-2">
                    <PlaylistSubNav
                        playlistUuid={playlist.uuid}
                        currentPage="show"
                        canUpdate={permissions.can_update}
                    />
                    <Button variant="outline" asChild>
                        <Link
                            href={`/playlists/metrics?search=${encodeURIComponent(playlist.name)}`}
                        >
                            <BarChart3 className="size-4" aria-hidden="true" />
                            Métricas
                        </Link>
                    </Button>
                    {permissions.can_delete ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <Button variant="outline" asChild>
                                <Link
                                    href={destroy.url(playlist.uuid)}
                                    method="delete"
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Arquivar
                                </Link>
                            </Button>
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <Card className="gap-0 py-0">
                            <div className="p-5">
                                {playlist.cover_image?.url ? (
                                    <img
                                        src={playlist.cover_image.url}
                                        alt=""
                                        className="h-28 w-full rounded-lg object-cover"
                                        width={240}
                                        height={112}
                                    />
                                ) : (
                                    <div className="flex h-28 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
                                        <ListMusic
                                            className="size-12"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}

                                <div className="mt-4 space-y-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <h2 className="truncate text-base font-semibold">
                                            {playlist.name}
                                        </h2>
                                        {permissions.can_update ? (
                                            <PlaylistStatusMenu
                                                uuid={playlist.uuid}
                                                status={playlist.status}
                                                size="sm"
                                                align="end"
                                            />
                                        ) : (
                                            <StatusBadge
                                                presenter={
                                                    playlist.status_presenter
                                                }
                                            />
                                        )}
                                    </div>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {playlist.genre?.name ?? 'Sem gênero'}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-2 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <Users
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="tabular-nums">
                                            {playlist.followers_count?.toLocaleString(
                                                'pt-BR',
                                            ) ?? '-'}{' '}
                                            seguidores
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Radio
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="tabular-nums">
                                            {playlist.estimated_reach?.toLocaleString(
                                                'pt-BR',
                                            ) ?? '-'}{' '}
                                            alcance
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t border-separator px-5 py-4 text-xs text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <CalendarDays
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span className="tabular-nums">
                                        Criada {formatDate(playlist.created_at)}
                                    </span>
                                </div>
                                <p className="mt-1 tabular-nums">
                                    Última edição{' '}
                                    {formatDate(playlist.updated_at)}
                                </p>
                            </div>
                        </Card>
                    </aside>

                    <main className="min-w-0">
                        <Tabs
                            value={tab}
                            onValueChange={setTab}
                            className="space-y-4"
                        >
                            <TabsList
                                aria-label="Seções da playlist"
                                className="max-w-full overflow-x-auto"
                            >
                                <TabItem
                                    value="summary"
                                    label="Resumo"
                                    icon={Info}
                                />
                                <TabItem
                                    value="links"
                                    label="Links"
                                    icon={Link2}
                                />
                                <TabItem
                                    value="tops"
                                    label="TOPs"
                                    icon={ListOrdered}
                                />
                                <TabItem
                                    value="metadata"
                                    label="Metadata"
                                    icon={Braces}
                                />
                            </TabsList>

                            <TabPanel value="summary" className="space-y-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Dados comerciais</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <InfoField
                                            label="Nome"
                                            value={playlist.name}
                                        />
                                        <InfoField
                                            label="Slug"
                                            value={playlist.slug}
                                        />
                                        <InfoField
                                            label="Gênero"
                                            value={playlist.genre?.name ?? '-'}
                                        />
                                        <InfoField
                                            label="Subgêneros"
                                            value={
                                                playlist.subgenres.length > 0
                                                    ? playlist.subgenres
                                                          .map(
                                                              (subgenre) =>
                                                                  subgenre.name,
                                                          )
                                                          .join(', ')
                                                    : '-'
                                            }
                                        />
                                        <InfoField
                                            label="Parceiro"
                                            value={cleanValue(
                                                playlist.partner_name,
                                            )}
                                        />
                                    </CardContent>
                                </Card>

                                {playlist.notes ? (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Observações</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                                {playlist.notes}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ) : null}
                            </TabPanel>

                            <TabPanel value="links">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Referências externas
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <InfoField
                                            label="ID público Spotify"
                                            value={cleanValue(
                                                playlist.spotify_playlist_id,
                                            )}
                                        />
                                        <InfoField
                                            label="Link Spotify"
                                            value={cleanValue(
                                                playlist.spotify_url,
                                            )}
                                        />
                                        <InfoField
                                            label="Link YouTube"
                                            value={cleanValue(
                                                playlist.youtube_url,
                                            )}
                                        />
                                        <div className="flex flex-wrap items-end gap-2">
                                            {playlist.spotify_url ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            playlist.spotify_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <ExternalLink
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Spotify
                                                    </a>
                                                </Button>
                                            ) : null}
                                            {playlist.youtube_url ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            playlist.youtube_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <ExternalLink
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        YouTube
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabPanel>

                            <TabPanel value="tops">
                                <PlaylistTopsPanel
                                    playlist={playlist}
                                    playlistTops={playlistTops.data}
                                    topOptions={topOptions.data}
                                    permissions={playlistTopPermissions}
                                />
                            </TabPanel>

                            <TabPanel value="metadata">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Metadata legada</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-[360px] overflow-auto rounded-lg border border-border bg-muted p-4 text-xs leading-relaxed text-muted-foreground">
                                            <code>
                                                {metadataSummary(
                                                    playlist.external_metadata,
                                                )}
                                            </code>
                                        </pre>
                                    </CardContent>
                                </Card>
                            </TabPanel>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
