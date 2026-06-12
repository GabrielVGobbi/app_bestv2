import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, FileText, Mail, Phone, Save, UserRound } from 'lucide-react';
import { toast } from 'sonner';

import { destroy, index, show, update } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn, formatDate } from '@/lib/utils';
import ClientFormFields from '@/pages/app/clients/partials/client-form-fields';
import type { Client, ClientResource } from '@/types/app/client';

import { ClientSubNav } from './components/client-sub-nav';

interface Props {
    clientData: ClientResource;
}

const statusLabels: Record<Client['status'], string> = {
    active: 'Ativo',
    inactive: 'Inativo',
    archived: 'Arquivado',
};

function cleanValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '-';
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function SummaryItem({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof UserRound;
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground">
            <Icon className="size-4 shrink-0" />
            <span className="sr-only">{label}</span>
            <span className="truncate">{cleanValue(value)}</span>
        </div>
    );
}

export default function EditClient({ clientData }: Props) {
    const client = clientData.data;
    const clientName = String(client.name ?? client.uuid);
    const initials = getInitials(clientName);

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

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={show.url(client.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{clientName}</h1>
                        <p className="text-sm text-muted-foreground">{client.uuid}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
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

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-secondary text-3xl font-semibold text-primary">
                                    {initials}
                                </div>

                                <div className="mt-4 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <h2 className="truncate text-base font-semibold">{clientName}</h2>
                                        <Badge
                                            variant={client.status === 'active' ? 'default' : 'secondary'}
                                            className="h-5 rounded-sm px-1.5 text-[10px]"
                                        >
                                            {statusLabels[client.status]}
                                        </Badge>
                                    </div>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {cleanValue(client.company_name ?? client.email)}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-1">
                                    <SummaryItem icon={UserRound} label="Cliente" value={clientName} />
                                    <SummaryItem icon={FileText} label="Documento" value={client.document} />
                                    <SummaryItem icon={Mail} label="Email" value={client.email} />
                                    <SummaryItem icon={Phone} label="Telefone" value={client.phone} />
                                </div>
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <p>Criado - {formatDate(client.created_at)}</p>
                                <p className="mt-1">Última edição - {formatDate(client.updated_at)}</p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Form
                            action={update.url(client.uuid)}
                            method="put"
                            options={{ preserveScroll: true }}
                            onError={() => toast.error('Verifique os campos obrigatórios.')}
                            className="flex h-full flex-col"
                        >
                            {({ errors, processing }) => (
                                <div className="flex flex-1 flex-col gap-4">
                                    <Card>
                                        <CardContent>
                                            <ClientFormFields client={client} errors={errors} />
                                        </CardContent>
                                    </Card>

                                    <div
                                        className={cn(
                                            'sticky bottom-0 -mx-4 mt-auto border-t bg-background/95 px-4 backdrop-blur',
                                            'supports-backdrop-filter:bg-background/80',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-3 py-3">
                                            <p className="text-xs text-muted-foreground">
                                                Campos marcados com <span className="text-destructive">*</span> são obrigatórios.
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
                            )}
                        </Form>
                    </main>
                </div>
            </div>
        </>
    );
}
