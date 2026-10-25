import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ExternalLink,
    ListMusic,
    Mic2,
    Search,
    Sparkles,
    Users,
} from 'lucide-react';

import {
    artists,
    playlists,
} from '@/actions/App/Modules/ExternalApi/Modules/Spotify/Http/Controllers/SpotifyBrowserController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type SpotifyPlaylist = {
    id: string;
    name: string;
    uri: string;
    description: string | null;
    public: boolean;
    collaborative: boolean;
    images: Array<{ url: string; width?: number; height?: number }>;
    owner: { id?: string; display_name?: string };
    tracksTotal: number;
    spotifyUrl: string | null;
};

interface PageProps {
    query: string;
    playlists: SpotifyPlaylist[];
    isFake: boolean;
}

export default function SpotifyPlaylists({
    query,
    playlists: playlistResults,
    isFake,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Playlists do Spotify', href: playlists.url() }],
    });

    return (
        <>
            <Head title="Spotify Playlists" />

            <Header
                title="Playlists do Spotify"
                subtitle="Visualize playlists vindas da integração Spotify para validar autenticação, mock mode e transformação de DTOs."
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={isFake ? 'secondary' : 'default'}
                        className="h-8 rounded-full px-3"
                    >
                        <Sparkles className="size-3.5" />
                        {isFake ? 'Mock ativo' : 'API real'}
                    </Badge>
                    <Button variant="outline" asChild>
                        <Link href={artists()}>
                            <Mic2 className="size-4" />
                            Artistas
                        </Link>
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <Card className="rounded-lg py-4">
                        <CardContent className="px-4">
                            <Form
                                {...playlists.form()}
                                options={{ preserveScroll: true }}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="q"
                                        defaultValue={query}
                                        placeholder="Buscar playlist por tema, artista ou humor..."
                                        className="pl-9"
                                    />
                                </div>
                                <Button type="submit">
                                    <Search className="size-4" />
                                    Buscar
                                </Button>
                            </Form>
                        </CardContent>
                    </Card>

                    {playlistResults.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                            <ListMusic className="mb-3 size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Nenhuma playlist encontrada.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {playlistResults.map((playlist) => (
                                <Card
                                    key={playlist.id}
                                    className="overflow-hidden rounded-lg py-0"
                                >
                                    <div className="grid grid-cols-[112px_1fr] gap-4 p-4">
                                        <div className="aspect-square overflow-hidden rounded-md bg-muted">
                                            {playlist.images[0]?.url ? (
                                                <img
                                                    src={playlist.images[0].url}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center">
                                                    <ListMusic className="size-8 text-muted-foreground" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0 space-y-3">
                                            <div className="space-y-1">
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <h3 className="truncate text-sm font-semibold">
                                                        {playlist.name}
                                                    </h3>
                                                    {playlist.collaborative && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="rounded-full text-2xs"
                                                        >
                                                            Colab
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                                    {playlist.description ||
                                                        'Playlist sem descrição.'}
                                                </p>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-3 text-xs">
                                                <span className="inline-flex items-center gap-1 text-muted-foreground">
                                                    <Users className="size-3.5" />
                                                    {playlist.owner
                                                        .display_name ??
                                                        playlist.owner.id ??
                                                        'Spotify'}
                                                </span>
                                                <span className="font-medium tabular-nums">
                                                    {playlist.tracksTotal}{' '}
                                                    faixas
                                                </span>
                                                <Badge
                                                    variant="secondary"
                                                    className="rounded-full text-2xs"
                                                >
                                                    {playlist.public
                                                        ? 'Pública'
                                                        : 'Privada'}
                                                </Badge>
                                            </div>

                                            <div className="flex items-center justify-between gap-3">
                                                <p className="truncate font-mono text-xs text-muted-foreground">
                                                    {playlist.uri}
                                                </p>
                                                {playlist.spotifyUrl && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                playlist.spotifyUrl
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <ExternalLink className="size-3.5" />
                                                            Abrir
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
