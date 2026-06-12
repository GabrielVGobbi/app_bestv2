import { Link } from '@inertiajs/react';
import {
    Edit,
    ExternalLink,
    FileText,
    Mail,
    MapPin,
    Phone,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CopyableText } from '@/components/ui/copyable-text';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { getErrorMessage, isApiError } from '@/lib/http';
import { cn, formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import type { Address } from '@/types/address';
import type { Client } from '@/types/app/client';
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

type DetailItem = {
    label: string;
    value: string | null | undefined;
};

const tabTriggerClass =
    'h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none';

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

function cleanValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '-';
}

function detailsErrorMessage(error: Error | unknown): string {
    if (isApiError(error)) {
        return getErrorMessage(error);
    }

    return error instanceof Error
        ? error.message
        : 'Não foi possível carregar os detalhes do cliente.';
}

function FieldGrid({
    items,
    columns = 'sm:grid-cols-2 xl:grid-cols-3',
}: {
    items: DetailItem[];
    columns?: string;
}) {
    return (
        <dl className={cn('grid gap-x-8 gap-y-5', columns)}>
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <dt className="text-xs font-medium text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd className="mt-1 min-w-0 text-sm font-medium break-words text-foreground">
                        {cleanValue(item.value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Panel({
    title,
    children,
    className,
}: {
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('rounded-lg border bg-card', className)}>
            <header className="flex min-h-10 items-center px-4 pt-4">
                <h3 className="text-sm font-semibold">{title}</h3>
            </header>
            <div className="px-4 pt-3 pb-4">{children}</div>
        </section>
    );
}

function SidebarItem({
    icon: Icon,
    label,
    value,
    copyable = false,
    copyValue,
}: {
    icon: typeof UserRound;
    label: string;
    value: string | null | undefined;
    copyable?: boolean;
    copyValue?: string | null | undefined;
}) {
    const content = (
        <>
            <Icon className="size-4 shrink-0" />
            <span className="sr-only">{label}</span>
            <span className="truncate">{cleanValue(value)}</span>
        </>
    );

    if (copyable) {
        return (
            <CopyableText
                value={value}
                copyValue={copyValue}
                label={label}
                className="w-full px-2 py-1.5 text-sm text-muted-foreground"
            >
                <span className="flex min-w-0 items-center gap-2">
                    {content}
                </span>
            </CopyableText>
        );
    }

    return (
        <div className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-secondary/60">
            {content}
        </div>
    );
}

function DefaultAddress({ address }: { address?: Address }) {
    if (!address) {
        return (
            <div className="text-sm text-muted-foreground">
                Nenhum endereço padrão definido.
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <MapPin className="size-4 text-muted-foreground" />
                <p className="text-sm font-medium">
                    {address.name || 'Endereço'}
                </p>
                <Badge
                    variant="secondary"
                    className="h-5 rounded-sm px-1.5 text-[10px]"
                >
                    Padrão
                </Badge>
            </div>
            <p className="text-sm leading-6 text-muted-foreground">
                {address.full_address}
            </p>
        </div>
    );
}

function ClientDetailsSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside className="space-y-4">
                <div className="rounded-lg border bg-card p-5">
                    <Skeleton className="h-28 w-full" />
                    <div className="mt-4 grid gap-2">
                        <Skeleton className="h-4 w-36" />
                        <Skeleton className="h-3 w-44" />
                    </div>
                </div>
            </aside>
            <main className="space-y-4">
                <div className="rounded-lg border bg-card p-5">
                    <Skeleton className="h-4 w-32" />
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton key={index} className="h-10 w-full" />
                        ))}
                    </div>
                </div>
                <div className="rounded-lg border bg-card p-5">
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
            <section className="grid h-full min-h-72 place-items-center overflow-hidden rounded-lg border border-dashed bg-muted/20 p-8 text-center">
                <div className="max-w-md space-y-2">
                    <h2 className="text-lg font-semibold">
                        Selecione um cliente
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Clique em um cliente na lista para carregar os dados
                        detalhados aqui.
                    </p>
                </div>
            </section>
        );
    }

    if (isLoading || (isFetching && client === null)) {
        return (
            <section className="h-full min-h-0 overflow-y-auto rounded-lg border bg-background p-4">
                <ClientDetailsSkeleton />
            </section>
        );
    }

    if (error || client === null) {
        return (
            <section className="grid h-full min-h-72 place-items-center overflow-hidden rounded-lg border bg-background p-8 text-center">
                <div className="max-w-md space-y-4">
                    <div className="space-y-2">
                        <h2 className="text-lg font-semibold">
                            Detalhes indisponíveis
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {detailsErrorMessage(error)}
                        </p>
                    </div>
                    <Button type="button" variant="outline" onClick={onRetry}>
                        Tentar novamente
                    </Button>
                </div>
            </section>
        );
    }

    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);
    const clientName = String(client.name ?? client.uuid);
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
        <section className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-lg border bg-background">
            <div className="flex shrink-0 flex-col gap-4 border-b p-4 xl:flex-row xl:items-center xl:justify-between">
                <div className="min-w-0">
                    <div className="flex min-w-0 items-center gap-2">
                        <h2 className="truncate text-xl font-semibold">
                            {clientName}
                        </h2>
                    </div>
                    <p className="truncate text-sm text-muted-foreground">
                        {cleanValue(client.company_name ?? client.email)}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show.url(client.uuid)}>
                            <ExternalLink className="size-4" />
                            Abrir
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={edit.url(client.uuid)}>
                            <Edit className="size-4" />
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
                        <Trash2 className="size-4" />
                        Excluir
                    </Button>
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="rounded-lg border bg-card">
                        <div className="p-5">
                            <div className="flex h-32 items-center justify-center rounded-lg bg-secondary text-4xl font-semibold text-primary">
                                {initials}
                            </div>

                            <div className="mt-4 space-y-1">
                                <h3 className="truncate text-base font-semibold">
                                    {clientName}
                                </h3>
                                <p className="truncate text-sm text-muted-foreground">
                                    {cleanValue(
                                        client.company_name ?? client.email,
                                    )}
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

                            <div className="mt-5 space-y-1">
                                <SidebarItem
                                    icon={UserRound}
                                    label="Cliente"
                                    value={clientName}
                                />
                                <SidebarItem
                                    icon={FileText}
                                    label="Documento"
                                    value={formatCpfCnpj(client.document)}
                                    copyable
                                />
                                <SidebarItem
                                    icon={Mail}
                                    label="Email"
                                    value={client.email}
                                    copyable
                                />
                                <SidebarItem
                                    icon={Phone}
                                    label="Telefone"
                                    value={formatPhone(client.phone)}
                                    copyable
                                />
                                <SidebarItem
                                    icon={Share2}
                                    label="Compartilhar"
                                    value="Compartilhar"
                                />
                            </div>
                        </div>

                        <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                            <p>Criado - {formatDate(client.created_at)}</p>
                            <p className="mt-1">
                                Última edição - {formatDate(client.updated_at)}
                            </p>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Tabs
                            defaultValue="overview"
                            className="overflow-hidden rounded-lg border bg-card"
                        >
                            <div className="overflow-x-auto border-b px-5">
                                <TabsList className="h-auto gap-6 rounded-none bg-transparent p-0">
                                    <TabsTrigger
                                        value="overview"
                                        className={tabTriggerClass}
                                    >
                                        Detalhes
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="addresses"
                                        className={tabTriggerClass}
                                    >
                                        Endereço
                                        <span className="rounded-full bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                            {addresses.length}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="notes"
                                        className={tabTriggerClass}
                                    >
                                        Observações
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <TabsContent
                                value="overview"
                                className="p-5 outline-none"
                            >
                                <div className="space-y-5">
                                    <Panel title="Dados do Cliente">
                                        <div className="space-y-5">
                                            <ClientQuickUpdatePanel
                                                key={`${client.uuid}:${client.updated_at}`}
                                                client={client}
                                                columns={quickUpdateColumns}
                                                reloadProps={null}
                                                onSaved={onRetry}
                                            />
                                            <Separator />
                                            <FieldGrid items={profileItems} />
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
                                                    value: String(
                                                        addresses.length,
                                                    ),
                                                },
                                            ]}
                                        />
                                    </Panel>
                                </div>
                            </TabsContent>

                            <TabsContent
                                value="addresses"
                                className="p-5 outline-none"
                            >
                                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                                    <AddressManager
                                        model="client"
                                        modelId={client.uuid}
                                        initialAddresses={addresses}
                                    />

                                    <div className="space-y-5">
                                        <Panel title="Endereço principal">
                                            <DefaultAddress
                                                address={defaultAddress}
                                            />
                                        </Panel>

                                        <Panel title="Contato">
                                            <FieldGrid
                                                columns="grid-cols-1"
                                                items={[
                                                    {
                                                        label: 'Email',
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
                            </TabsContent>

                            <TabsContent
                                value="notes"
                                className="p-5 outline-none"
                            >
                                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
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
                            </TabsContent>
                        </Tabs>
                    </main>
                </div>
            </div>
        </section>
    );
}
