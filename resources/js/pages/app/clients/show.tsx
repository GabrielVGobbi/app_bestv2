import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ClipboardList,
    FileText,
    History,
    Mail,
    MapPin,
    MessageSquareText,
    Phone,
    SquarePen,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { AddressManager } from '@/components/addresses/address-manager';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Separator } from '@/components/ui/separator';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type { ClientResource } from '@/types/app/client';

import { ClientQuickUpdatePanel } from './components/client-quick-update-panel';
import type { ClientQuickUpdateColumn } from './components/client-quick-update-panel';
import {
    ClientStatusBadge,
    ClientStatusMenu,
} from './components/client-status-menu';
import { ClientSubNav } from './components/client-sub-nav';
import {
    DefaultAddressSummary,
    FieldGrid,
    Section,
    SidebarContactRow,
    SidebarMetaRow,
    TechnicalIdsDisclosure,
    isBlank,
} from './partials/client-show-helpers';
import type { DetailItem } from './partials/client-show-helpers';

interface Props {
    clientData: ClientResource;
    timelineSubject: {
        type: string;
        id: string | number;
    };
}

const quickUpdateColumns: ClientQuickUpdateColumn[] = [
    {
        field: 'name',
        label: 'Nome',
    },
    {
        field: 'responsible_user_id',
        label: 'Responsável',
    },
];

const clientTabs: Array<{
    value: string;
    label: string;
    icon: LucideIcon;
    count?: boolean;
}> = [
    { value: 'overview', label: 'Visão geral', icon: ClipboardList },
    { value: 'addresses', label: 'Endereços', icon: MapPin, count: true },
    { value: 'notes', label: 'Observações', icon: MessageSquareText },
    { value: 'activity', label: 'Atividade', icon: History },
];

export default function ShowClient({ clientData, timelineSubject }: Props) {
    const client = clientData.data;
    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);
    const canUpdate = client.permissions.can_update;
    const editUrl = edit.url(client.uuid);
    const clientName = String(client.name ?? client.uuid);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            { title: clientName, href: show.url(client.uuid) },
        ],
    });

    const initials = clientName
        .split(' ')
        .map((namePart) => namePart[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    const profileItems: DetailItem[] = [
        { label: 'Empresa', value: client.company_name },
        { label: 'CPF/CNPJ', value: formatCpfCnpj(client.document) },
        { label: 'Classificação', value: client.classification },
        { label: 'Gênero musical', value: client.musical_genre },
        { label: 'Origem', value: client.source_id },
        { label: 'Agente/produtor', value: client.is_agent ? 'Sim' : 'Não' },
    ];

    const technicalItems: DetailItem[] = [
        { label: 'UUID', value: client.uuid },
        { label: 'ID interno', value: String(client.id) },
        {
            label: 'Empresa ID',
            value: client.company_id ? String(client.company_id) : null,
        },
        {
            label: 'Responsável ID',
            value: client.responsible_user_id
                ? String(client.responsible_user_id)
                : null,
        },
    ];

    return (
        <>
            <Head title={clientName} />

            <Header title={clientName} backHref={index.url()}>
                <div className="flex flex-wrap items-center gap-2">
                    <ClientSubNav
                        clientUuid={client.uuid}
                        currentPage="show"
                        canEdit={canUpdate}
                    />
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
                <div className="grid gap-4 p-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <Card className="gap-0 py-0">
                            <CardContent className="p-5">
                                <div className="flex h-20 items-center justify-center rounded-lg bg-primary-soft text-2xl font-semibold text-primary-soft-foreground">
                                    {initials}
                                </div>

                                <div className="mt-4">
                                    {canUpdate ? (
                                        <ClientStatusMenu
                                            uuid={client.uuid}
                                            status={client.status}
                                            size="sm"
                                            className="w-full justify-between"
                                        />
                                    ) : (
                                        <ClientStatusBadge
                                            status={client.status}
                                            className="w-full justify-center"
                                        />
                                    )}
                                </div>

                                <div className="mt-5 space-y-0.5">
                                    <SidebarContactRow
                                        icon={FileText}
                                        label="Documento"
                                        value={formatCpfCnpj(client.document)}
                                        copyable
                                        emptyHref={
                                            canUpdate ? editUrl : undefined
                                        }
                                        emptyLabel={
                                            canUpdate
                                                ? 'Adicionar documento'
                                                : undefined
                                        }
                                    />
                                    <SidebarContactRow
                                        icon={Mail}
                                        label="E-mail"
                                        value={client.email}
                                        copyable
                                        emptyHref={
                                            canUpdate ? editUrl : undefined
                                        }
                                        emptyLabel={
                                            canUpdate
                                                ? 'Adicionar e-mail'
                                                : undefined
                                        }
                                    />
                                    <SidebarContactRow
                                        icon={Phone}
                                        label="Telefone"
                                        value={formatPhone(client.phone)}
                                        copyable
                                        emptyHref={
                                            canUpdate ? editUrl : undefined
                                        }
                                        emptyLabel={
                                            canUpdate
                                                ? 'Adicionar telefone'
                                                : undefined
                                        }
                                    />
                                </div>

                                <Separator className="my-4" />

                                <div className="space-y-0.5">
                                    <SidebarMetaRow
                                        icon={UserRound}
                                        label="Responsável"
                                        value={
                                            responsibleUser?.name ??
                                            responsibleUser?.email
                                        }
                                    />
                                    <DefaultAddressSummary
                                        address={defaultAddress}
                                    />
                                </div>
                            </CardContent>

                            <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                                <p>Criado em {formatDate(client.created_at)}</p>
                                <p className="mt-1">
                                    Atualizado em{' '}
                                    {formatDate(client.updated_at)}
                                </p>
                            </div>
                        </Card>
                    </aside>

                    <main className="min-w-0">
                        <Tabs defaultValue="overview" className="space-y-4">
                            <TabsList
                                aria-label="Seções do cliente"
                                className="max-w-full overflow-x-auto"
                            >
                                {clientTabs.map((tab) => (
                                    <TabItem
                                        key={tab.value}
                                        value={tab.value}
                                        icon={tab.icon}
                                        label={
                                            tab.count
                                                ? `${tab.label} (${addresses.length})`
                                                : tab.label
                                        }
                                    />
                                ))}
                            </TabsList>

                            <TabPanel value="overview">
                                <Card>
                                    <CardContent className="space-y-6">
                                        {canUpdate ? (
                                            <Section title="Dados rápidos">
                                                <div className="rounded-lg border border-border px-3.5 py-3.5">
                                                    <ClientQuickUpdatePanel
                                                        key={`${client.uuid}:${client.updated_at}`}
                                                        client={client}
                                                        columns={
                                                            quickUpdateColumns
                                                        }
                                                    />
                                                </div>
                                            </Section>
                                        ) : null}

                                        <Section title="Classificação">
                                            <FieldGrid
                                                items={profileItems}
                                                variant="rows"
                                            />
                                        </Section>
                                    </CardContent>
                                </Card>
                            </TabPanel>

                            <TabPanel value="addresses">
                                <Card>
                                    <CardContent>
                                        <AddressManager
                                            model="client"
                                            modelId={client.uuid}
                                            initialAddresses={addresses}
                                            variant="plain"
                                            title="Lista de endereços"
                                            emptyMessage="Cadastre o endereço de cobrança ou entrega deste cliente."
                                        />
                                    </CardContent>
                                </Card>
                            </TabPanel>

                            <TabPanel value="notes">
                                <Card>
                                    <CardContent className="space-y-6">
                                        <Section
                                            title="Observações"
                                            action={
                                                canUpdate ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={editUrl}>
                                                            <SquarePen
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            Editar
                                                        </Link>
                                                    </Button>
                                                ) : null
                                            }
                                        >
                                            {isBlank(client.notes) ? (
                                                <div className="rounded-lg border border-dashed border-border">
                                                    <EmptyState
                                                        icon={MessageSquareText}
                                                        title="Nenhuma observação cadastrada"
                                                        description={
                                                            canUpdate
                                                                ? 'Registre combinados e preferências deste cliente.'
                                                                : undefined
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                <div className="rounded-lg border border-border px-4 py-3 text-sm leading-6 whitespace-pre-wrap text-foreground">
                                                    {client.notes}
                                                </div>
                                            )}
                                        </Section>

                                        <TechnicalIdsDisclosure
                                            items={technicalItems}
                                        />
                                    </CardContent>
                                </Card>
                            </TabPanel>

                            <TabPanel value="activity">
                                <Card>
                                    <CardContent>
                                        <ActivityTimeline
                                            showHeader={false}
                                            modelType={timelineSubject.type}
                                            modelId={timelineSubject.id}
                                            title=""
                                            allowAttachments
                                            className="m-0 border-0 bg-transparent p-0 shadow-none"
                                        />
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
