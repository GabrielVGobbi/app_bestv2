import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Mail,
    MapPin,
    Phone,
    Share2,
    SquarePen,
    UserRound,
} from 'lucide-react';


import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { AddressManager } from '@/components/addresses/address-manager';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CopyableText } from '@/components/ui/copyable-text';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn, formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type { Address } from '@/types/address';
import type { ClientResource } from '@/types/app/client';
import { ClientQuickUpdatePanel } from './components/client-quick-update-panel';
import type { ClientQuickUpdateColumn } from './components/client-quick-update-panel';
import { ClientStatusMenu } from './components/client-status-menu';

interface Props {
    clientData: ClientResource;
    timelineSubject: {
        type: string;
        id: string | number;
    };
}

type DetailItem = {
    label: string;
    value: string | null | undefined;
};

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
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('rounded-lg border bg-card', className)}>
            <header className="flex min-h-10 items-center px-4 pt-4">
                <h2 className="text-sm font-semibold">{title}</h2>
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

export default function ShowClient({ clientData, timelineSubject }: Props) {
    const client = clientData.data;
    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);

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
        <>
            <Head title={clientName} />

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
                        <h1 className="text-xl font-semibold">{clientName}</h1>
                        <p className="text-sm text-muted-foreground">
                            {client.uuid}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="action"
                        asChild
                    >
                        <Link href={edit.url(client.uuid)}>
                            <SquarePen className="size-4" />
                            Editar
                        </Link>
                    </Button>

                    <Separator orientation="vertical" className="mx-1 h-6" />

                    <DeleteButton
                        url={destroy.url(client.uuid)}
                        resourceName={clientName}
                        resourceType="cliente"
                        redirectTo={index.url()}
                        size="action"
                    />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="rounded-lg border bg-card">
                        <div className="p-5">
                            <div className="flex h-32 items-center justify-center rounded-lg bg-secondary text-4xl font-semibold text-primary">
                                {initials}
                            </div>

                            <div className="mt-4 space-y-1">
                                <h2 className="truncate text-base font-semibold">
                                    {clientName}
                                </h2>
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
                                        className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        Detalhes
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="addresses"
                                        className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        Endereço
                                        <span className="rounded-full bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                            {addresses.length}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="notes"
                                        className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        Observações
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="timeline"
                                        className="h-12 rounded-none border-0 border-b-2 border-transparent bg-transparent px-0 shadow-none data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        Timeline
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <TabsContent
                                value="overview"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >
                                <div className="space-y-5">
                                    <Panel title="Dados do Cliente">
                                        <div className="space-y-5">
                                            <ClientQuickUpdatePanel
                                                key={`${client.uuid}:${client.updated_at}`}
                                                client={client}
                                                columns={quickUpdateColumns}
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

                                    <ActivityTimeline
                                        showHeader={false}
                                        modelType={timelineSubject.type}
                                        modelId={timelineSubject.id}
                                        title=""
                                        allowAttachments
                                        className="p-0 m-0 border-0 bg-transparent shadow-none"
                                    />
                                </div>
                            </TabsContent>

                            <TabsContent
                                value="addresses"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >
                                <div className="space-y-5">
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
                                </div>
                            </TabsContent>

                            <TabsContent
                                value="notes"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >
                                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                                    <Panel title="Observações">
                                        <div className="min-h-40 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                            {client.notes ||
                                                'Nenhuma Observação cadastrada.'}
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

                            <TabsContent
                                value="timeline"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >

                            </TabsContent>
                        </Tabs>


                    </main>


                </div>
            </div>
        </>
    );
}
