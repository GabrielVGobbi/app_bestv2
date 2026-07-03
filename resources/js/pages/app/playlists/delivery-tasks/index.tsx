import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Check, ExternalLink, Eye, Music2 } from 'lucide-react';
import { useMemo } from 'react';

import {
    complete,
    index,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistDeliveryTaskController';
import { index as playlistsIndex } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import { show as showSale } from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
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
import { apiRoutes } from '@/lib/api/api-routes';
import { formatDate } from '@/lib/utils';
import type {
    PlaylistDeliveryTask,
    PlaylistDeliveryTaskFilters,
    PlaylistDeliveryTaskPermissions,
    PlaylistDeliveryTaskTrack,
} from '@/types/app/playlist-delivery-task';

interface Props {
    filters: PlaylistDeliveryTaskFilters;
    permissions: PlaylistDeliveryTaskPermissions;
}

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criada em' },
    { value: 'completed_at', label: 'Concluída em' },
    { value: 'canceled_at', label: 'Cancelada em' },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Playlists\\Models\\PlaylistDeliveryTask',
    columnsEndpoint: apiRoutes.exports.columns,
};

const tableQueryKey = 'playlist-delivery-tasks-operational-v2';

function trackLabel(track: PlaylistDeliveryTaskTrack): string {
    return track.name ?? track.spotify_url ?? '-';
}

function artistLabel(track: PlaylistDeliveryTaskTrack): string {
    if (!track.artists?.length) {
        return '';
    }

    return track.artists
        .map((artist) => (typeof artist === 'string' ? artist : artist.name))
        .filter(Boolean)
        .join(', ');
}

function taskTrack(task: PlaylistDeliveryTask): PlaylistDeliveryTaskTrack {
    if (task.track?.name || task.track?.spotify_url) {
        return task.track;
    }

    return task.type === 'remove_track' ? task.from_track : task.to_track;
}

function spotifyLink(task: PlaylistDeliveryTask): string | null {
    return taskTrack(task).spotify_url;
}

export default function PlaylistDeliveryTaskIndex({
    filters,
    permissions,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: playlistsIndex.url() },
            { title: 'Fila de Playlists', href: index.url() },
        ],
    });

    const queryClient = useQueryClient();

    const columns = useMemo<ColumnDef<PlaylistDeliveryTask>[]>(
        () => [
            {
                id: 'type',
                accessorKey: 'type',
                header: 'Tarefa',
                size: 150,
                cell: ({ row }) => (
                    <StatusBadge presenter={row.original.type_presenter} />
                ),
                meta: { label: 'Tarefa' },
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                size: 150,
                cell: ({ row }) => (
                    <StatusBadge presenter={row.original.status_presenter} />
                ),
                meta: { label: 'Status' },
            },
            {
                id: 'playlist_name',
                accessorKey: 'playlist_name',
                header: 'Playlist',
                size: 240,
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {row.original.playlist_name ?? '-'}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.original.top_name ?? 'TOP não informado'}
                        </p>
                    </div>
                ),
                meta: { label: 'Playlist' },
            },
            {
                id: 'to_track_name',
                accessorKey: 'to_track.name',
                header: 'Música',
                size: 280,
                cell: ({ row }) => {
                    const track = taskTrack(row.original);

                    return (
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {trackLabel(track)}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {artistLabel(track) || 'Artista não informado'}
                            </p>
                        </div>
                    );
                },
                meta: { label: 'Música' },
            },
            {
                id: 'from_track_name',
                accessorKey: 'from_track.name',
                header: 'Música anterior',
                size: 240,
                cell: ({ row }) =>
                    row.original.type === 'replace_track'
                        ? trackLabel(row.original.from_track)
                        : '-',
                meta: { label: 'Música anterior', initialVisibility: false },
            },
            {
                id: 'sale_order_number',
                accessorKey: 'sale_order_number',
                header: 'Venda',
                size: 180,
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {row.original.sale_order_number ?? '-'}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.original.client_name ?? 'Cliente não informado'}
                        </p>
                    </div>
                ),
                meta: { label: 'Venda' },
            },
            {
                id: 'created_at',
                accessorKey: 'created_at',
                header: 'Criada em',
                size: 170,
                cell: ({ row }) => formatDate(row.original.created_at),
                meta: { label: 'Criada em' },
            },
            {
                id: 'completed_at',
                accessorKey: 'completed_at',
                header: 'Concluída em',
                size: 170,
                cell: ({ row }) => formatDate(row.original.completed_at),
                meta: { label: 'Concluída em', initialVisibility: false },
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
                id: 'type',
                label: 'Tipo',
                type: 'select',
                operator: 'eq',
                options: filters.types,
            },
            {
                id: 'playlist_name',
                label: 'Playlist',
                type: 'text',
                operator: 'contains',
                placeholder: 'Nome da playlist',
            },
            {
                id: 'client_name',
                label: 'Cliente',
                type: 'text',
                operator: 'contains',
                placeholder: 'Nome do cliente',
            },
            {
                id: 'sale_order_number',
                label: 'Venda',
                type: 'text',
                operator: 'contains',
                placeholder: 'VEN-000000',
            },
        ],
        [filters.statuses, filters.types],
    );

    const actions = useMemo<DataTableAction<PlaylistDeliveryTask>[]>(() => {
        const tableActions: DataTableAction<PlaylistDeliveryTask>[] = [
            {
                label: 'Abrir playlist',
                icon: <Music2 className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Abrir playlist no Spotify',
                disabled: (task) => !task.playlist?.spotify_url,
                onClick: (task) => {
                    if (task.playlist?.spotify_url) {
                        window.open(task.playlist.spotify_url, '_blank', 'noopener,noreferrer');
                    }
                },
            },
            {
                label: 'Abrir música',
                icon: <ExternalLink className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Abrir música no Spotify',
                disabled: (task) => !spotifyLink(task),
                onClick: (task) => {
                    const url = spotifyLink(task);

                    if (url) {
                        window.open(url, '_blank', 'noopener,noreferrer');
                    }
                },
            },
        ];

        tableActions.push({
            label: 'Ver venda',
            icon: <Eye className="size-4" />,
            variant: 'outline',
            showLabel: false,
            tooltip: 'Ver venda',
            disabled: (task) => !task.sale?.uuid,
            href: (task) => (task.sale?.uuid ? showSale.url(task.sale.uuid) : '#'),
        });

        if (permissions.can_complete) {
            tableActions.push({
                label: 'Concluir',
                icon: <Check className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Concluir checklist',
                disabled: (task) => task.status !== 'pending',
                onClick: (task) => {
                    router.patch(complete.url(task.uuid), undefined, {
                        preserveScroll: true,
                        onSuccess: () => {
                            void queryClient.invalidateQueries({
                                queryKey: [tableQueryKey],
                            });
                        },
                    });
                },
            });
        }

        return tableActions;
    }, [permissions.can_complete, queryClient]);

    return (
        <>
            <Head title="Fila de Playlists" />

            <div className="page-wrapper-xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Fila de Playlists"
                            description="Checklist operacional para adicionar, remover e trocar músicas manualmente no Spotify."
                        />
                        <Button variant="outline" asChild>
                            <Link href={playlistsIndex.url()}>
                                <Music2 className="size-4" />
                                Playlists
                            </Link>
                        </Button>
                    </div>

                    <DataTable<PlaylistDeliveryTask>
                        queryKey={tableQueryKey}
                        endpoint={apiRoutes.playlistDeliveryTasks.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(task) => task.uuid}
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
                        searchPlaceholder="Buscar por música, playlist, cliente ou venda..."
                        emptyMessage="Nenhuma tarefa de playlist encontrada."
                    />
                </div>
            </div>
        </>
    );
}
