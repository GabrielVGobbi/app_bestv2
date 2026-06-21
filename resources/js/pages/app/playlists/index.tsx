import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ChevronDown,
    Copy,
    ExternalLink,
    Eye,
    Loader2,
    Music2,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
    create,
    destroy,
    edit,
    index,
    restore,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import syncSpotifyPlaylist from '@/actions/App/Modules/Playlists/Http/Controllers/SpotifyPlaylistSyncController';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { apiRoutes } from '@/lib/api/api-routes';
import { getErrorMessage, httpPatch, toApiError } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    MusicSubgenre,
    Playlist,
    PlaylistFormOptions,
} from '@/types/app/playlist';

interface Props {
    permissions: {
        can_create: boolean;
        can_update: boolean;
        can_delete: boolean;
        can_restore: boolean;
    };
    filters: PlaylistFormOptions;
}

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Playlists\\Models\\Playlist',
    columnsEndpoint: apiRoutes.exports.columns,
};

function openExternal(url: string | null): void {
    if (!url) {
        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
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

function spotifyUrl(playlist: Playlist): string | null {
    if (playlist.spotify_url) {
        return playlist.spotify_url;
    }

    if (playlist.spotify_playlist_id) {
        return `https://open.spotify.com/playlist/${playlist.spotify_playlist_id}`;
    }

    return null;
}

function playlistCoverUrl(playlist: Playlist): string | null {
    return playlist.cover_image?.url ?? playlist.spotify_image_url ?? null;
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

interface SpotifyActionsMenuProps {
    playlist: Playlist;
    canSync: boolean;
    onSynced: () => void;
}

function SpotifyActionsMenu({
    playlist,
    canSync,
    onSynced,
}: SpotifyActionsMenuProps) {
    const [syncing, setSyncing] = useState(false);
    const url = spotifyUrl(playlist);
    const hasSpotifyReference = Boolean(url || playlist.spotify_playlist_id);
    const canRunSync = canSync && Boolean(playlist.spotify_playlist_id);

    async function copySpotifyLink(): Promise<void> {
        if (!url) {
            toast.error('Essa playlist ainda não tem link ou ID do Spotify.');

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

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Ações do Spotify"
                    disabled={!hasSpotifyReference}
                    className={cn(
                        'text-emerald-700 hover:text-emerald-800 dark:text-emerald-400',
                        hasSpotifyReference &&
                            'border-emerald-200 hover:bg-emerald-50 dark:border-emerald-900/80 dark:hover:bg-emerald-950/30',
                    )}
                >
                    {syncing ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <SpotifyIcon className="size-4" />
                    )}
                    <ChevronDown className="absolute right-0.5 bottom-0.5 size-2.5 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuLabel className="flex items-center gap-2">
                    <SpotifyIcon className="size-4 text-emerald-600" />
                    Spotify
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
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
                    disabled={!url}
                    onSelect={() => url && openExternal(url)}
                >
                    <ExternalLink className="size-4" />
                    Abrir Spotify
                </DropdownMenuItem>
                <DropdownMenuSeparator />
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
                    Sincronizar Spotify
                </DropdownMenuItem>
                {!playlist.spotify_playlist_id ? (
                    <p className="px-2 py-1 text-xs text-muted-foreground">
                        Vincule o ID público para sincronizar.
                    </p>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function PlaylistIndex({ permissions, filters }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Playlists', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [playlistToDelete, setPlaylistToDelete] = useState<Playlist | null>(
        null,
    );

    const subgenreOptions = useMemo(
        () =>
            filters.genres.flatMap((genre) =>
                (genre.subgenres ?? []).map((subgenre: MusicSubgenre) => ({
                    value: subgenre.id,
                    label: `${genre.name} / ${subgenre.name}`,
                })),
            ),
        [filters.genres],
    );

    const columns = useMemo<ColumnDef<Playlist>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nome',
                size: 340,
                cell: ({ row }) => {
                    const coverUrl = playlistCoverUrl(row.original);

                    return (
                        <div className="flex min-w-0 items-center gap-3">
                            <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted text-muted-foreground">
                                {coverUrl ? (
                                    <img
                                        src={coverUrl}
                                        alt={`Capa da playlist ${row.original.name}`}
                                        width={48}
                                        height={48}
                                        loading="lazy"
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Music2 className="size-5" />
                                )}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {row.original.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {row.original.spotify_playlist_id ??
                                        row.original.slug}
                                </p>
                            </div>
                        </div>
                    );
                },
                meta: { label: 'Nome' },
            },
            {
                id: 'genre',
                accessorKey: 'genre.name',
                header: 'Gênero',
                cell: ({ row }) => row.original.genre?.name ?? '-',
                meta: { label: 'Gênero' },
            },
            {
                id: 'partner_name',
                accessorKey: 'partner_name',
                header: 'Parceiro',
                cell: ({ row }) => row.original.partner_name ?? '-',
                meta: { label: 'Parceiro' },
            },
            {
                id: 'followers_count',
                accessorKey: 'followers_count',
                header: 'Seguidores',
                cell: ({ row }) =>
                    row.original.followers_count?.toLocaleString('pt-BR') ??
                    '-',
                meta: { label: 'Seguidores' },
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <StatusBadge presenter={row.original.status_presenter} />
                ),
                meta: { label: 'Status' },
            },
        ],
        [],
    );

    const tableFilters = useMemo<DataTableFilterConfig[]>(
        () => [
            {
                id: 'status',
                label: 'Status',
                type: 'select',
                operator: 'eq',
                options: filters.statuses,
            },
            {
                id: 'music_genre_id',
                label: 'Gênero',
                type: 'select',
                operator: 'eq',
                options: filters.genres.map((genre) => ({
                    value: genre.id,
                    label: genre.name,
                })),
            },
            {
                id: 'subgenres.id',
                label: 'Subgênero',
                type: 'select',
                operator: 'eq',
                options: subgenreOptions,
            },
            {
                id: 'partner_name',
                label: 'Parceiro',
                type: 'text',
                operator: 'contains',
            },
        ],
        [filters.genres, filters.statuses, subgenreOptions],
    );

    const actions = useMemo<DataTableAction<Playlist>[]>(() => {
        const tableActions: DataTableAction<Playlist>[] = [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (playlist) => show.url(playlist.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Spotify',
                render: (playlist) => (
                    <SpotifyActionsMenu
                        playlist={playlist}
                        canSync={permissions.can_update}
                        onSynced={() => {
                            void queryClient.invalidateQueries({
                                queryKey: ['playlists'],
                            });
                        }}
                    />
                ),
            },
        ];

        if (permissions.can_update) {
            tableActions.push({
                label: 'Editar',
                icon: <Pencil className="size-4" />,
                variant: 'outline',
                href: (playlist) => edit.url(playlist.uuid),
                showLabel: false,
                tooltip: 'Editar',
            });
        }

        if (permissions.can_restore) {
            tableActions.push({
                label: 'Restaurar',
                icon: <RotateCcw className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Restaurar',
                disabled: (playlist) => !playlist.deleted_at,
                onClick: (playlist) => {
                    router.patch(restore.url(playlist.uuid), undefined, {
                        preserveScroll: true,
                    });
                },
            });
        }

        if (permissions.can_delete) {
            tableActions.push({
                label: 'Arquivar',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Arquivar',
                onClick: (playlist) => setPlaylistToDelete(playlist),
            });
        }

        return tableActions;
    }, [
        permissions.can_delete,
        permissions.can_restore,
        permissions.can_update,
        queryClient,
    ]);

    function deletePlaylist(): void {
        if (!playlistToDelete) {
            return;
        }

        router.delete(destroy.url(playlistToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => setPlaylistToDelete(null),
        });
    }

    return (
        <>
            <Head title="Playlists" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Playlists"
                            description="Cadastro comercial de canais musicais e métricas operacionais."
                        />
                        {permissions.can_create ? (
                            <Button asChild>
                                <Link prefetch href={create.url()}>
                                    <Plus className="size-4" />
                                    Nova Playlist
                                </Link>
                            </Button>
                        ) : null}
                    </div>

                    <Dialog
                        open={playlistToDelete !== null}
                        onOpenChange={(open) =>
                            !open && setPlaylistToDelete(null)
                        }
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Arquivar playlist</DialogTitle>
                                <DialogDescription>
                                    Esta ação remove a playlist de novas
                                    operações e preserva o histórico para
                                    consultas futuras.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setPlaylistToDelete(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={deletePlaylist}
                                >
                                    Arquivar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<Playlist>
                        queryKey="playlists"
                        endpoint={apiRoutes.playlists.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(playlist) => playlist.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                            export: true,
                        }}
                        exportConfig={exportConfig}
                        filters={tableFilters}
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{
                            display: 'inline',
                            label: 'Ações',
                            buttonSize: 'icon',
                            showTooltips: true,
                            sticky: true,
                        }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                        }}
                        appearance={{
                            size: 'md',
                            gridLines: 'none',
                            rowStyle: 'soft-striped',
                        }}
                        searchPlaceholder="Buscar playlists..."
                    />
                </div>
            </div>
        </>
    );
}
