import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Save, Trash2, X } from 'lucide-react';
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
import type {
    PlaylistFormOptions,
    PlaylistResource,
} from '@/types/app/playlist';

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
        <div className="flex min-h-full flex-col">
            <Head title={`Editar ${playlist.name}`} />

            <Header title={playlist.name} backHref={show.url(playlist.uuid)}>
                <div className="flex flex-wrap items-center gap-2">
                    <PlaylistSubNav
                        playlistUuid={playlist.uuid}
                        currentPage="edit"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={archivePlaylist}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Arquivar
                            </Button>
                        </>
                    ) : null}
                </div>
            </Header>

            <Form
                action={update.url(playlist.uuid)}
                method="put"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
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

                        <div className="sticky bottom-0 z-10 border-t bg-card/95">
                            <div className="page-wrapper-md mb-0 flex items-center justify-between gap-3 py-3">
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    Campos marcados com{' '}
                                    <span className="text-destructive">*</span>{' '}
                                    são obrigatórios
                                </p>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={show.url(playlist.uuid)}>
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancelar
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save />
                                        {processing ? 'Salvando...' : 'Salvar'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
