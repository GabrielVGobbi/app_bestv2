import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Braces,
    CalendarDays,
    CircleDollarSign,
    Info,
    PackageCheck,
    Settings2,
} from 'lucide-react';
import { useState } from 'react';

import {
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Separator } from '@/components/ui/separator';
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
            <p className="mt-0.5 text-sm break-words">{value}</p>
        </div>
    );
}

function metadataSummary(
    metadata: ServiceResource['data']['metadata'],
): string {
    if (!metadata) {
        return '{}';
    }

    return JSON.stringify(metadata, null, 2);
}

export default function ShowService({ serviceData, permissions }: Props) {
    const service = serviceData.data;
    const [tab, setTab] = useState('summary');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Serviços', href: index.url() },
            { title: service.name, href: show.url(service.uuid) },
        ],
    });

    return (
        <>
            <Head title={service.name} />

            <Header title={service.name} backHref={index.url()}>
                <div className="flex flex-wrap items-center gap-2">
                    <ServiceSubNav
                        serviceUuid={service.uuid}
                        currentPage="show"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <DeleteButton
                                url={destroy.url(service.uuid)}
                                resourceName={service.name}
                                resourceType="serviço"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <Card className="gap-0 py-0">
                            <CardContent className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
                                    <PackageCheck
                                        className="size-12"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    <h2 className="min-w-0 truncate text-base font-semibold">
                                        {service.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            service.is_active
                                                ? 'success'
                                                : 'secondary'
                                        }
                                    >
                                        {service.is_active
                                            ? 'Ativo'
                                            : 'Inativo'}
                                    </Badge>
                                </div>

                                <div className="mt-5 flex items-center gap-2 text-sm text-muted-foreground">
                                    <CircleDollarSign
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="tabular-nums">
                                        {formatMoney(service.base_price, {
                                            fromCents: true,
                                        })}
                                    </span>
                                </div>
                            </CardContent>

                            <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                                <div className="flex items-center gap-2">
                                    <CalendarDays
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        Criado {formatDate(service.created_at)}
                                    </span>
                                </div>
                                <p className="mt-1">
                                    Última edição{' '}
                                    {formatDate(service.updated_at)}
                                </p>
                            </div>
                        </Card>
                    </aside>

                    <main className="min-w-0">
                        <Tabs
                            value={tab}
                            onValueChange={setTab}
                            className="space-y-4"
                        >
                            <TabsList
                                aria-label="Seções do serviço"
                                className="max-w-full overflow-x-auto"
                            >
                                <TabItem
                                    value="summary"
                                    label="Resumo"
                                    icon={Info}
                                />
                                <TabItem
                                    value="config"
                                    label="Configurações"
                                    icon={Settings2}
                                />
                                <TabItem
                                    value="metadata"
                                    label="Metadata"
                                    icon={Braces}
                                />
                            </TabsList>

                            <TabPanel value="summary" className="space-y-4">
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
                                            label="Preço base"
                                            value={formatMoney(
                                                service.base_price,
                                                { fromCents: true },
                                            )}
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
                                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                                {service.description}
                                            </p>
                                        </CardContent>
                                    </Card>
                                )}
                            </TabPanel>

                            <TabPanel value="config">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Configurações do item de venda
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <InfoField
                                            label="Exige período"
                                            value={
                                                service.metadata
                                                    ?.requires_period
                                                    ? 'Sim'
                                                    : 'Não'
                                            }
                                        />
                                        <InfoField
                                            label="Exige referência de ativo"
                                            value={
                                                service.metadata
                                                    ?.requires_asset_reference
                                                    ? 'Sim'
                                                    : 'Não'
                                            }
                                        />
                                        <InfoField
                                            label="Modo de entrega"
                                            value={cleanValue(
                                                service.metadata?.delivery_mode,
                                            )}
                                        />
                                        <InfoField
                                            label="Observação padrão"
                                            value={cleanValue(
                                                service.metadata?.default_notes,
                                            )}
                                        />
                                    </CardContent>
                                </Card>
                            </TabPanel>

                            <TabPanel value="metadata">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Metadata</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <pre className="max-h-[360px] overflow-auto rounded-lg border border-border bg-muted p-4 text-xs leading-relaxed text-muted-foreground">
                                            <code>
                                                {metadataSummary(
                                                    service.metadata,
                                                )}
                                            </code>
                                        </pre>
                                    </CardContent>
                                </Card>
                            </TabPanel>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
