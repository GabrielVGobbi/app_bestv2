import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowLeft,
    ArrowUpRight,
    CalendarDays,
    LineChart as LineChartIcon,
    Search,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { index as playlistsIndex } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PanelTable,
    PanelTableBody,
    PanelTableHead,
    PanelTableRow,
} from '@/components/ui/panel-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import type {
    MusicGenre,
    PlaylistMetricRow,
    PlaylistMetricsDashboard,
} from '@/types/app/playlist';

interface Props {
    dashboard: PlaylistMetricsDashboard;
    filters: {
        period_days: number;
        search: string;
        music_genre_id: number | null;
        status: string;
        genres: MusicGenre[];
    };
}

function compactNumber(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function signedNumber(value: number): string {
    const formatted = value.toLocaleString('pt-BR');

    return value > 0 ? `+${formatted}` : formatted;
}

function deltaClass(value: number): string {
    if (value > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (value < 0) {
        return 'text-red-600 dark:text-red-400';
    }

    return 'text-muted-foreground';
}

function syncPriorityLabel(value: PlaylistMetricRow['spotify_sync_priority']): string {
    const labels: Record<PlaylistMetricRow['spotify_sync_priority'], string> = {
        high: 'Alta',
        normal: 'Normal',
        low: 'Baixa',
        paused: 'Pausada',
    };

    return labels[value] ?? value;
}

function SummaryCard({
    title,
    value,
    detail,
    tone,
}: {
    title: string;
    value: string;
    detail: string;
    tone: 'neutral' | 'up' | 'down';
}) {
    const Icon =
        tone === 'up' ? ArrowUpRight : tone === 'down' ? ArrowDownRight : Users;

    return (
        <Card>
            <CardContent className="flex min-h-28 items-center justify-between gap-4 p-5">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">{title}</p>
                    <p className="mt-1 text-2xl font-semibold tracking-normal">
                        {value}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detail}
                    </p>
                </div>
                <div className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted/40">
                    <Icon className="size-5 text-muted-foreground" />
                </div>
            </CardContent>
        </Card>
    );
}

function RankingList({
    title,
    rows,
    empty,
}: {
    title: string;
    rows: PlaylistMetricRow[];
    empty: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        {empty}
                    </div>
                ) : (
                    <ul className="divide-y">
                        {rows.map((row) => (
                            <li
                                key={row.playlist_uuid}
                                className="flex items-center justify-between gap-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {row.playlist_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.genre ?? 'Sem gênero'}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p
                                        className={`text-sm font-semibold tabular-nums ${deltaClass(row.followers_delta)}`}
                                    >
                                        {signedNumber(row.followers_delta)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {compactNumber(row.followers_count)}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default function PlaylistMetricsIndex({ dashboard, filters }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: playlistsIndex.url() },
            { title: 'Métricas', href: '/playlists/metrics' },
        ],
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const form = new FormData(event.currentTarget);

        router.get(
            '/playlists/metrics',
            {
                search: form.get('search')?.toString() ?? '',
                period_days: form.get('period_days')?.toString() ?? '30',
                music_genre_id:
                    form.get('music_genre_id')?.toString() === 'all'
                        ? ''
                        : form.get('music_genre_id')?.toString(),
                status:
                    form.get('status')?.toString() === 'all'
                        ? ''
                        : form.get('status')?.toString(),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title="Métricas de playlists" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={playlistsIndex.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Métricas de playlists
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Histórico de seguidores, capas e alterações vindas
                            do Spotify.
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="space-y-4 p-4">
                    <form
                        onSubmit={submit}
                        className="grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))_auto]"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="metrics-search">Buscar</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="metrics-search"
                                    name="search"
                                    defaultValue={filters.search}
                                    className="pl-9"
                                    placeholder="Playlist, Spotify ou parceiro"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="metrics-period">Período</Label>
                            <Select
                                name="period_days"
                                defaultValue={String(filters.period_days)}
                            >
                                <SelectTrigger
                                    id="metrics-period"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="7">7 dias</SelectItem>
                                    <SelectItem value="30">30 dias</SelectItem>
                                    <SelectItem value="90">90 dias</SelectItem>
                                    <SelectItem value="365">12 meses</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="metrics-genre">Gênero</Label>
                            <Select
                                name="music_genre_id"
                                defaultValue={
                                    filters.music_genre_id
                                        ? String(filters.music_genre_id)
                                        : 'all'
                                }
                            >
                                <SelectTrigger
                                    id="metrics-genre"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {filters.genres.map((genre) => (
                                        <SelectItem
                                            key={genre.id}
                                            value={String(genre.id)}
                                        >
                                            {genre.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="metrics-status">Status</Label>
                            <Select
                                name="status"
                                defaultValue={filters.status || 'all'}
                            >
                                <SelectTrigger
                                    id="metrics-status"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="active">Ativa</SelectItem>
                                    <SelectItem value="inactive">
                                        Inativa
                                    </SelectItem>
                                    <SelectItem value="archived">
                                        Arquivada
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end sm:col-span-2 xl:col-span-1">
                            <Button type="submit" className="h-9 w-full">
                                Aplicar
                            </Button>
                        </div>
                    </form>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                        <SummaryCard
                            title="Seguidores atuais"
                            value={dashboard.summary.total_followers.toLocaleString(
                                'pt-BR',
                            )}
                            detail="Soma das playlists filtradas"
                            tone="neutral"
                        />
                        <SummaryCard
                            title="Crescimento líquido"
                            value={signedNumber(dashboard.summary.net_growth)}
                            detail={`Últimos ${filters.period_days} dias`}
                            tone={
                                dashboard.summary.net_growth < 0
                                    ? 'down'
                                    : 'up'
                            }
                        />
                        <SummaryCard
                            title="Em alta"
                            value={String(dashboard.summary.rising_count)}
                            detail="Playlists com delta positivo"
                            tone="up"
                        />
                        <SummaryCard
                            title="Em queda"
                            value={String(dashboard.summary.falling_count)}
                            detail="Playlists com delta negativo"
                            tone="down"
                        />
                        <SummaryCard
                            title="Falhas recentes"
                            value={String(dashboard.summary.failed_sync_count)}
                            detail="Sincronizações com erro no período"
                            tone={
                                dashboard.summary.failed_sync_count > 0
                                    ? 'down'
                                    : 'neutral'
                            }
                        />
                        <SummaryCard
                            title="Sync pendente"
                            value={String(dashboard.summary.due_sync_count)}
                            detail="Playlists vencidas na fila"
                            tone={
                                dashboard.summary.due_sync_count > 0
                                    ? 'down'
                                    : 'neutral'
                            }
                        />
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,0.7fr)]">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between gap-4">
                                <CardTitle>Evolução de seguidores</CardTitle>
                                <LineChartIcon className="size-5 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="h-80">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart data={dashboard.series}>
                                            <defs>
                                                <linearGradient
                                                    id="followersGradient"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="var(--primary)"
                                                        stopOpacity={0.28}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="var(--primary)"
                                                        stopOpacity={0.02}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                className="stroke-muted"
                                            />
                                            <XAxis
                                                dataKey="date"
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                            />
                                            <YAxis
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                                tickFormatter={compactNumber}
                                            />
                                            <Tooltip
                                                formatter={(value) =>
                                                    Number(
                                                        value,
                                                    ).toLocaleString('pt-BR')
                                                }
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="followers"
                                                stroke="var(--primary)"
                                                fill="url(#followersGradient)"
                                                strokeWidth={2}
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-4">
                            <RankingList
                                title="Maiores altas"
                                rows={dashboard.rankings.rising}
                                empty="Nenhuma playlist cresceu no período."
                            />
                            <RankingList
                                title="Maiores quedas"
                                rows={dashboard.rankings.falling}
                                empty="Nenhuma playlist caiu no período."
                            />
                        </div>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Comparativo por playlist</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PanelTable>
                                <PanelTableHead
                                    className="grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(6rem,0.55fr))]"
                                    columns={[
                                        { label: 'Playlist' },
                                        { label: 'Seguidores', align: 'right' },
                                        { label: 'Delta', align: 'right' },
                                        { label: 'Faixas', align: 'right' },
                                        { label: 'Status' },
                                        { label: 'Última sync' },
                                    ]}
                                />
                                <PanelTableBody as="ul">
                                    {dashboard.rows.map((row) => (
                                        <PanelTableRow
                                            as="li"
                                            key={row.playlist_uuid}
                                            className="lg:grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(6rem,0.55fr))]"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                {row.cover_url ? (
                                                    <img
                                                        src={row.cover_url}
                                                        alt=""
                                                        className="size-11 rounded-md object-cover"
                                                        loading="lazy"
                                                        width={44}
                                                        height={44}
                                                    />
                                                ) : (
                                                    <div className="flex size-11 items-center justify-center rounded-md bg-muted">
                                                        <Users className="size-5 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {row.playlist_name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {row.spotify_name ??
                                                            row.genre ??
                                                            'Sem dados Spotify'}
                                                    </p>
                                                </div>
                                            </div>
                                            <p className="text-sm tabular-nums lg:text-right">
                                                {row.followers_count.toLocaleString(
                                                    'pt-BR',
                                                )}
                                            </p>
                                            <p
                                                className={`text-sm font-medium tabular-nums lg:text-right ${deltaClass(row.followers_delta)}`}
                                            >
                                                {signedNumber(
                                                    row.followers_delta,
                                                )}
                                            </p>
                                            <p className="text-sm tabular-nums lg:text-right">
                                                {row.spotify_tracks_total ?? '-'}
                                            </p>
                                            <Badge variant="outline">
                                                {row.status_label}
                                            </Badge>
                                            <div className="space-y-1 text-xs text-muted-foreground">
                                                <p>
                                                    {row.spotify_last_synced_at
                                                        ? formatDate(
                                                              row.spotify_last_synced_at,
                                                          )
                                                        : '-'}
                                                </p>
                                                <p>
                                                    {syncPriorityLabel(
                                                        row.spotify_sync_priority,
                                                    )}
                                                    {row.next_spotify_sync_at
                                                        ? ` · próxima ${formatDate(row.next_spotify_sync_at)}`
                                                        : ''}
                                                </p>
                                                {row.spotify_sync_failures_count >
                                                    0 && (
                                                    <p className="text-red-600 dark:text-red-400">
                                                        {row.spotify_sync_failures_count}{' '}
                                                        falha(s)
                                                    </p>
                                                )}
                                            </div>
                                        </PanelTableRow>
                                    ))}
                                </PanelTableBody>
                            </PanelTable>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline de alterações</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {dashboard.events.length === 0 ? (
                                <div className="rounded-md border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                    Nenhuma alteração registrada no período.
                                </div>
                            ) : (
                                <ul className="divide-y">
                                    {dashboard.events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="flex items-start gap-3 py-3"
                                        >
                                            <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border bg-muted/40">
                                                <CalendarDays className="size-4 text-muted-foreground" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {event.playlist_name} ·{' '}
                                                    {event.label}
                                                </p>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {event.old_value ?? '-'} →{' '}
                                                    {event.new_value ?? '-'}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatDate(
                                                        event.occurred_at,
                                                    )}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
