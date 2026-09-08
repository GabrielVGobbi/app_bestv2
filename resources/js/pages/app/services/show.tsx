import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CircleDollarSign,
    FileText,
    PackageCheck,
} from 'lucide-react';

import {
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatMoney } from '@/lib/utils';
import type { ServiceResource } from '@/types/app/service';

import { ServiceSubNav } from './components/service-sub-nav';

interface Props {
    serviceData: ServiceResource;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
}

function cleanValue(value: unknown): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '-';
}

function InfoField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 break-words text-sm">{value}</p>
        </div>
    );
}

function metadataSummary(metadata: ServiceResource['data']['metadata']): string {
    if (!metadata) {
        return '{}';
    }

    return JSON.stringify(metadata, null, 2);
}

export default function ShowService({ serviceData, permissions }: Props) {
    const service = serviceData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Servicos', href: index.url() },
            { title: service.name, href: show.url(service.uuid) },
        ],
    });

    return (
        <>
            <Head title={service.name} />

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
                        <h1 className="text-xl font-semibold">{service.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {service.slug}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <ServiceSubNav
                        serviceUuid={service.uuid}
                        currentPage="show"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator orientation="vertical" className="mx-1 h-6" />
                            <DeleteButton
                                url={destroy.url(service.uuid)}
                                resourceName={service.name}
                                resourceType="Servico"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-secondary text-primary">
                                    <PackageCheck className="size-12" />
                                </div>

                                <div className="mt-4 space-y-2">

                                    <h2 className="truncate text-base font-semibold">
                                        {service.name}
                                    </h2>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {service.slug}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-2 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <CircleDollarSign className="size-4 shrink-0" />
                                        <span>
                                            {formatMoney(service.base_price, {
                                                fromCents: true,
                                            })}
                                        </span>
                                    </div>

                                </div>
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <CalendarDays className="size-3.5" />
                                    <span>Criado {formatDate(service.created_at)}</span>
                                </div>
                                <p className="mt-1">
                                    Ultima edicao {formatDate(service.updated_at)}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Tabs defaultValue="summary" className="gap-4">
                            <TabsList variant="line">
                                <TabsTrigger value="summary">Resumo</TabsTrigger>
                                <TabsTrigger value="config">Configurações</TabsTrigger>
                                <TabsTrigger value="metadata">Metadata</TabsTrigger>
                            </TabsList>

                            <TabsContent value="summary" className="space-y-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Dados comerciais</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <InfoField
                                            label="Nome"
                                            value={service.name}
                                        />
                                        <InfoField
                                            label="Slug"
                                            value={service.slug}
                                        />
                                        <InfoField
                                            label="Preço Base"
                                            value={formatMoney(service.base_price, {
                                                fromCents: true,
                                            })}
                                        />
                                        <InfoField
                                            label="Moeda"
                                            value={service.currency}
                                        />

                                    </CardContent>
                                </Card>

                                {service.description && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Descrição</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                                {service.description || 'Sem descrição.'}
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}

                            </TabsContent>

                            <TabsContent value="config">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Configurações do item de venda
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <InfoField
                                            label="Exige periodo"
                                            value={
                                                service.metadata?.requires_period
                                                    ? 'Sim'
                                                    : 'Nao'
                                            }
                                        />
                                        <InfoField
                                            label="Exige referencia de ativo"
                                            value={
                                                service.metadata
                                                    ?.requires_asset_reference
                                                    ? 'Sim'
                                                    : 'Nao'
                                            }
                                        />
                                        <InfoField
                                            label="Modo de entrega"
                                            value={cleanValue(
                                                service.metadata?.delivery_mode,
                                            )}
                                        />
                                        <InfoField
                                            label="Observação padrao"
                                            value={cleanValue(
                                                service.metadata?.default_notes,
                                            )}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="metadata">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Metadata</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-[360px] overflow-auto rounded-md border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground">
                                            <code>
                                                {metadataSummary(service.metadata)}
                                            </code>
                                        </pre>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </main>
                </div>
            </div >
        </>
    );
}
