import { Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    Loader2,
    MoreHorizontal,
    Pencil,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import {
    edit,
    restore,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import syncSpotifyPlaylist from '@/actions/App/Modules/Playlists/Http/Controllers/SpotifyPlaylistSyncController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getErrorMessage, httpPatch, toApiError } from '@/lib/http';
import type { Playlist } from '@/types/app/playlist';

interface PlaylistRowActionsProps {
    playlist: Playlist;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    onArchive: (playlist: Playlist) => void;
    onSynced: () => void;
}

function SpotifyIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className={className}
            fill="currentColor"
        >
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.59 14.42a.63.63 0 0 1-.86.2c-2.36-1.45-5.33-1.78-8.82-.97a.63.63 0 1 1-.28-1.23c3.82-.87 7.1-.5 9.76 1.13.3.18.39.57.2.87Zm1.22-2.72a.78.78 0 0 1-1.07.25c-2.7-1.66-6.82-2.14-10.02-1.17a.78.78 0 1 1-.45-1.5c3.65-1.11 8.19-.58 11.28 1.32.37.23.48.72.26 1.1Zm.1-2.84C14.67 8.94 9.32 8.76 6.23 9.7a.94.94 0 0 1-.55-1.79c3.55-1.08 9.45-.87 13.18 1.34.44.27.59.84.32 1.29a.94.94 0 0 1-1.28.32Z" />
        </svg>
    );
}

function playlistSpotifyUrl(playlist: Playlist): string | null {
    if (playlist.spotify_url) {
        return playlist.spotify_url;
    }

    if (playlist.spotify_playlist_id) {
        return `https://open.spotify.com/playlist/${playlist.spotify_playlist_id}`;
    }

    return null;
}

async function copyToClipboard(value: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);

        return;
    }

    const textarea = document.createElement('textarea');

    textarea.value = value;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
}

export function PlaylistRowActions({
    playlist,
    canUpdate,
    canDelete,
    canRestore,
    onArchive,
    onSynced,
}: PlaylistRowActionsProps) {
    const [syncing, setSyncing] = useState(false);
    const url = playlistSpotifyUrl(playlist);
    const isArchived = Boolean(playlist.deleted_at);
    const canRunSync = canUpdate && Boolean(playlist.spotify_playlist_id);
    const hasSpotifyGroup = Boolean(url || playlist.spotify_playlist_id);

    async function copySpotifyLink(): Promise<void> {
        if (!url) {
            toast.error('Essa playlist ainda não tem link do Spotify.');

            return;
        }

        try {
            await copyToClipboard(url);
            toast.success('Link do Spotify copiado.');
        } catch {
            toast.error('Não foi possível copiar o link automaticamente.');
        }
    }

    async function syncSpotify(): Promise<void> {
        if (!canRunSync || syncing) {
            return;
        }

        setSyncing(true);

        try {
            await httpPatch(syncSpotifyPlaylist.url(playlist.uuid));
            toast.success('Dados do Spotify sincronizados.');
            onSynced();
        } catch (error) {
            toast.error(getErrorMessage(toApiError(error)));
        } finally {
            setSyncing(false);
        }
    }

    function restorePlaylist(): void {
        router.patch(restore.url(playlist.uuid), undefined, {
            preserveScroll: true,
        });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Ações de ${playlist.name}`}
                >
                    {syncing ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <MoreHorizontal className="size-4" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem asChild>
                    <Link href={show.url(playlist.uuid)} prefetch>
                        <Eye className="size-4" />
                        Visualizar
                    </Link>
                </DropdownMenuItem>
                {canUpdate ? (
                    <DropdownMenuItem asChild>
                        <Link href={edit.url(playlist.uuid)} prefetch>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </DropdownMenuItem>
                ) : null}

                {hasSpotifyGroup ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled={!url}
                            onSelect={() => {
                                if (!url) {
                                    return;
                                }

                                window.open(url, '_blank', 'noopener,noreferrer');
                            }}
                        >
                            <SpotifyIcon className="size-4 text-emerald-600 dark:text-emerald-400" />
                            Abrir Spotify
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!url}
                            onSelect={(event) => {
                                event.preventDefault();
                                void copySpotifyLink();
                            }}
                        >
                            <Copy className="size-4" />
                            Copiar link
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={!canRunSync || syncing}
                            onSelect={(event) => {
                                event.preventDefault();
                                void syncSpotify();
                            }}
                        >
                            {syncing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            Sincronizar
                        </DropdownMenuItem>
                    </>
                ) : null}

                {canRestore && isArchived ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => restorePlaylist()}>
                            <RotateCcw className="size-4" />
                            Restaurar
                        </DropdownMenuItem>
                    </>
                ) : null}

                {canDelete && !isArchived ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => onArchive(playlist)}
                        >
                            <Trash2 className="size-4" />
                            Arquivar
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function playlistCoverUrl(playlist: Playlist): string | null {
    return playlist.cover_image?.url ?? playlist.spotify_image_url ?? null;
}

export function playlistSubtitle(playlist: Playlist): string {
    const parts: string[] = [];

    if (playlist.spotify_owner_name) {
        parts.push(playlist.spotify_owner_name);
    }

    if (playlist.spotify_tracks_total != null) {
        parts.push(
            `${playlist.spotify_tracks_total.toLocaleString('pt-BR')} faixas`,
        );
    }

    if (parts.length > 0) {
        return parts.join(' · ');
    }

    if (playlist.spotify_playlist_id) {
        return 'Vinculada ao Spotify';
    }

    return 'Cadastro manual';
}
