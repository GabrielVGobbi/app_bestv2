import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    index,
    show,
    update,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
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
                backAriaLabel="Voltar para a ficha do cliente"
            />

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <ClientProfileSidebar client={client} />

                    <main className="min-w-0">
                        <Form
                            action={update.url(client.uuid)}
                            method="put"
                            options={{ preserveScroll: true }}
                            onError={() =>
                                toast.error(
                                    'Verifique os campos obrigatórios.',
                                )
                            }
                            className="flex h-full flex-col"
                        >
                            {({ errors, processing }) => (
                                <div className="flex flex-1 flex-col gap-4">
                                    <div className="overflow-hidden rounded-xl border border-border/80 bg-card shadow-[var(--shadow-app-panel)]">
                                        <div className="p-5 sm:p-6">
                                            <ClientFormFields
                                                client={client}
                                                errors={errors}
                                            />
                                        </div>
                                    </div>

                                    <div
                                        className={cn(
                                            'sticky bottom-0 -mx-4 mt-auto border-t bg-background/95 px-4 backdrop-blur',
                                            'supports-backdrop-filter:bg-background/80',
                                        )}
                                    >
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
                                                    <Link
                                                        href={show.url(
                                                            client.uuid,
                                                        )}
                                                    >
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
                    </main>
                </div>
            </div>
        </>
    );
}
