import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CalendarDays } from 'lucide-react';

import { destroy, index, show } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDate } from '@/lib/utils';
import type { ClientResource } from '@/types/app/client';

import { ClientSubNav } from './components/client-sub-nav';

interface Props {
    clientData: ClientResource;
}

function InfoField({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm">{value || '-'}</p>
        </div>
    );
}

export default function ShowClient({ clientData }: Props) {
    const client = clientData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clients', href: index.url() },
            { title: String(client.name ?? client.uuid), href: show.url(client.uuid) },
        ],
    });

    const initials = String(client.name ?? client.uuid)
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <>
            <Head title={String(client.name ?? client.uuid)} />

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
                    <ClientSubNav clientUuid={client.uuid} currentPage="show" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url(client.uuid)}
                        resourceName={String(client.name ?? client.uuid)}
                        resourceType="client"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="grid gap-6 md:grid-cols-3">
                        <aside className="space-y-4 md:col-span-1">
                            <Card>
                                <CardContent className="flex flex-col items-center gap-4 p-6">
                                    <div className="flex size-20 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary">
                                        {initials}
                                    </div>
                                    <div className="text-center">
                                        <h2 className="text-lg font-semibold">{String(client.name ?? client.uuid)}</h2>
                                        <p className="text-sm text-muted-foreground">client.uuid</p>
                                    </div>

                                    <Separator className="w-full" />

                                    <div className="w-full space-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-2">
                                            <CalendarDays className="size-3.5" />
                                            <span>Criado {formatDate(client.created_at)}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>

                        <main className="space-y-6 md:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informações de Client</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <InfoField label="Name" value={String(client.name ?? '-')} />
                                    <InfoField label="Email" value={String(client.email ?? '-')} />
                                    <InfoField label="Criado em" value={formatDate(client.created_at)} />
                                    <InfoField label="Atualizado em" value={formatDate(client.updated_at)} />
                                </CardContent>
                            </Card>
                        </main>
                    </div>
                </div>
            </div>
        </>
    );
}
