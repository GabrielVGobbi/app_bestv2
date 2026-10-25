import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    CalendarDays,
    Filter,
    History,
    LineChart as LineChartIcon,
    ListMusic,
    Search,
    TrendingDown,
    TrendingUp,
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
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
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

const periodOptions = [
    { value: '7', label: '7 dias' },
    { value: '30', label: '30 dias' },
    { value: '90', label: '90 dias' },
    { value: '365', label: '12 meses' },
];

const statusOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'active', label: 'Ativa' },
    { value: 'inactive', label: 'Inativa' },
    { value: 'archived', label: 'Arquivada' },
];

const chartTooltipStyle = {
    background: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: 8,
    color: 'var(--foreground)',
    fontSize: 12,
};

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
        return 'text-success';
    }

    if (value < 0) {
        return 'text-destructive';
    }

    return 'text-muted-foreground';
}

function syncPriorityLabel(
    value: PlaylistMetricRow['spotify_sync_priority'],
): string {
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
                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                        {value}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detail}
                    </p>
                </div>
                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            </CardContent>
        </Card>
    );
}

function RankingList({
    title,
    rows,
    empty,
    tone,
}: {
    title: string;
    rows: PlaylistMetricRow[];
    empty: string;
    tone: 'up' | 'down';
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <EmptyState
                        icon={tone === 'up' ? TrendingUp : TrendingDown}
                        title={empty}
                    />
                ) : (
                    <ul className="divide-y divide-separator">
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
                                    <p className="text-xs text-muted-foreground tabular-nums">
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

            <Header
                title="Métricas de playlists"
                subtitle="Histórico de seguidores, capas e alterações vindas do Spotify"
                backHref={playlistsIndex.url()}
            />

            <div className="page-wrapper-lg">
                <div className="space-y-4 p-4">
                    <Card className="py-4">
                        <CardContent className="px-4">
                            <form
                                onSubmit={submit}
                                className="grid gap-3 md:grid-cols-[minmax(12rem,1fr)_10rem_12rem_10rem_auto] md:items-end"
                            >
                                <FormField label="Buscar">
                                    <div className="relative">
                                        <Search
                                            aria-hidden="true"
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            id="metrics-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            className="pl-9"
                                            placeholder="Playlist, Spotify ou parceiro"
                                        />
                                    </div>
                                </FormField>
                                <FormField id="metrics-period" label="Período">
                                    <Select
                                        name="period_days"
                                        defaultValue={String(
                                            filters.period_days,
                                        )}
                                    >
                                        <SelectTrigger
                                            id="metrics-period"
                                            className="w-full"
                                            icon={CalendarDays}
                                        />
                                        <SelectContent>
                                            {periodOptions.map(
                                                (option, index) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        index={index}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField id="metrics-genre" label="Gênero">
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
                                            icon={ListMusic}
                                        />
                                        <SelectContent>
                                            <SelectItem index={0} value="all">
                                                Todos
                                            </SelectItem>
                                            {filters.genres.map(
                                                (genre, index) => (
                                                    <SelectItem
                                                        key={genre.id}
                                                        index={index + 1}
                                                        value={String(genre.id)}
                                                    >
                                                        {genre.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField id="metrics-status" label="Status">
                                    <Select
                                        name="status"
                                        defaultValue={filters.status || 'all'}
                                    >
                                        <SelectTrigger
                                            id="metrics-status"
                                            className="w-full"
                                        />
                                        <SelectContent>
                                            {statusOptions.map(
                                                (option, index) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        index={index}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <Button type="submit" className="w-full">
                                    <Filter
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Aplicar
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

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
                                dashboard.summary.net_growth < 0 ? 'down' : 'up'
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
                                <LineChartIcon
                                    className="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </CardHeader>
                            <CardContent>
                                {dashboard.series.length === 0 ? (
                                    <EmptyState
                                        icon={LineChartIcon}
                                        title="Sem histórico no período"
                                        description="As sincronizações com o Spotify alimentam esta curva."
                                    />
                                ) : (
                                    <div className="h-80">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
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
                                                            stopColor="var(--chart-1)"
                                                            stopOpacity={0.28}
                                                        />
                                                        <stop
                                                            offset="95%"
                                                            stopColor="var(--chart-1)"
                                                            stopOpacity={0.02}
                                                        />
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid
                                                    strokeDasharray="3 3"
                                                    stroke="var(--border)"
                                                    vertical={false}
                                                />
                                                <XAxis
                                                    dataKey="date"
                                                    tickLine={false}
                                                    axisLine={false}
                                                    fontSize={12}
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                    }}
                                                />
                                                <YAxis
                                                    tickLine={false}
                                                    axisLine={false}
                                                    fontSize={12}
                                                    tick={{
                                                        fill: 'var(--muted-foreground)',
                                                    }}
                                                    tickFormatter={
                                                        compactNumber
                                                    }
                                                />
                                                <Tooltip
                                                    cursor={{
                                                        stroke: 'var(--border)',
                                                    }}
                                                    contentStyle={
                                                        chartTooltipStyle
                                                    }
                                                    labelStyle={{
                                                        color: 'var(--muted-foreground)',
                                                    }}
                                                    itemStyle={{
                                                        color: 'var(--foreground)',
                                                    }}
                                                    formatter={(value) =>
                                                        Number(
                                                            value,
                                                        ).toLocaleString(
                                                            'pt-BR',
                                                        )
                                                    }
                                                />
                                                <Area
                                                    type="monotone"
                                                    dataKey="followers"
                                                    name="Seguidores"
                                                    stroke="var(--chart-1)"
                                                    fill="url(#followersGradient)"
                                                    strokeWidth={2}
                                                />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid gap-4">
                            <RankingList
                                title="Maiores altas"
                                rows={dashboard.rankings.rising}
                                empty="Nenhuma playlist cresceu no período"
                                tone="up"
                            />
                            <RankingList
                                title="Maiores quedas"
                                rows={dashboard.rankings.falling}
                                empty="Nenhuma playlist caiu no período"
                                tone="down"
                            />
                        </div>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Comparativo por playlist</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {dashboard.rows.length === 0 ? (
                                <EmptyState
                                    icon={ListMusic}
                                    title="Nenhuma playlist nos filtros atuais"
                                    description="Ajuste a busca, o gênero ou o status para comparar playlists."
                                />
                            ) : (
                                <div className="overflow-hidden rounded-lg border border-border">
                                    <div className="hidden grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(6rem,0.55fr))] gap-3 border-b border-separator bg-muted px-4 py-2 text-xs font-semibold text-muted-foreground lg:grid">
                                        <span>Playlist</span>
                                        <span className="text-right">
                                            Seguidores
                                        </span>
                                        <span className="text-right">
                                            Delta
                                        </span>
                                        <span className="text-right">
                                            Faixas
                                        </span>
                                        <span>Status</span>
                                        <span>Última sync</span>
                                    </div>
                                    <ul className="divide-y divide-separator">
                                        {dashboard.rows.map((row) => (
                                            <li
                                                key={row.playlist_uuid}
                                                className="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(6rem,0.55fr))] lg:items-center"
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
                                                            <Users
                                                                className="size-5 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
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
                                                    {row.spotify_tracks_total ??
                                                        '-'}
                                                </p>
                                                <div>
                                                    <Badge variant="outline">
                                                        {row.status_label}
                                                    </Badge>
                                                </div>
                                                <div className="space-y-1 text-xs text-muted-foreground tabular-nums">
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
                                                        <p className="text-destructive">
                                                            {
                                                                row.spotify_sync_failures_count
                                                            }{' '}
                                                            falha(s)
                                                        </p>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline de alterações</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {dashboard.events.length === 0 ? (
                                <EmptyState
                                    icon={History}
                                    title="Nenhuma alteração registrada no período"
                                />
                            ) : (
                                <ul className="divide-y divide-separator">
                                    {dashboard.events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="flex items-start gap-3 py-3"
                                        >
                                            <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                                <CalendarDays
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
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
                                                <p className="mt-1 text-xs text-muted-foreground tabular-nums">
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
