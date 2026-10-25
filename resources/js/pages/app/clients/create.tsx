import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ClientFormFields from '@/pages/app/clients/partials/client-form-fields';
import type { ClientFormOptions } from '@/types/app/client';

interface Props {
    formOptions: ClientFormOptions;
}

export default function CreateClient({ formOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            { title: 'Novo cliente', href: create.url() },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Novo cliente" />

            <Header
                title="Novo cliente"
                subtitle="Cadastre um contato comercial"
                backHref={index.url()}
            />

            <Form
                action={store.url()}
                method="post"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
                            <section className="grid gap-x-10 gap-y-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                                <div className="md:pt-6">
                                    <h2 className="text-sm font-semibold">
                                        Dados do cliente
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Identificação, contato, dados comerciais
                                        e gênero musical do cliente.
                                    </p>
                                </div>
                                <Card>
                                    <CardContent>
                                        <ClientFormFields
                                            errors={errors}
                                            musicGenres={formOptions.genres}
                                            canCreateMusicGenre={
                                                formOptions.can_create_music_catalog
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            </section>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t border-border bg-card/95">
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
                                        <Link href={index.url()}>
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancelar
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save aria-hidden="true" />
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
