import { Form, Head, Link, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Loader2,
    Save,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import previewSpotifyPlaylist from '@/actions/App/Modules/Playlists/Http/Controllers/SpotifyPlaylistPreviewController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
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

const issueToneClasses: Record<
    NonNullable<SpotifyPreviewIssue['tone']>,
    string
> = {
    info: 'bg-info-soft text-info-soft-foreground',
    danger: 'bg-destructive-soft text-destructive-soft-foreground',
    warning: 'bg-warning-soft text-warning-soft-foreground',
};

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

export default function CreatePlaylist({ formOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: index.url() },
            { title: 'Nova Playlist', href: create.url() },
        ],
    });

    const [spotifyInput, setSpotifyInput] = useState('');
    const [spotifyPreview, setSpotifyPreview] =
        useState<SpotifyPlaylistPreview | null>(null);
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

    async function submitSpotifyPreview(
        event: FormEvent<HTMLFormElement>,
    ): Promise<void> {
        event.preventDefault();
        setSpotifyPreview(null);
        setSpotifyPreviewIssue(null);

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
                    setSpotifyPreviewIssue({
                        title: 'Não foi possível buscar a playlist',
                        message:
                            'O Spotify não retornou dados para esta playlist.',
                    });

                    return;
                }

                setSpotifyPreview(body.data);
            },
            onError: (errors) => {
                const message = errors.spotify_input;

                setSpotifyPreviewIssue({
                    title: 'Não foi possível buscar a playlist',
                    message:
                        typeof message === 'string'
                            ? message
                            : 'Informe um link ou ID válido de playlist.',
                });
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

                setSpotifyPreviewIssue({
                    title: 'Não foi possível consultar o Spotify',
                    message:
                        body?.message ??
                        body?.data?.message ??
                        'Não foi possível consultar o Spotify agora.',
                    tone: 'danger',
                });
            },
        });
    }
    function applySpotifyPreview(): void {
        if (!spotifyPreview) {
            return;
        }

        setPlaylistDraft({
            name: spotifyPreview.name ?? '',
            spotify_playlist_id: spotifyPreview.spotify_playlist_id,
            spotify_url: spotifyPreview.spotify_url,
            spotify_snapshot_id: spotifyPreview.spotify_snapshot_id,
            spotify_owner_id: spotifyPreview.spotify_owner_id,
            spotify_owner_name: spotifyPreview.spotify_owner_name,
            spotify_tracks_total: spotifyPreview.spotify_tracks_total,
            spotify_last_synced_at: new Date().toISOString(),
            followers_count: spotifyPreview.followers_count,
        });
        setFormKey(
            `spotify-${spotifyPreview.spotify_playlist_id}-${Date.now()}`,
        );
        toast.success('Dados aplicados ao formulário.');
    }

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Nova playlist" />

            <Header
                title="Nova playlist"
                subtitle="Cadastre um canal musical comercializável"
                backHref={index.url()}
            />

            <Dialog
                open={spotifyPreviewIssue !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSpotifyPreviewIssue(null);
                    }
                }}
            >
                <DialogContent className="overflow-hidden p-0 sm:max-w-md">
                    <div className="border-b border-separator bg-muted px-6 py-5">
                        <DialogHeader className="gap-3 text-left">
                            <div
                                className={cn(
                                    'flex size-11 items-center justify-center rounded-full',
                                    issueToneClasses[
                                        spotifyPreviewIssue?.tone ?? 'warning'
                                    ],
                                )}
                            >
                                {spotifyPreviewIssue?.tone === 'info' ? (
                                    <CheckCircle2
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <AlertTriangle
                                        className="size-5"
                                        aria-hidden="true"
                                    />
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
                            <div className="rounded-lg border border-border bg-muted p-4">
                                <p className="mb-2 text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
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
                                    onClick={() => setSpotifyPreviewIssue(null)}
                                >
                                    Entendi
                                </Button>
                            )}
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="page-wrapper-lg flex-1 p-4 sm:p-6">
                <CreateFormSection
                    title="Buscar pelo Spotify"
                    description="Cole o link público ou o ID da playlist para preencher os dados principais."
                >
                    <Card className="overflow-hidden py-0">
                        <CardContent className="space-y-4 p-4 sm:p-6">
                            <form
                                className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end"
                                onSubmit={submitSpotifyPreview}
                            >
                                <FormField label="Link ou ID da playlist">
                                    <Input
                                        id="spotify-preview-input"
                                        name="spotify_input"
                                        value={spotifyInput}
                                        onChange={(event) => {
                                            setSpotifyInput(event.target.value);
                                            setSpotifyPreviewData(
                                                'spotify_input',
                                                event.target.value,
                                            );
                                        }}
                                        placeholder="https://open.spotify.com/playlist/... ou 37i9dQZF..."
                                        autoComplete="off"
                                        required
                                    />
                                </FormField>
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full sm:w-auto"
                                    disabled={previewProcessing}
                                >
                                    {previewProcessing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Search />
                                    )}
                                    Buscar dados
                                </Button>
                            </form>

                            {spotifyPreview ? (
                                <div className="flex flex-col gap-3 rounded-lg border border-border bg-muted p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CheckCircle2
                                                className="size-4 text-success"
                                                aria-hidden="true"
                                            />
                                            <p className="font-medium">
                                                {spotifyPreview.name ??
                                                    'Playlist Spotify'}
                                            </p>
                                            {spotifyPreview.is_partial ? (
                                                <Badge variant="outline">
                                                    Dados básicos
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {spotifyPreview.spotify_owner_name ??
                                                'Dono não informado'}
                                            {' · '}
                                            {spotifyPreview.followers_count?.toLocaleString(
                                                'pt-BR',
                                            ) ?? 'Não disponível'}{' '}
                                            seguidores
                                            {' · '}
                                            {spotifyPreview.spotify_tracks_total?.toLocaleString(
                                                'pt-BR',
                                            ) ?? 'Não disponível'}{' '}
                                            faixas
                                        </p>
                                        {spotifyPreview.is_partial ? (
                                            <p className="text-xs text-muted-foreground">
                                                O Spotify liberou apenas os
                                                dados básicos pelo embed
                                                oficial.
                                            </p>
                                        ) : null}
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {spotifyPreview.spotify_playlist_id}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={applySpotifyPreview}
                                    >
                                        Aplicar ao formulário
                                    </Button>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                </CreateFormSection>

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
                            />

                            <div className="sticky bottom-0 z-10 border-t bg-card/95 md:pl-[calc(240px+2rem)] lg:pl-[calc(300px+2rem)]">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="hidden text-xs text-muted-foreground sm:block">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>{' '}
                                        são obrigatórios
                                    </p>
                                    <div className="ml-auto flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            asChild
                                            disabled={processing}
                                        >
                                            <Link href={index.url()}>
                                                <X
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Cancelar
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
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
            </div>
        </div>
    );
}
