import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import { destroy, index, show, update } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ClientFormFields from '@/pages/app/clients/partials/client-form-fields';
import type { ClientResource } from '@/types/app/client';

import { ClientSubNav } from './components/client-sub-nav';

interface Props {
    clientData: ClientResource;
}

export default function EditClient({ clientData }: Props) {
    const client = clientData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clients', href: index.url() },
            { title: String(client.name ?? client.uuid), href: show.url(client.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title="Editar Client" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{String(client.name ?? client.uuid)}</h1>
                        <p className="text-sm text-muted-foreground">client.uuid</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <ClientSubNav clientUuid={client.uuid} currentPage="edit" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url(client.uuid)}
                        resourceName={String(client.name ?? client.uuid)}
                        resourceType="client"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(client.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatórios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4">
                                <div className="flex flex-1 flex-col gap-6 ">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Informações de Client</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ClientFormFields client={client} errors={errors} />
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                    <div className="flex items-center justify-between gap-3 py-3">
                                        <p className="text-xs text-muted-foreground">
                                            Campos marcados com <span className="text-destructive">*</span> são obrigatórios
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Button type="button" variant="ghost" size="sm" asChild disabled={processing}>
                                                <Link href={show.url(client.uuid)}>Cancelar</Link>
                                            </Button>
                                            <Button type="submit" size="sm" disabled={processing}>
                                                <Save />
                                                {processing ? 'Salvando...' : 'Salvar'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
