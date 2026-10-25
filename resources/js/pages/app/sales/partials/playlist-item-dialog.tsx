import { useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ListMusic,
    Loader2,
    Music2,
    Plus,
    Save,
    Search,
    Trophy,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';

import SpotifyTrackLookupController from '@/actions/App/Modules/Sales/Http/Controllers/SpotifyTrackLookupController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Label } from '@/components/ui/label';
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
    const [manualTrackNameVisible, setManualTrackNameVisible] = useState(
        Boolean(initialLine?.track_name),
    );
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
        spotifyPreview?.display_name ??
        spotifyPreview?.name ??
        draft.track_name;
    const previewImageUrl =
        spotifyPreview?.image_url ?? lineArtworkUrl(draft, optionsById);
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
                    track_spotify_url:
                        body.data?.spotify_url ?? current.track_spotify_url,
                    track_artists:
                        body.data?.artist_names ?? current.track_artists,
                    track_album_name:
                        body.data?.album_name ?? current.track_album_name,
                    track_duration_ms:
                        body.data?.duration_ms ?? current.track_duration_ms,
                    track_isrc: body.data?.isrc ?? current.track_isrc,
                    track_image_url:
                        body.data?.image_url ?? current.track_image_url,
                }));
                setSpotifyLookupData('spotify_input', body.data.spotify_url);
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

        onSave({
            ...draft,
            id: initialLine?.id ?? draft.id,
            quantity: String(quantityFromInput(draft.quantity)),
        });
        close();
    }

    const previewCard = (title: string, subtitle: string) => (
        <div className="flex gap-3 rounded-lg border border-border bg-card p-3">
            <div className="relative flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted text-muted-foreground">
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
                    <CheckCircle2
                        className="size-4 text-success"
                        aria-hidden="true"
                    />
                    <p className="truncate text-sm font-medium">{title}</p>
                </div>
                <p className="mt-1 truncate text-xs text-muted-foreground">
                    {subtitle}
                </p>
            </div>
        </div>
    );

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

                {/* Diálogo bg-popover (surface-3): os selects abrem um nível acima. */}
                <SurfaceProvider value={3}>
                    <form onSubmit={savePlaylistItem} className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <FormField label="Playlist" required>
                                <Select
                                    value={draft.playlist_id}
                                    onValueChange={(value) =>
                                        updateDraft('playlist_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="playlist_item_playlist_id"
                                        className="w-full"
                                        icon={ListMusic}
                                        placeholder="Selecione a playlist"
                                    />
                                    <SelectContent>
                                        {formOptions.playlists.map(
                                            (playlist, index) => (
                                                <SelectItem
                                                    key={playlist.id}
                                                    index={index}
                                                    value={String(playlist.id)}
                                                >
                                                    {playlist.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="TOP" required>
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
                                        icon={Trophy}
                                        placeholder={
                                            draft.playlist_id === ''
                                                ? 'Escolha a playlist'
                                                : 'Selecione o TOP'
                                        }
                                    />
                                    <SelectContent>
                                        {filteredPlaylistTops.map(
                                            (playlistTop, index) => (
                                                <SelectItem
                                                    key={playlistTop.id}
                                                    index={index}
                                                    value={String(
                                                        playlistTop.id,
                                                    )}
                                                >
                                                    {playlistTopLabel(
                                                        playlistTop,
                                                    )}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid gap-4 md:grid-cols-[120px_minmax(0,1fr)]">
                            <FormField label="Qtd.">
                                <Input
                                    id="playlist_item_quantity"
                                    type="number"
                                    min="1"
                                    value={draft.quantity}
                                    onChange={(event) =>
                                        updateDraft(
                                            'quantity',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField label="Valor unitário">
                                <InputMoney
                                    id="playlist_item_unit_amount"
                                    value={draft.unit_amount}
                                    onChange={(value) =>
                                        updateDraft('unit_amount', value)
                                    }
                                />
                            </FormField>
                        </div>

                        <div className="space-y-3 rounded-lg border border-border bg-muted p-3">
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

                            <FormField
                                id="playlist_item_track_spotify_url"
                                label="Link da música no Spotify"
                                required
                            >
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
                                        className="h-10"
                                        onClick={lookupTrack}
                                        disabled={lookupProcessing}
                                    >
                                        {lookupProcessing ? (
                                            <Loader2 className="size-4 animate-spin" />
                                        ) : (
                                            <Search className="size-4" />
                                        )}
                                        Buscar no Spotify
                                    </Button>
                                </div>
                            </FormField>
                        </div>

                        <div
                            id="playlist_item_track_lookup_feedback"
                            className="space-y-2"
                            aria-live="polite"
                        >
                            {spotifyPreview === null && hasDraftTrack
                                ? previewCard(
                                      previewTitle,
                                      previewSubtitle ??
                                          'Dados manuais da música',
                                  )
                                : null}

                            {spotifyPreview
                                ? previewCard(
                                      spotifyPreview.display_name ??
                                          spotifyPreview.name ??
                                          'Música encontrada',
                                      [
                                          spotifyPreview.album_name,
                                          durationLabel(
                                              spotifyPreview.duration_ms,
                                          ),
                                      ]
                                          .filter(Boolean)
                                          .join(' · ') ||
                                          'Dados básicos do Spotify',
                                  )
                                : null}

                            {spotifyLookupIssue ? (
                                <Alert
                                    variant={
                                        spotifyLookupIssue.tone === 'danger'
                                            ? 'destructive'
                                            : 'warning'
                                    }
                                >
                                    <AlertTriangle aria-hidden="true" />
                                    <AlertTitle>
                                        {spotifyLookupIssue.title}
                                    </AlertTitle>
                                    <AlertDescription>
                                        <p>{spotifyLookupIssue.message}</p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-2"
                                            onClick={() =>
                                                setManualTrackNameVisible(true)
                                            }
                                        >
                                            Digitar manualmente
                                        </Button>
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </div>

                        {manualTrackNameVisible ? (
                            <div className="rounded-lg border border-border bg-muted p-3">
                                <FormField label="Música" required>
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
                                </FormField>
                            </div>
                        ) : null}

                        <div className="rounded-lg border border-border bg-muted p-3">
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
                            <Button
                                type="button"
                                variant="outline"
                                onClick={close}
                            >
                                <X className="size-4" aria-hidden="true" />
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
                </SurfaceProvider>
            </DialogContent>
        </Dialog>
    );
}
