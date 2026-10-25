import { Head, setLayoutProps } from '@inertiajs/react';
import {
    ClipboardList,
    History,
    MapPin,
    MessageSquareText,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

import {
    index,
    show,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { AddressManager } from '@/components/addresses/address-manager';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { formatCpfCnpj } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type { ClientResource } from '@/types/app/client';

import { ClientNotesDialog } from './components/client-notes-dialog';
import { ClientProfileHeader } from './components/client-profile-header';
import { ClientProfileSidebar } from './components/client-profile-sidebar';
import { ClientQuickUpdatePanel } from './components/client-quick-update-panel';
import type { ClientQuickUpdateColumn } from './components/client-quick-update-panel';
import {
    FieldGrid,
    Section,
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
    const addresses = client.addresses ?? [];
    const canUpdate = client.permissions.can_update;
    const [isNotesDialogOpen, setIsNotesDialogOpen] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            {
                title: String(client.name ?? client.uuid),
                href: show.url(client.uuid),
            },
        ],
    });

    const clientName = String(client.name ?? client.uuid);

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

            <ClientProfileHeader
                client={client}
                backHref={index.url()}
                showEditAction
            />

            <div className="page-wrapper-lg">
                <div className="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <ClientProfileSidebar
                        client={client}
                        showEmptyContactLinks
                    />

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
                                                canUpdate &&
                                                !isBlank(client.notes) ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setIsNotesDialogOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        Editar
                                                    </Button>
                                                ) : null
                                            }
                                        >
                                            {isBlank(client.notes) ? (
                                                <EmptyState
                                                    icon={MessageSquareText}
                                                    title="Nenhuma observação cadastrada"
                                                    action={
                                                        canUpdate ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setIsNotesDialogOpen(
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                Adicionar
                                                                observação
                                                            </Button>
                                                        ) : null
                                                    }
                                                />
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
                                <ActivityTimeline
                                    showHeader={false}
                                    modelType={timelineSubject.type}
                                    modelId={timelineSubject.id}
                                    title=""
                                    allowAttachments
                                />
                            </TabPanel>
                        </Tabs>
                    </main>
                </div>
            </div>

            {/* Montado só enquanto aberto: cada abertura começa com a observação atual. */}
            {canUpdate && isNotesDialogOpen ? (
                <ClientNotesDialog
                    client={client}
                    open={isNotesDialogOpen}
                    onOpenChange={setIsNotesDialogOpen}
                />
            ) : null}
        </>
    );
}
