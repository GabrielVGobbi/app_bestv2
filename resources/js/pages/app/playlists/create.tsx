import { Form, Head, Link, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Save,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import previewSpotifyPlaylist from '@/actions/App/Modules/Playlists/Http/Controllers/SpotifyPlaylistPreviewController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import SpotifyPlaylistImporter from '@/pages/app/playlists/components/spotify-playlist-importer';
import PlaylistFormFields from '@/pages/app/playlists/partials/playlist-form-fields';
import { csrfCookie } from '@/routes/sanctum';
import type {
    Playlist,
    PlaylistFormOptions,
    SpotifyPlaylistPreview,
} from '@/types/app/playlist';

interface Props {
    formOptions: PlaylistFormOptions;
}

type CreateOrigin = 'spotify' | 'manual';

interface CreateFormSectionProps {
    title: string;
    description: string;
    children: ReactNode;
}

interface SpotifyPreviewIssue {
    title: string;
    message: string;
    tone?: 'warning' | 'danger' | 'info';
    canReload?: boolean;
    duplicate?: {
        name?: string | null;
        spotify_playlist_id?: string | null;
    };
}

async function refreshCsrfCookie(): Promise<void> {
    const response = await fetch(csrfCookie.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('CSRF cookie request failed.');
    }
}

function CreateFormSection({
    title,
    description,
    children,
}: CreateFormSectionProps) {
    return (
        <section className="grid grid-cols-1 gap-x-8 gap-y-4 py-6 md:grid-cols-[minmax(0,240px)_minmax(0,1fr)] lg:grid-cols-[minmax(240px,300px)_minmax(0,1fr)]">
            <div className="px-1 md:px-0">
                <h2 className="text-sm leading-6 font-semibold">{title}</h2>
                <p className="mt-1 max-w-sm text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="min-w-0">{children}</div>
        </section>
    );
}

function draftFromPreview(preview: SpotifyPlaylistPreview): Partial<Playlist> {
    return {
        name: preview.name ?? '',
        spotify_playlist_id: preview.spotify_playlist_id,
        spotify_url: preview.spotify_url,
        spotify_name: preview.name,
        spotify_description: preview.description,
        spotify_public: preview.public,
        spotify_image_url: preview.image_url,
        spotify_snapshot_id: preview.spotify_snapshot_id,
        spotify_owner_id: preview.spotify_owner_id,
        spotify_owner_name: preview.spotify_owner_name,
        spotify_tracks_total: preview.spotify_tracks_total,
        followers_count: preview.followers_count,
    };
}

export default function CreatePlaylist({ formOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: index.url() },
            { title: 'Nova Playlist', href: create.url() },
        ],
    });

    const [origin, setOrigin] = useState<CreateOrigin>('spotify');
    const [spotifyInput, setSpotifyInput] = useState('');
    const [spotifyPreview, setSpotifyPreview] =
        useState<SpotifyPlaylistPreview | null>(null);
    const [spotifyFieldError, setSpotifyFieldError] = useState<string | null>(
        null,
    );
    const [spotifyPreviewIssue, setSpotifyPreviewIssue] =
        useState<SpotifyPreviewIssue | null>(null);
    const [playlistDraft, setPlaylistDraft] = useState<
        Partial<Playlist> | undefined
    >();
    const [formKey, setFormKey] = useState('manual');
    const {
        post,
        setData: setSpotifyPreviewData,
        processing: previewProcessing,
    } = useHttp({
        spotify_input: spotifyInput,
    });

    const isSpotifyOrigin = origin === 'spotify';
    const showCommercialForm = !isSpotifyOrigin || spotifyPreview !== null;

    function handleOriginChange(value: string): void {
        if (value !== 'spotify' && value !== 'manual') {
            return;
        }

        setOrigin(value);
        setSpotifyFieldError(null);

        if (value === 'manual' && !spotifyPreview) {
            setFormKey('manual');
        }
    }

    function handleSpotifyInputChange(value: string): void {
        setSpotifyInput(value);
        setSpotifyPreviewData('spotify_input', value);
        setSpotifyFieldError(null);
    }

    async function searchSpotifyPlaylist(input: string): Promise<void> {
        const spotifyValue = input.trim();

        if (spotifyValue === '') {
            return;
        }

        setSpotifyPreview(null);
        setSpotifyFieldError(null);
        setSpotifyPreviewIssue(null);
        setSpotifyInput(spotifyValue);
        setSpotifyPreviewData('spotify_input', spotifyValue);

        try {
            await refreshCsrfCookie();
        } catch {
            setSpotifyPreviewIssue({
                title: 'Sessão inválida',
                message:
                    'Não foi possível preparar a sessão para buscar a playlist. Recarregue a página e tente novamente.',
                canReload: true,
            });

            return;
        }

        post(previewSpotifyPlaylist.url(), {
            onSuccess: (response) => {
                const body = response as { data?: SpotifyPlaylistPreview };

                if (!body.data) {
                    setSpotifyFieldError(
                        'O Spotify não retornou dados para esta playlist.',
                    );

                    return;
                }

                setSpotifyPreview(body.data);
                setPlaylistDraft(draftFromPreview(body.data));
                setFormKey(
                    `spotify-${body.data.spotify_playlist_id}-${Date.now()}`,
                );
            },
            onError: (errors) => {
                const message = errors.spotify_input;

                setSpotifyFieldError(
                    typeof message === 'string'
                        ? message
                        : 'Informe um link ou ID válido de playlist.',
                );
            },
            onHttpException: (response) => {
                const body = response.data as
                    | {
                          message?: string;
                          duplicate?: SpotifyPreviewIssue['duplicate'];
                          data?: {
                              message?: string;
                              duplicate?: SpotifyPreviewIssue['duplicate'];
                          };
                      }
                    | undefined;
                const isDuplicate = response.status === 409;
                const duplicate = body?.duplicate ?? body?.data?.duplicate;

                if (response.status === 419) {
                    setSpotifyPreviewIssue({
                        title: 'Sessão expirada',
                        message:
                            'Sua sessão expirou ou o token de segurança ficou inválido. Recarregue a página e tente novamente.',
                        tone: 'warning',
                        canReload: true,
                    });

                    return;
                }

                if (isDuplicate) {
                    setSpotifyPreviewIssue({
                        title: 'Playlist já cadastrada',
                        message:
                            'Esse ID público do Spotify já existe no cadastro. Use a playlist existente ou informe outro link.',
                        tone: 'info',
                        duplicate,
                    });

                    return;
                }

                setSpotifyFieldError(
                    body?.message ??
                        body?.data?.message ??
                        'Não foi possível consultar o Spotify agora.',
                );
            },
        });
    }

    return (
        <>
            <Head title="Nova Playlist" />

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
                        <h1 className="text-xl font-semibold">Nova Playlist</h1>
                        <p className="text-sm text-muted-foreground">
                            Cadastrar canal musical comercializável
                        </p>
                    </div>
                </div>
            </Header>

            <Dialog
                open={spotifyPreviewIssue !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSpotifyPreviewIssue(null);
                    }
                }}
            >
                <DialogContent className="overflow-hidden p-0 sm:max-w-md">
                    <div
                        className={cn(
                            'border-b px-6 py-5',
                            spotifyPreviewIssue?.tone === 'info' &&
                                'bg-emerald-50/80 dark:bg-emerald-950/20',
                            spotifyPreviewIssue?.tone === 'danger' &&
                                'bg-red-50/80 dark:bg-red-950/20',
                            (!spotifyPreviewIssue?.tone ||
                                spotifyPreviewIssue.tone === 'warning') &&
                                'bg-amber-50/80 dark:bg-amber-950/20',
                        )}
                    >
                        <DialogHeader className="gap-3 text-left">
                            <div
                                className={cn(
                                    'flex size-11 items-center justify-center rounded-full border bg-background shadow-sm',
                                    spotifyPreviewIssue?.tone === 'info' &&
                                        'border-emerald-200 text-emerald-700 dark:border-emerald-900 dark:text-emerald-300',
                                    spotifyPreviewIssue?.tone === 'danger' &&
                                        'border-red-200 text-red-700 dark:border-red-900 dark:text-red-300',
                                    (!spotifyPreviewIssue?.tone ||
                                        spotifyPreviewIssue.tone ===
                                            'warning') &&
                                        'border-amber-200 text-amber-700 dark:border-amber-900 dark:text-amber-300',
                                )}
                            >
                                {spotifyPreviewIssue?.tone === 'info' ? (
                                    <CheckCircle2 className="size-5" />
                                ) : (
                                    <AlertTriangle className="size-5" />
                                )}
                            </div>
                            <div className="space-y-1">
                                <DialogTitle className="text-lg">
                                    {spotifyPreviewIssue?.title ?? 'Atenção'}
                                </DialogTitle>
                                <DialogDescription className="leading-relaxed">
                                    {spotifyPreviewIssue?.message}
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                    </div>

                    <div className="space-y-4 px-6 py-5">
                        {spotifyPreviewIssue?.duplicate ? (
                            <div className="rounded-lg border bg-muted/30 p-4">
                                <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Playlist encontrada no cadastro
                                </p>
                                <p className="text-sm font-medium">
                                    {spotifyPreviewIssue.duplicate.name ??
                                        'Playlist cadastrada'}
                                </p>
                                {spotifyPreviewIssue.duplicate
                                    .spotify_playlist_id ? (
                                    <p className="mt-1 font-mono text-xs text-muted-foreground">
                                        {
                                            spotifyPreviewIssue.duplicate
                                                .spotify_playlist_id
                                        }
                                    </p>
                                ) : null}
                            </div>
                        ) : null}

                        <DialogFooter>
                            {spotifyPreviewIssue?.canReload ? (
                                <Button
                                    type="button"
                                    onClick={() => window.location.reload()}
                                >
                                    Recarregar página
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        setSpotifyPreviewIssue(null)
                                    }
                                >
                                    Entendi
                                </Button>
                            )}
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="page-wrapper-lg p-4 sm:p-6">
                <CreateFormSection
                    title="Origem da playlist"
                    description="Importe pelo Spotify para preencher capa e métricas, ou cadastre manualmente."
                >
                    <ToggleGroup
                        type="single"
                        value={origin}
                        onValueChange={handleOriginChange}
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <ToggleGroupItem
                            value="spotify"
                            aria-label="Importar do Spotify"
                            className="px-4"
                        >
                            Importar do Spotify
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="manual"
                            aria-label="Cadastro manual"
                            className="px-4"
                        >
                            Cadastro manual
                        </ToggleGroupItem>
                    </ToggleGroup>
                </CreateFormSection>

                {isSpotifyOrigin ? (
                    <CreateFormSection
                        title="Buscar no Spotify"
                        description="Cole o link público ou o ID. Os dados principais entram sozinhos no formulário."
                    >
                        <SpotifyPlaylistImporter
                            value={spotifyInput}
                            processing={previewProcessing}
                            preview={spotifyPreview}
                            error={spotifyFieldError}
                            onChange={handleSpotifyInputChange}
                            onSearch={searchSpotifyPlaylist}
                        />
                    </CreateFormSection>
                ) : null}

                {showCommercialForm ? (
                    <Form
                        action={store.url()}
                        method="post"
                        options={{ preserveScroll: true }}
                        onError={() =>
                            toast.error('Verifique os campos obrigatórios.')
                        }
                        className="flex h-full flex-col"
                    >
                        {({ errors, processing }) => (
                            <div>
                                <PlaylistFormFields
                                    key={formKey}
                                    playlist={playlistDraft}
                                    options={formOptions}
                                    errors={errors}
                                    layout="section-cards"
                                    spotifyImported={
                                        isSpotifyOrigin &&
                                        spotifyPreview !== null
                                    }
                                />

                                <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80 md:pl-[calc(240px+2rem)] lg:pl-[calc(300px+2rem)]">
                                    <div className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <p className="text-xs text-muted-foreground">
                                            Campos marcados com{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>{' '}
                                            são obrigatórios.
                                        </p>
                                        <div className="flex items-center justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                                disabled={processing}
                                            >
                                                <Link href={index.url()}>
                                                    Cancelar
                                                </Link>
                                            </Button>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                <Save />
                                                {processing
                                                    ? 'Salvando...'
                                                    : 'Salvar'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </Form>
                ) : (
                    <p className="py-8 text-sm text-muted-foreground md:pl-[calc(240px+2rem)] lg:pl-[calc(300px+2rem)]">
                        Cole o link para puxar capa e métricas, ou use o
                        cadastro manual.
                    </p>
                )}
            </div>
        </>
    );
}
