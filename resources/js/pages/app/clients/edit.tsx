import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    index,
    show,
    update,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ClientFormFields from '@/pages/app/clients/partials/client-form-fields';
import type { ClientResource } from '@/types/app/client';

import { ClientProfileHeader } from './components/client-profile-header';
import { ClientProfileSidebar } from './components/client-profile-sidebar';

interface Props {
    clientData: ClientResource;
}

export default function EditClient({ clientData }: Props) {
    const client = clientData.data;
    const clientName = String(client.name ?? client.uuid);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            { title: clientName, href: show.url(client.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title="Editar cliente" />

            <ClientProfileHeader
                client={client}
                backHref={show.url(client.uuid)}
            />

            <div className="page-wrapper-lg">
                <div className="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <ClientProfileSidebar client={client} />

                    <main className="min-w-0">
                        <Form
                            action={update.url(client.uuid)}
                            method="put"
                            options={{ preserveScroll: true }}
                            onError={() =>
                                toast.error('Verifique os campos obrigatórios.')
                            }
                            className="flex h-full flex-col"
                        >
                            {({ errors, processing }) => (
                                <div className="flex flex-1 flex-col gap-4">
                                    <Card>
                                        <CardContent>
                                            <ClientFormFields
                                                client={client}
                                                errors={errors}
                                            />
                                        </CardContent>
                                    </Card>

                                    <div className="sticky bottom-0 z-10 -mx-4 mt-auto border-t border-border bg-card/95 px-4">
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
                                                    <Link
                                                        href={show.url(
                                                            client.uuid,
                                                        )}
                                                    >
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
                    </main>
                </div>
            </div>
        </>
    );
}
