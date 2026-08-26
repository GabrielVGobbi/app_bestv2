import { CheckCircle2, Loader2, Search } from 'lucide-react';
import type { ClipboardEvent, FormEvent } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SpotifyPlaylistPreview } from '@/types/app/playlist';

interface Props {
    value: string;
    processing: boolean;
    preview: SpotifyPlaylistPreview | null;
    error?: string | null;
    onChange: (value: string) => void;
    onSearch: (value: string) => void;
}

function looksLikeSpotifyPlaylist(value: string): boolean {
    const input = value.trim();

    if (!input) {
        return false;
    }

    if (/^[A-Za-z0-9]{22}$/.test(input)) {
        return true;
    }

    if (/^spotify:playlist:[A-Za-z0-9]{22}$/i.test(input)) {
        return true;
    }

    try {
        const url = new URL(input);

        return (
            url.hostname.includes('spotify.com') &&
            url.pathname.split('/').filter(Boolean).includes('playlist')
        );
    } catch {
        return false;
    }
}

export default function SpotifyPlaylistImporter({
    value,
    processing,
    preview,
    error,
    onChange,
    onSearch,
}: Props) {
    function handleSubmit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        onSearch(value);
    }

    function handlePaste(event: ClipboardEvent<HTMLInputElement>): void {
        const pasted = event.clipboardData.getData('text');

        if (!looksLikeSpotifyPlaylist(pasted)) {
            return;
        }

        window.setTimeout(() => {
            onSearch(pasted);
        }, 0);
    }

    return (
        <Card className="overflow-hidden py-0 shadow-xs">
            <CardContent className="space-y-4 p-4 sm:p-6">
                <form
                    className="grid gap-3 sm:grid-cols-[1fr_auto]"
                    onSubmit={handleSubmit}
                >
                    <div className="space-y-2">
                        <Label htmlFor="spotify-preview-input">
                            Link ou ID da playlist
                        </Label>
                        <Input
                            id="spotify-preview-input"
                            name="spotify_input"
                            value={value}
                            onChange={(event) => onChange(event.target.value)}
                            onPaste={handlePaste}
                            placeholder="https://open.spotify.com/playlist/... ou 37i9dQZF..."
                            autoComplete="off"
                            required
                            aria-invalid={!!error}
                        />
                        {error ? (
                            <p className="text-sm text-destructive">{error}</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Cole o link para puxar capa, nome e métricas
                                automaticamente.
                            </p>
                        )}
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="submit"
                            className="w-full sm:w-auto"
                            disabled={processing || value.trim() === ''}
                        >
                            {processing ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <Search />
                            )}
                            Buscar
                        </Button>
                    </div>
                </form>

                {processing && !preview ? (
                    <div className="flex gap-4 rounded-md border bg-muted/30 p-4">
                        <Skeleton className="size-20 shrink-0 rounded-md" />
                        <div className="min-w-0 flex-1 space-y-2">
                            <Skeleton className="h-5 w-2/3" />
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-3 w-40" />
                        </div>
                    </div>
                ) : null}

                {preview ? (
                    <div className="flex flex-col gap-4 rounded-md border bg-muted/30 p-4 sm:flex-row sm:items-center">
                        {preview.image_url ? (
                            <img
                                src={preview.image_url}
                                alt={`Capa de ${preview.name ?? 'playlist Spotify'}`}
                                width={80}
                                height={80}
                                className="size-20 shrink-0 rounded-md object-cover"
                            />
                        ) : (
                            <div
                                className={cn(
                                    'flex size-20 shrink-0 items-center justify-center rounded-md border bg-background text-xs text-muted-foreground',
                                )}
                            >
                                Sem capa
                            </div>
                        )}
                        <div className="min-w-0 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <CheckCircle2 className="size-4 text-emerald-600" />
                                <p className="font-medium">
                                    {preview.name ?? 'Playlist Spotify'}
                                </p>
                                {preview.is_partial ? (
                                    <Badge variant="outline">
                                        Dados básicos
                                    </Badge>
                                ) : null}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {preview.spotify_owner_name ??
                                    'Dono não informado'}{' '}
                                ·{' '}
                                {preview.followers_count?.toLocaleString(
                                    'pt-BR',
                                ) ?? 'Não disponível'}{' '}
                                seguidores ·{' '}
                                {preview.spotify_tracks_total?.toLocaleString(
                                    'pt-BR',
                                ) ?? 'Não disponível'}{' '}
                                faixas
                            </p>
                            {preview.is_partial ? (
                                <p className="text-xs text-muted-foreground">
                                    O Spotify liberou apenas os dados básicos
                                    pelo embed oficial.
                                </p>
                            ) : null}
                            <p className="font-mono text-xs text-muted-foreground">
                                {preview.spotify_playlist_id}
                            </p>
                        </div>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
