import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    ExternalLink,
    ListMusic,
    Radio,
    Trash2,
    Users,
} from 'lucide-react';

import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import type { PlaylistResource, PlaylistStatus } from '@/types/app/playlist';

import { PlaylistSubNav } from './components/playlist-sub-nav';
import { PlaylistStatusMenu } from './components/playlist-status-menu';

interface Props {
    playlistData: PlaylistResource;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
}

const statusVariant: Record<
    PlaylistStatus,
    'default' | 'outline' | 'secondary'
> = {
    active: 'default',
    inactive: 'outline',
    archived: 'secondary',
};

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

function metadataSummary(
    metadata: PlaylistResource['data']['external_metadata'],
): string {
    if (!metadata) {
        return '{}';
    }

    return JSON.stringify(metadata, null, 2);
}

export default function ShowPlaylist({ playlistData, permissions }: Props) {
    const playlist = playlistData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: index.url() },
            { title: playlist.name, href: show.url(playlist.uuid) },
        ],
    });

    return (
        <>
            <Head title={playlist.name} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            {playlist.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {playlist.slug}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <PlaylistSubNav
                        playlistUuid={playlist.uuid}
                        currentPage="show"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <Button variant="destructive" size="sm" asChild>
                                <Link
                                    href={destroy.url(playlist.uuid)}
                                    method="delete"
                                >
                                    <Trash2 className="size-4" />
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
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-secondary text-primary">
                                    <ListMusic className="size-12" />
                                </div>

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
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        playlist.status
                                                    ]
                                                }
                                            >
                                                {playlist.status_label}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {playlist.genre?.name ?? 'Sem gênero'}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-2 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <Users className="size-4 shrink-0" />
                                        <span>
                                            {playlist.followers_count?.toLocaleString(
                                                'pt-BR',
                                            ) ?? '-'}{' '}
                                            seguidores
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Radio className="size-4 shrink-0" />
                                        <span>
                                            {playlist.estimated_reach?.toLocaleString(
                                                'pt-BR',
                                            ) ?? '-'}{' '}
                                            alcance
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <CalendarDays className="size-3.5" />
                                    <span>
                                        Criada {formatDate(playlist.created_at)}
                                    </span>
                                </div>
                                <p className="mt-1">
                                    Última edição{' '}
                                    {formatDate(playlist.updated_at)}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Tabs defaultValue="summary" className="gap-4">
                            <TabsList className="w-full justify-start overflow-x-auto sm:w-fit">
                                <TabsTrigger value="summary">
                                    Resumo
                                </TabsTrigger>
                                <TabsTrigger value="links">Links</TabsTrigger>
                                <TabsTrigger value="metadata">
                                    Metadata
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="summary" className="space-y-4">
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
                            </TabsContent>

                            <TabsContent value="links">
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
                                                        <ExternalLink className="size-4" />
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
                                                        <ExternalLink className="size-4" />
                                                        YouTube
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="metadata">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Metadata legada</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-[360px] overflow-auto rounded-md border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground">
                                            <code>
                                                {metadataSummary(
                                                    playlist.external_metadata,
                                                )}
                                            </code>
                                        </pre>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
