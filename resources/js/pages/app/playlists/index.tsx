import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Music2, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
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
import { apiRoutes } from '@/lib/api/api-routes';
import type {
    MusicSubgenre,
    Playlist,
    PlaylistFormOptions,
} from '@/types/app/playlist';

import {
    PlaylistRowActions,
    playlistCoverUrl,
    playlistSubtitle,
} from './components/playlist-row-actions';

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

function formatFollowers(value: number | null): string {
    if (value == null) {
        return '—';
    }

    return value.toLocaleString('pt-BR');
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
                size: 300,
                cell: ({ row }) => {
                    const playlist = row.original;
                    const coverUrl = playlistCoverUrl(playlist);

                    return (
                        <Link
                            href={show.url(playlist.uuid)}
                            prefetch
                            aria-label={playlist.name}
                            className="flex min-w-0 items-center gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted text-muted-foreground">
                                {coverUrl ? (
                                    <img
                                        src={coverUrl}
                                        alt=""
                                        width={40}
                                        height={40}
                                        loading="lazy"
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Music2 className="size-4" />
                                )}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {playlist.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {playlistSubtitle(playlist)}
                                </p>
                            </div>
                        </Link>
                    );
                },
                meta: { label: 'Nome' },
            },
            {
                id: 'genre',
                accessorKey: 'genre.name',
                header: 'Gênero',
                size: 130,
                minSize: 110,
                cell: ({ row }) => row.original.genre?.name ?? '—',
                meta: { label: 'Gênero' },
            },
            {
                id: 'partner_name',
                accessorKey: 'partner_name',
                header: 'Parceiro',
                size: 170,
                minSize: 130,
                cell: ({ row }) => row.original.partner_name ?? '—',
                meta: { label: 'Parceiro' },
            },
            {
                id: 'followers_count',
                accessorKey: 'followers_count',
                header: 'Seguidores',
                size: 130,
                minSize: 120,
                cell: ({ row }) => formatFollowers(row.original.followers_count),
                meta: { label: 'Seguidores', align: 'center', type: 'number' },
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                size: 140,
                minSize: 120,
                cell: ({ row }) => (
                    <StatusBadge presenter={row.original.status_presenter} />
                ),
                meta: { label: 'Status', align: 'center' },
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

    const actions = useMemo<DataTableAction<Playlist>[]>(
        () => [
            {
                label: 'Ações',
                render: (playlist) => (
                    <PlaylistRowActions
                        playlist={playlist}
                        canUpdate={permissions.can_update}
                        canDelete={permissions.can_delete}
                        canRestore={permissions.can_restore}
                        onArchive={setPlaylistToDelete}
                        onSynced={() => {
                            void queryClient.invalidateQueries({
                                queryKey: ['playlists'],
                            });
                        }}
                    />
                ),
            },
        ],
        [
            permissions.can_delete,
            permissions.can_restore,
            permissions.can_update,
            queryClient,
        ],
    );

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
                            showTooltips: false,
                            columnSize: 136,
                        }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                        }}
                        appearance={{
                            size: 'md',
                            gridLines: 'horizontal',
                            rowStyle: 'soft-striped',
                        }}
                        searchPlaceholder="Buscar playlists..."
                    />
                </div>
            </div>
        </>
    );
}
