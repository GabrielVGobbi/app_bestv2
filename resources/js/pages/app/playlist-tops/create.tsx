import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Playlists/Http/Controllers/TopController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import TopFormFields from '@/pages/app/playlist-tops/partials/top-form-fields';

export default function CreateTop() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'TOPs', href: index.url() },
            { title: 'Novo TOP', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo TOP" />

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
                        <h1 className="page-heading-title">Novo TOP</h1>
                        <p className="text-sm text-muted-foreground">
                            Cadastrar posição comercial reutilizável
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm">
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
                        <div className="space-y-4">
                            <Card variant="form">
                                <CardContent>
                                    <TopFormFields errors={errors} />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-background">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>{' '}
                                        são obrigatórios.
                                    </p>
                                    <div className="flex items-center gap-2">
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
                                            <Save className="size-4" />
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
        </>
    );
}
