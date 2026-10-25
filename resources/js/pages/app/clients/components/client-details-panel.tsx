import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    Edit,
    ExternalLink,
    FileText,
    Mail,
    MousePointerClick,
    Phone,
    RefreshCw,
    Share2,
    Trash2,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { AddressManager } from '@/components/addresses/address-manager';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { getErrorMessage, isApiError } from '@/lib/http';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import type { Client } from '@/types/app/client';

import {
    DefaultAddressSummary,
    FieldGrid,
    SidebarContactRow,
    isBlank,
} from '../partials/client-show-helpers';
import type { DetailItem } from '../partials/client-show-helpers';
import { ClientQuickUpdatePanel } from './client-quick-update-panel';
import type { ClientQuickUpdateColumn } from './client-quick-update-panel';
import { ClientStatusMenu } from './client-status-menu';

interface ClientDetailsPanelProps {
    selectedUuid: string | null;
    client: Client | null;
    isLoading: boolean;
    isFetching: boolean;
    error: Error | unknown;
    onDelete: (client: Client) => void;
    onRetry: () => void;
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

function detailsErrorMessage(error: Error | unknown): string {
    if (isApiError(error)) {
        return getErrorMessage(error);
    }

    return error instanceof Error
        ? error.message
        : 'Não foi possível carregar os detalhes do cliente.';
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function ClientDetailsSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside className="space-y-4">
                <div className="rounded-lg border border-border bg-card p-5">
                    <Skeleton className="h-28 w-full" />
                    <div className="mt-4 grid gap-2">
                        <Skeleton className="h-4 w-36" />
                        <Skeleton className="h-3 w-44" />
                    </div>
                </div>
            </aside>
            <main className="space-y-4">
                <div className="rounded-lg border border-border bg-card p-5">
                    <Skeleton className="h-4 w-32" />
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton key={index} className="h-10 w-full" />
                        ))}
                    </div>
                </div>
                <div className="rounded-lg border border-border bg-card p-5">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="mt-4 h-20 w-full" />
                </div>
            </main>
        </div>
    );
}

export function ClientDetailsPanel({
    selectedUuid,
    client,
    isLoading,
    isFetching,
    error,
    onDelete,
    onRetry,
}: ClientDetailsPanelProps) {
    if (selectedUuid === null) {
        return (
            <section className="grid h-full min-h-72 place-items-center overflow-hidden rounded-lg border border-dashed border-border bg-muted">
                <EmptyState
                    icon={MousePointerClick}
                    title="Selecione um cliente"
                    description="Clique em um cliente na lista para carregar os dados detalhados aqui."
                />
            </section>
        );
    }

    if (isLoading || (isFetching && client === null)) {
        return (
            <section className="h-full min-h-0 overflow-y-auto rounded-lg border border-border bg-background p-4">
                <ClientDetailsSkeleton />
            </section>
        );
    }

    if (error || client === null) {
        return (
            <section className="grid h-full min-h-72 place-items-center overflow-hidden rounded-lg border border-border bg-card">
                <EmptyState
                    icon={AlertCircle}
                    title="Detalhes indisponíveis"
                    description={detailsErrorMessage(error)}
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onRetry}
                        >
                            <RefreshCw className="size-4" aria-hidden="true" />
                            Tentar novamente
                        </Button>
                    }
                />
            </section>
        );
    }

    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);
    const clientName = String(client.name ?? client.uuid);
    const summary =
        [client.company_name, client.email].find((value) => !isBlank(value)) ??
        '-';
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

    return (
        <section className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-lg border border-border bg-background">
            <div className="flex shrink-0 flex-col gap-4 border-b border-border p-4 xl:flex-row xl:items-center xl:justify-between">
                <div className="min-w-0">
                    <h2 className="truncate text-xl font-semibold text-foreground">
                        {clientName}
                    </h2>
                    <p className="truncate text-sm text-muted-foreground">
                        {summary}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show.url(client.uuid)}>
                            <ExternalLink
                                className="size-4"
                                aria-hidden="true"
                            />
                            Abrir
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={edit.url(client.uuid)}>
                            <Edit className="size-4" aria-hidden="true" />
                            Editar
                        </Link>
                    </Button>
                    <Separator
                        orientation="vertical"
                        className="hidden h-6 xl:block"
                    />
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() => onDelete(client)}
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                        Excluir
                    </Button>
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <Card className="gap-0 self-start py-0">
                        <CardContent className="p-5">
                            <div className="flex h-28 items-center justify-center rounded-lg bg-primary-soft text-3xl font-semibold text-primary-soft-foreground">
                                {initials}
                            </div>

                            <div className="mt-4 space-y-1">
                                <h3 className="truncate text-base font-semibold text-foreground">
                                    {clientName}
                                </h3>
                                <p className="truncate text-sm text-muted-foreground">
                                    {summary}
                                </p>
                            </div>

                            <div className="mt-4">
                                <ClientStatusMenu
                                    uuid={client.uuid}
                                    status={client.status}
                                    size="sm"
                                    className="w-full justify-between"
                                />
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
                                    value={formatCpfCnpj(client.document)}
                                    copyable
                                />
                                <SidebarContactRow
                                    icon={Mail}
                                    label="E-mail"
                                    value={client.email}
                                    copyable
                                />
                                <SidebarContactRow
                                    icon={Phone}
                                    label="Telefone"
                                    value={formatPhone(client.phone)}
                                    copyable
                                />
                                <SidebarContactRow
                                    icon={Share2}
                                    label="Compartilhar"
                                    value="Compartilhar"
                                />
                            </div>
                        </CardContent>

                        <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                            <p>Criado em {formatDate(client.created_at)}</p>
                            <p className="mt-1">
                                Última edição em {formatDate(client.updated_at)}
                            </p>
                        </div>
                    </Card>

                    <main className="min-w-0">
                        <Tabs defaultValue="overview" className="space-y-4">
                            <TabsList
                                aria-label="Seções do cliente"
                                className="max-w-full overflow-x-auto"
                            >
                                <TabItem value="overview" label="Detalhes" />
                                <TabItem
                                    value="addresses"
                                    label={`Endereços (${addresses.length})`}
                                />
                                <TabItem value="notes" label="Observações" />
                            </TabsList>

                            <TabPanel value="overview" className="space-y-4">
                                <Panel title="Dados do cliente">
                                    <div className="space-y-5">
                                        <ClientQuickUpdatePanel
                                            key={`${client.uuid}:${client.updated_at}`}
                                            client={client}
                                            columns={quickUpdateColumns}
                                            reloadProps={null}
                                            onSaved={onRetry}
                                        />
                                        <Separator />
                                        <FieldGrid
                                            items={profileItems}
                                            columns="sm:grid-cols-2 xl:grid-cols-3"
                                        />
                                    </div>
                                </Panel>

                                <Panel title="Histórico do cadastro">
                                    <FieldGrid
                                        columns="sm:grid-cols-3"
                                        items={[
                                            {
                                                label: 'Criado em',
                                                value: formatDate(
                                                    client.created_at,
                                                ),
                                            },
                                            {
                                                label: 'Atualizado em',
                                                value: formatDate(
                                                    client.updated_at,
                                                ),
                                            },
                                            {
                                                label: 'Endereços',
                                                value: String(addresses.length),
                                            },
                                        ]}
                                    />
                                </Panel>
                            </TabPanel>

                            <TabPanel value="addresses">
                                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                                    <AddressManager
                                        model="client"
                                        modelId={client.uuid}
                                        initialAddresses={addresses}
                                    />

                                    <div className="space-y-4">
                                        <Panel title="Endereço principal">
                                            <DefaultAddressSummary
                                                address={defaultAddress}
                                            />
                                        </Panel>

                                        <Panel title="Contato">
                                            <FieldGrid
                                                columns="grid-cols-1"
                                                items={[
                                                    {
                                                        label: 'E-mail',
                                                        value: client.email,
                                                    },
                                                    {
                                                        label: 'Telefone',
                                                        value: formatPhone(
                                                            client.phone,
                                                        ),
                                                    },
                                                    {
                                                        label: 'Responsável',
                                                        value:
                                                            responsibleUser?.email ??
                                                            responsibleUser?.name,
                                                    },
                                                ]}
                                            />
                                        </Panel>
                                    </div>
                                </div>
                            </TabPanel>

                            <TabPanel value="notes">
                                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                                    <Panel title="Observações">
                                        <div className="min-h-40 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                            {client.notes ||
                                                'Nenhuma observação cadastrada.'}
                                        </div>
                                    </Panel>

                                    <Panel title="Identificadores">
                                        <FieldGrid
                                            columns="grid-cols-1"
                                            items={[
                                                {
                                                    label: 'UUID',
                                                    value: client.uuid,
                                                },
                                                {
                                                    label: 'ID interno',
                                                    value: String(client.id),
                                                },
                                                {
                                                    label: 'Empresa ID',
                                                    value: client.company_id
                                                        ? String(
                                                              client.company_id,
                                                          )
                                                        : null,
                                                },
                                                {
                                                    label: 'Responsável ID',
                                                    value: client.responsible_user_id
                                                        ? String(
                                                              client.responsible_user_id,
                                                          )
                                                        : null,
                                                },
                                            ]}
                                        />
                                    </Panel>
                                </div>
                            </TabPanel>
                        </Tabs>
                    </main>
                </div>
            </div>
        </section>
    );
}
