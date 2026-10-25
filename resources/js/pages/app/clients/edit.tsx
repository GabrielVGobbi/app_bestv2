import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText, Mail, Phone, Save, UserRound, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import ClientFormFields from '@/pages/app/clients/partials/client-form-fields';
import type { ClientResource } from '@/types/app/client';

import { ClientStatusBadge } from './components/client-status-menu';
import { ClientSubNav } from './components/client-sub-nav';
import { SidebarContactRow, isBlank } from './partials/client-show-helpers';

interface Props {
    clientData: ClientResource;
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

export default function EditClient({ clientData }: Props) {
    const client = clientData.data;
    const clientName = String(client.name ?? client.uuid);
    const initials = getInitials(clientName);
    const summary =
        [client.company_name, client.email].find((value) => !isBlank(value)) ??
        '-';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            { title: clientName, href: show.url(client.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Editar cliente" />

            <Header title={clientName} backHref={show.url(client.uuid)}>
                <div className="flex flex-wrap items-center gap-2">
                    <ClientSubNav clientUuid={client.uuid} currentPage="edit" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url(client.uuid)}
                        resourceName={clientName}
                        resourceType="cliente"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <Form
                action={update.url(client.uuid)}
                method="put"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-lg flex-1 pb-8">
                            <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                                <aside className="lg:sticky lg:top-4 lg:self-start">
                                    <Card className="gap-0 py-0">
                                        <CardContent className="p-5">
                                            <div className="flex h-24 items-center justify-center rounded-lg bg-primary-soft text-3xl font-semibold text-primary-soft-foreground">
                                                {initials}
                                            </div>

                                            <div className="mt-4 space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h2 className="truncate text-base font-semibold">
                                                        {clientName}
                                                    </h2>
                                                    <ClientStatusBadge
                                                        status={client.status}
                                                    />
                                                </div>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {summary}
                                                </p>
                                            </div>

                                            <div className="mt-5 space-y-0.5">
                                                <SidebarContactRow
                                                    icon={UserRound}
                                                    label="Cliente"
                                                    value={clientName}
                                                />
                                                <SidebarContactRow
                                                    icon={FileText}
                                                    label="Documento"
                                                    value={formatCpfCnpj(
                                                        client.document,
                                                    )}
                                                />
                                                <SidebarContactRow
                                                    icon={Mail}
                                                    label="E-mail"
                                                    value={client.email}
                                                />
                                                <SidebarContactRow
                                                    icon={Phone}
                                                    label="Telefone"
                                                    value={formatPhone(
                                                        client.phone,
                                                    )}
                                                />
                                            </div>
                                        </CardContent>

                                        <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                                            <p>
                                                Criado em{' '}
                                                {formatDate(client.created_at)}
                                            </p>
                                            <p className="mt-1">
                                                Última edição em{' '}
                                                {formatDate(client.updated_at)}
                                            </p>
                                        </div>
                                    </Card>
                                </aside>

                                <main className="min-w-0">
                                    <Card>
                                        <CardContent>
                                            <ClientFormFields
                                                client={client}
                                                errors={errors}
                                            />
                                        </CardContent>
                                    </Card>
                                </main>
                            </div>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t border-border bg-card/95">
                            <div className="page-wrapper-lg mb-0 flex items-center justify-between gap-3 px-4 py-3">
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
                                        <Link href={show.url(client.uuid)}>
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
