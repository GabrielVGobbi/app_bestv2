import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, ListMusic, Mic2, Search, Sparkles } from 'lucide-react';

import {
    artists,
    playlists,
} from '@/actions/App/Modules/ExternalApi/Modules/Spotify/Http/Controllers/SpotifyBrowserController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type SpotifyArtist = {
    id: string;
    name: string;
    uri: string;
    genres: string[];
    popularity: number;
    images: Array<{ url: string; width?: number; height?: number }>;
    spotifyUrl: string | null;
};

interface PageProps {
    query: string;
    artists: SpotifyArtist[];
    isFake: boolean;
}

export default function SpotifyArtists({
    query,
    artists: artistResults,
    isFake,
}: PageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Artistas do Spotify', href: artists.url() }],
    });

    return (
        <>
            <Head title="Spotify Artistas" />

            <Header
                title="Artistas do Spotify"
                subtitle="Busque artistas usando o módulo ExternalApi. Em fake mode, os dados mockados passam pelo mesmo fluxo de serviço."
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
                        <Link href={playlists()}>
                            <ListMusic className="size-4" />
                            Playlists
                        </Link>
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <Card className="rounded-lg py-4">
                        <CardContent className="px-4">
                            <Form
                                {...artists.form()}
                                options={{ preserveScroll: true }}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="q"
                                        defaultValue={query}
                                        placeholder="Buscar artista, banda ou projeto..."
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

                    {artistResults.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                            <Mic2 className="mb-3 size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Nenhum artista encontrado.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {artistResults.map((artist) => (
                                <Card
                                    key={artist.id}
                                    className="overflow-hidden rounded-lg py-0"
                                >
                                    <div className="flex gap-4 p-4">
                                        <div className="size-20 shrink-0 overflow-hidden rounded-md bg-muted">
                                            {artist.images[0]?.url ? (
                                                <img
                                                    src={artist.images[0].url}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center">
                                                    <Mic2 className="size-7 text-muted-foreground" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1 space-y-3">
                                            <div className="min-w-0">
                                                <h3 className="truncate text-sm font-semibold">
                                                    {artist.name}
                                                </h3>
                                                <p className="truncate font-mono text-xs text-muted-foreground">
                                                    {artist.uri}
                                                </p>
                                            </div>

                                            <div className="flex flex-wrap gap-1.5">
                                                {artist.genres
                                                    .slice(0, 3)
                                                    .map((genre) => (
                                                        <Badge
                                                            key={genre}
                                                            variant="secondary"
                                                            className="rounded-full text-2xs"
                                                        >
                                                            {genre}
                                                        </Badge>
                                                    ))}
                                            </div>

                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-2xs text-muted-foreground">
                                                        Popularidade
                                                    </p>
                                                    <p className="text-sm font-semibold tabular-nums">
                                                        {artist.popularity}/100
                                                    </p>
                                                </div>
                                                {artist.spotifyUrl && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                artist.spotifyUrl
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
