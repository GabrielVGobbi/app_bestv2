import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import PlaylistFormFields from '@/pages/app/playlists/partials/playlist-form-fields';
import type { PlaylistFormOptions, PlaylistResource } from '@/types/app/playlist';

import { PlaylistSubNav } from './components/playlist-sub-nav';

interface Props {
    playlistData: PlaylistResource;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    formOptions: PlaylistFormOptions;
}

export default function EditPlaylist({
    playlistData,
    permissions,
    formOptions,
}: Props) {
    const playlist = playlistData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Playlists', href: index.url() },
            { title: playlist.name, href: show.url(playlist.uuid) },
            { title: 'Editar' },
        ],
    });

    function archivePlaylist(): void {
        router.delete(destroy.url(playlist.uuid), {
            preserveScroll: true,
            onError: () => toast.error('Não foi possível arquivar a playlist.'),
        });
    }

    return (
        <>
            <Head title={`Editar ${playlist.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(playlist.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{playlist.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {playlist.slug}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <PlaylistSubNav
                        playlistUuid={playlist.uuid}
                        currentPage="edit"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator orientation="vertical" className="mx-1 h-6" />
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={archivePlaylist}
                            >
                                <Trash2 className="size-4" />
                                Arquivar
                            </Button>
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(playlist.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatórios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <div className="flex flex-1 flex-col gap-6">
                                <Card>
                                    <CardContent>
                                        <PlaylistFormFields
                                            playlist={playlist}
                                            options={formOptions}
                                            errors={errors}
                                        />
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">*</span> são
                                        obrigatórios.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                            disabled={processing}
                                        >
                                            <Link href={show.url(playlist.uuid)}>
                                                Cancelar
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <Save />
                                            {processing ? 'Salvando...' : 'Salvar'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
