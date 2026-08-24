import { useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Loader2,
    Music2,
    Plus,
    Save,
    Search,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';

import SpotifyTrackLookupController from '@/actions/App/Modules/Sales/Http/Controllers/SpotifyTrackLookupController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { cn } from '@/lib/utils';
import { formatMoney } from '@/lib/utils';
import type { SaleFormOption, SaleFormOptions } from '@/types/app/sale';

export interface PlaylistLineForm {
    id: string;
    uuid?: string;
    type: 'playlist_slot';
    playlist_id: string;
    playlist_top_id: string;
    quantity: string;
    unit_amount: string;
    starts_on: string;
    ends_on: string;
    track_name: string;
    track_spotify_url: string;
    track_artists?: string[];
    track_album_name?: string | null;
    track_duration_ms?: number | null;
    track_isrc?: string | null;
    track_image_url?: string | null;
}

export interface ItemOptionMaps {
    services: Map<string, SaleFormOption>;
    playlists: Map<string, SaleFormOption>;
    playlistTops: Map<string, SaleFormOption>;
}

interface PlaylistItemDialogProps {
    open: boolean;
    initialLine?: PlaylistLineForm | null;
    formOptions: SaleFormOptions;
    optionsById: ItemOptionMaps;
    onOpenChange: (open: boolean) => void;
    onSave: (line: PlaylistLineForm) => void;
}

interface SpotifyTrackPreview {
    spotify_track_id: string;
    spotify_url: string;
    name: string | null;
    display_name: string | null;
    artist_names: string[];
    album_name: string | null;
    image_url: string | null;
    duration_ms: number | null;
    isrc: string | null;
    source: 'web_api';
}

interface SpotifyLookupIssue {
    title: string;
    message: string;
    tone: 'warning' | 'danger';
}

export const emptyPlaylistLine = (): PlaylistLineForm => ({
    id: uniqueId('playlist'),
    type: 'playlist_slot',
    playlist_id: '',
    playlist_top_id: '',
    quantity: '1',
    unit_amount: '0.00',
    starts_on: '',
    ends_on: '',
    track_name: '',
    track_spotify_url: '',
});

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

function isSpotifyTrackReference(value: string): boolean {
    const input = value.trim();

    if (/^[A-Za-z0-9]{22}$/.test(input)) {
        return true;
    }

    if (/^spotify:track:[A-Za-z0-9]{22}$/.test(input)) {
        return true;
    }

    try {
        const url = new URL(input);

        if (url.hostname !== 'open.spotify.com') {
            return false;
        }

        const segments = url.pathname.split('/').filter(Boolean);

        if (/^intl-[a-z]{2}$/.test(segments[0] ?? '')) {
            segments.shift();
        }

        return (
            segments[0] === 'track' &&
            /^[A-Za-z0-9]{22}$/.test(segments[1] ?? '')
        );
    } catch {
        return false;
    }
}

function playlistTopLabel(playlistTop: SaleFormOption): string {
    return `${playlistTop.top_name ?? playlistTop.name} - ${formatMoney(
        playlistTop.price ?? 0,
        {
            fromCents: true,
            fallback: 'R$ 0,00',
        },
    )}`;
}

function lineTotal(line: PlaylistLineForm): number {
    return (
        quantityFromInput(line.quantity) * centsFromDecimal(line.unit_amount)
    );
}

function durationLabel(durationMs: number | null): string | null {
    if (durationMs === null) {
        return null;
    }

    const totalSeconds = Math.max(0, Math.round(durationMs / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function lineArtworkUrl(
    line: PlaylistLineForm,
    optionsById: ItemOptionMaps,
): string | null {
    const playlist = optionsById.playlists.get(line.playlist_id);
    const playlistTop = optionsById.playlistTops.get(line.playlist_top_id);

    return (
        line.track_image_url ??
        playlist?.cover_image_url ??
        playlistTop?.cover_image_url ??
        playlist?.spotify_image_url ??
        playlistTop?.spotify_image_url ??
        null
    );
}

export function PlaylistItemDialog({
    open,
    initialLine,
    formOptions,
    optionsById,
    onOpenChange,
    onSave,
}: PlaylistItemDialogProps) {
    const [draft, setDraft] = useState<PlaylistLineForm>(
        initialLine ?? emptyPlaylistLine(),
    );
    const [spotifyPreview, setSpotifyPreview] =
        useState<SpotifyTrackPreview | null>(null);
    const [spotifyLookupIssue, setSpotifyLookupIssue] =
        useState<SpotifyLookupIssue | null>(null);
    const [manualTrackNameVisible, setManualTrackNameVisible] =
        useState(Boolean(initialLine?.track_name));
    const {
        post: lookupSpotifyTrack,
        setData: setSpotifyLookupData,
        processing: lookupProcessing,
    } = useHttp({
        spotify_input: draft.track_spotify_url,
    });
    const isEditing = Boolean(initialLine);
    const filteredPlaylistTops = formOptions.playlist_tops.filter(
        (playlistTop) =>
            draft.playlist_id !== '' &&
            String(playlistTop.playlist_id) === draft.playlist_id,
    );
    const selectedPlaylistTop = optionsById.playlistTops.get(
        draft.playlist_top_id,
    );
    const hasDraftTrack =
        draft.track_name.trim() !== '' && draft.track_spotify_url.trim() !== '';
    const previewTitle =
        spotifyPreview?.display_name ?? spotifyPreview?.name ?? draft.track_name;
    const previewImageUrl = spotifyPreview?.image_url ?? lineArtworkUrl(draft, optionsById);
    const previewSubtitle =
        spotifyPreview !== null
            ? [
                  spotifyPreview.album_name,
                  durationLabel(spotifyPreview.duration_ms),
              ]
                  .filter(Boolean)
                  .join(' · ') || 'Dados básicos do Spotify'
            : selectedPlaylistTop?.name;

    function close(): void {
        onOpenChange(false);
    }

    function updateDraft<K extends Exclude<keyof PlaylistLineForm, 'uuid'>>(
        field: K,
        value: PlaylistLineForm[K],
    ): void {
        if (field === 'playlist_id') {
            setDraft((current) => ({
                ...current,
                playlist_id: String(value ?? ''),
                playlist_top_id: '',
                unit_amount: '0.00',
            }));

            return;
        }

        if (field === 'playlist_top_id') {
            const playlistTop = optionsById.playlistTops.get(String(value));

            setDraft((current) => ({
                ...current,
                playlist_top_id: String(value ?? ''),
                unit_amount: centsToDecimal(playlistTop?.price),
            }));

            return;
        }

        if (field === 'track_spotify_url') {
            setSpotifyLookupData('spotify_input', String(value));
            setSpotifyPreview(null);
            setSpotifyLookupIssue(null);
        }

        if (field === 'track_name') {
            setSpotifyPreview(null);
            setManualTrackNameVisible(true);
        }

        setDraft((current) => ({ ...current, [field]: value }));
    }

    function lookupTrack(): void {
        const spotifyInput = draft.track_spotify_url.trim();

        setSpotifyPreview(null);
        setSpotifyLookupIssue(null);
        setManualTrackNameVisible(false);

        if (spotifyInput === '') {
            setSpotifyLookupIssue({
                title: 'Informe o link da música',
                message:
                    'Cole o link, URI ou ID da faixa no Spotify antes de buscar.',
                tone: 'warning',
            });

            return;
        }

        if (!isSpotifyTrackReference(spotifyInput)) {
            setSpotifyLookupIssue({
                title: 'Link não é de música',
                message:
                    'Use um link de faixa, por exemplo https://open.spotify.com/intl-pt/track/4kwbbhuemSkRWvtN5dkWmb.',
                tone: 'warning',
            });

            return;
        }

        lookupSpotifyTrack(SpotifyTrackLookupController.url(), {
            onSuccess: (response) => {
                const body = response as { data?: SpotifyTrackPreview };

                if (!body.data) {
                    setSpotifyLookupIssue({
                        title: 'Música não encontrada',
                        message:
                            'O Spotify não retornou dados para esta faixa. Você pode preencher a música manualmente.',
                        tone: 'warning',
                    });

                    return;
                }

                const trackName = body.data.display_name ?? body.data.name;

                setSpotifyPreview(body.data);
                setManualTrackNameVisible(false);
                setDraft((current) => ({
                    ...current,
                    track_name: trackName ?? current.track_name,
                    track_spotify_url: body.data?.spotify_url ?? current.track_spotify_url,
                    track_artists: body.data?.artist_names ?? current.track_artists,
                    track_album_name: body.data?.album_name ?? current.track_album_name,
                    track_duration_ms: body.data?.duration_ms ?? current.track_duration_ms,
                    track_isrc: body.data?.isrc ?? current.track_isrc,
                    track_image_url: body.data?.image_url ?? current.track_image_url,
                }));
                setSpotifyLookupData(
                    'spotify_input',
                    body.data.spotify_url,
                );
                toast.success('Música preenchida pelo Spotify.');
            },
            onError: (errors) => {
                const message = errors.spotify_input;

                setSpotifyLookupIssue({
                    title: 'Não foi possível buscar a música',
                    message:
                        typeof message === 'string'
                            ? message
                            : 'Confira o link da música ou preencha manualmente.',
                    tone: 'warning',
                });
            },
            onHttpException: (response) => {
                const body = response.data as
                    | {
                          message?: string;
                          data?: {
                              message?: string;
                          };
                      }
                    | undefined;

                setSpotifyLookupIssue({
                    title: 'Não foi possível consultar o Spotify',
                    message:
                        body?.message ??
                        body?.data?.message ??
                        'Preencha a música manualmente e tente a busca novamente depois.',
                    tone: response.status >= 500 ? 'danger' : 'warning',
                });
            },
        });
    }

    function savePlaylistItem(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (
            draft.playlist_top_id === '' ||
            draft.track_name.trim() === '' ||
            draft.track_spotify_url.trim() === ''
        ) {
            if (draft.track_name.trim() === '') {
                setManualTrackNameVisible(true);
            }

            toast.error(
                'Informe playlist, TOP, música e link da música no Spotify.',
            );

            return;
        }

        if (!isSpotifyTrackReference(draft.track_spotify_url)) {
            toast.error(
                'Informe um link, URI ou ID válido de música do Spotify.',
            );

            return;
        }

        const hasStartsOn = draft.starts_on.trim() !== '';
        const hasEndsOn = draft.ends_on.trim() !== '';

        if (hasStartsOn !== hasEndsOn) {
            toast.error(
                'Informe o período completo (início e fim) ou deixe as duas datas vazias.',
            );

            return;
        }

        if (hasStartsOn && hasEndsOn && draft.ends_on < draft.starts_on) {
            toast.error(
                'A data final deve ser igual ou posterior à data inicial.',
            );

            return;
        }

        onSave({
            ...draft,
            id: initialLine?.id ?? draft.id,
            quantity: String(quantityFromInput(draft.quantity)),
        });
        close();
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Editar playlist' : 'Adicionar playlist'}
                    </DialogTitle>
                    <DialogDescription>
                        Selecione a playlist, o TOP vendido e a música antes de
                        atualizar o item da venda.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={savePlaylistItem} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="playlist_item_playlist_id">
                                Playlist
                            </Label>
                            <Select
                                value={draft.playlist_id}
                                onValueChange={(value) =>
                                    updateDraft('playlist_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="playlist_item_playlist_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Selecione a playlist" />
                                </SelectTrigger>
                                <SelectContent>
                                    {formOptions.playlists.map((playlist) => (
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

                        <div className="grid gap-2">
                            <Label htmlFor="playlist_item_top_id">TOP</Label>
                            <Select
                                value={draft.playlist_top_id}
                                onValueChange={(value) =>
                                    updateDraft('playlist_top_id', value)
                                }
                                disabled={draft.playlist_id === ''}
                            >
                                <SelectTrigger
                                    id="playlist_item_top_id"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={
                                            draft.playlist_id === ''
                                                ? 'Escolha a playlist'
                                                : 'Selecione o TOP'
                                        }
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredPlaylistTops.map((playlistTop) => (
                                        <SelectItem
                                            key={playlistTop.id}
                                            value={String(playlistTop.id)}
                                        >
                                            {playlistTopLabel(playlistTop)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-[120px_minmax(0,1fr)]">
                        <div className="grid gap-2">
                            <Label htmlFor="playlist_item_quantity">Qtd.</Label>
                            <Input
                                id="playlist_item_quantity"
                                type="number"
                                min="1"
                                value={draft.quantity}
                                onChange={(event) =>
                                    updateDraft('quantity', event.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="playlist_item_unit_amount">
                                Valor unitário
                            </Label>
                            <InputMoney
                                id="playlist_item_unit_amount"
                                value={draft.unit_amount}
                                onChange={(value) =>
                                    updateDraft('unit_amount', value)
                                }
                            />
                        </div>
                    </div>

                    <div className="space-y-2 rounded-md border bg-muted/20 p-3">
                        <p className="text-sm font-medium">
                            Período da vaga{' '}
                            <span className="font-normal text-muted-foreground">
                                (opcional)
                            </span>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Com período definido, a disponibilidade do TOP é
                            validada e a vaga entra ativa na confirmação da
                            venda. Sem período, a vaga fica pendente de agenda.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="playlist_item_starts_on">
                                    Início
                                </Label>
                                <Input
                                    id="playlist_item_starts_on"
                                    type="date"
                                    value={draft.starts_on}
                                    onChange={(event) =>
                                        updateDraft(
                                            'starts_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="playlist_item_ends_on">
                                    Fim
                                </Label>
                                <Input
                                    id="playlist_item_ends_on"
                                    type="date"
                                    min={draft.starts_on || undefined}
                                    value={draft.ends_on}
                                    onChange={(event) =>
                                        updateDraft(
                                            'ends_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                        <div className="hidden">
                            <Label htmlFor="playlist_item_track_name_hidden">
                                Música
                            </Label>
                            <Input
                                id="playlist_item_track_name_hidden"
                                value={draft.track_name}
                                onChange={(event) =>
                                    updateDraft(
                                        'track_name',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="playlist_item_track_spotify_url">
                                Link da música no Spotify
                            </Label>
                            <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <Input
                                    id="playlist_item_track_spotify_url"
                                    value={draft.track_spotify_url}
                                    onChange={(event) =>
                                        updateDraft(
                                            'track_spotify_url',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="https://open.spotify.com/intl-pt/track/4kwbbhuemSkRWvtN5dkWmb"
                                    autoComplete="off"
                                    aria-describedby="playlist_item_track_lookup_feedback"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-10 px-3 sm:w-auto"
                                    onClick={lookupTrack}
                                    disabled={lookupProcessing}
                                    title="Buscar no Spotify"
                                >
                                    {lookupProcessing ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Search className="size-4" />
                                    )}
                                    Buscar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        id="playlist_item_track_lookup_feedback"
                        className="space-y-2"
                        aria-live="polite"
                    >
                        {spotifyPreview === null && hasDraftTrack ? (
                            <div className="flex gap-3 rounded-md border bg-background p-3">
                                <div className="relative flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted text-muted-foreground">
                                    {previewImageUrl ? (
                                        <img
                                            src={previewImageUrl}
                                            alt=""
                                            className="absolute inset-0 size-full object-cover"
                                        />
                                    ) : (
                                        <Music2 className="size-5" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                        <p className="truncate text-sm font-medium">
                                            {previewTitle}
                                        </p>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                        {previewSubtitle ??
                                            'Dados manuais da música'}
                                    </p>
                                </div>
                            </div>
                        ) : null}

                        {spotifyPreview ? (
                            <div className="flex gap-3 rounded-md border bg-background p-3">
                                <div className="relative flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted text-muted-foreground">
                                    {previewImageUrl ? (
                                        <img
                                            src={previewImageUrl}
                                            alt=""
                                            className="absolute inset-0 size-full object-cover"
                                        />
                                    ) : (
                                        <Music2 className="size-5" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                        <p className="truncate text-sm font-medium">
                                            {spotifyPreview.display_name ??
                                                spotifyPreview.name ??
                                                'MÃºsica encontrada'}
                                        </p>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                        {[
                                            spotifyPreview.album_name,
                                            durationLabel(
                                                spotifyPreview.duration_ms,
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .join(' Â· ') ||
                                            'Dados bÃ¡sicos do Spotify'}
                                    </p>
                                </div>
                            </div>
                        ) : null}

                        {spotifyLookupIssue ? (
                            <div
                                className={cn(
                                    'flex gap-3 rounded-md border p-3 text-sm',
                                    spotifyLookupIssue.tone === 'danger'
                                        ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/20 dark:text-red-200'
                                        : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-200',
                                )}
                            >
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {spotifyLookupIssue.title}
                                    </p>
                                    <p className="mt-1 text-xs leading-relaxed">
                                        {spotifyLookupIssue.message}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-3 h-8 bg-background/70"
                                        onClick={() =>
                                            setManualTrackNameVisible(true)
                                        }
                                    >
                                        Digitar manualmente
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>

                    {manualTrackNameVisible ? (
                        <div className="grid gap-2 rounded-md border bg-muted/20 p-3">
                            <Label htmlFor="playlist_item_track_name">
                                Música
                            </Label>
                            <Input
                                id="playlist_item_track_name"
                                value={draft.track_name}
                                onChange={(event) =>
                                    updateDraft(
                                        'track_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nome da música"
                                autoComplete="off"
                            />
                        </div>
                    ) : null}

                    <div className="rounded-md border bg-muted/30 p-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {selectedPlaylistTop?.name ??
                                        'TOP ainda não selecionado'}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {draft.track_name || 'Música'} ·{' '}
                                    {draft.track_spotify_url ||
                                        'referência Spotify'}
                                </p>
                            </div>
                            <span className="text-lg font-semibold tabular-nums">
                                {formatMoney(lineTotal(draft), {
                                    fromCents: true,
                                    fallback: 'R$ 0,00',
                                })}
                            </span>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Cancelar
                        </Button>
                        <Button type="submit">
                            {isEditing ? (
                                <Save className="size-4" />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            {isEditing
                                ? 'Salvar alterações'
                                : 'Adicionar na venda'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
