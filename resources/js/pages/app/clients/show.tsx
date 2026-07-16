import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
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
import { Button } from '@/components/ui/button';
import { CopyableText } from '@/components/ui/copyable-text';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCpfCnpj, formatDate, formatPhone, cn } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type { ClientResource } from '@/types/app/client';
import { ClientQuickUpdatePanel } from './components/client-quick-update-panel';
import type { ClientQuickUpdateColumn } from './components/client-quick-update-panel';
import { ClientStatusMenu } from './components/client-status-menu';
import {
    ClientStatusBadge,
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

const tabTriggerClass = cn(
    'group relative h-11 flex-none gap-2 rounded-none border-0 bg-transparent px-3.5',
    'text-sm font-medium text-muted-foreground shadow-none',
    'hover:bg-transparent hover:text-foreground',
    'focus-visible:ring-0 focus-visible:outline-none',
    'data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none',
    'after:absolute after:right-2 after:bottom-0 after:left-2 after:h-0.5 after:rounded-full after:bg-transparent after:transition-colors',
    'data-[state=active]:after:bg-primary',
);

export default function ShowClient({ clientData, timelineSubject }: Props) {
    const client = clientData.data;
    const responsibleUser = client.responsible_user;
    const addresses = client.addresses ?? [];
    const defaultAddress = addresses.find((address) => address.is_default);
    const canUpdate = client.permissions.can_update;
    const editUrl = edit.url(client.uuid);

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
    const subtitle = !isBlank(client.company_name)
        ? client.company_name
        : !isBlank(client.email)
          ? client.email
          : null;

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

            <Header>
                <div className="flex min-w-0 items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8 shrink-0"
                        asChild
                    >
                        <Link href={index.url()} aria-label="Voltar para clientes">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="truncate text-xl font-semibold">
                                {clientName}
                            </h1>
                           
                        </div>
                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            {subtitle ? (
                                <span className="truncate">{subtitle}</span>
                            ) : null}
                            {subtitle ? (
                                <span className="text-border" aria-hidden>
                                    ·
                                </span>
                            ) : null}
                            <CopyableText
                                value={client.uuid}
                                label="ID"
                                className="text-xs text-muted-foreground"
                            >
                                <span className="underline-offset-2 group-hover/copyable:underline">
                                    Copiar ID
                                </span>
                            </CopyableText>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {canUpdate ? (
                        <>
                            <Button variant="outline" size="action" asChild>
                                <Link href={editUrl}>
                                    <SquarePen className="size-4" />
                                    Editar
                                </Link>
                            </Button>

                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                        </>
                    ) : null}

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
                <div className="grid gap-4 p-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-20 items-center justify-center rounded-lg bg-secondary text-2xl font-semibold text-primary">
                                    {initials}
                                </div>

                                <div className="mt-5 space-y-0.5">
                                    <SidebarContactRow
                                        icon={FileText}
                                        label="Documento"
                                        value={formatCpfCnpj(client.document)}
                                        copyable
                                        emptyHref={canUpdate ? editUrl : undefined}
                                        emptyLabel={
                                            canUpdate
                                                ? 'Adicionar documento'
                                                : undefined
                                        }
                                    />
                                    <SidebarContactRow
                                        icon={Mail}
                                        label="Email"
                                        value={client.email}
                                        copyable
                                        emptyHref={canUpdate ? editUrl : undefined}
                                        emptyLabel={
                                            canUpdate
                                                ? 'Adicionar email'
                                                : undefined
                                        }
                                    />
                                    <SidebarContactRow
                                        icon={Phone}
                                        label="Telefone"
                                        value={formatPhone(client.phone)}
                                        copyable
                                        emptyHref={canUpdate ? editUrl : undefined}
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
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <p>Criado · {formatDate(client.created_at)}</p>
                                <p className="mt-1">
                                    Atualizado · {formatDate(client.updated_at)}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0">
                        <Tabs
                            defaultValue="overview"
                            className="gap-0 overflow-hidden rounded-xl border border-border/80 bg-card shadow-[var(--shadow-app-panel)]"
                        >
                            <div className="border-b border-border/70 bg-muted/35">
                                <TabsList className="h-11 w-full justify-start gap-0 overflow-x-auto rounded-none border-0 bg-transparent p-0">
                                    {clientTabs.map((tab) => {
                                        const Icon = tab.icon;

                                        return (
                                            <TabsTrigger
                                                key={tab.value}
                                                value={tab.value}
                                                className={tabTriggerClass}
                                            >
                                                <Icon className="size-3.5 opacity-70 group-data-[state=active]:opacity-100" />
                                                <span>{tab.label}</span>
                                                {tab.count ? (
                                                    <span className="rounded-md bg-background/80 px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground tabular-nums ring-1 ring-border/60 group-data-[state=active]:text-foreground">
                                                        {addresses.length}
                                                    </span>
                                                ) : null}
                                            </TabsTrigger>
                                        );
                                    })}
                                </TabsList>
                            </div>

                            <TabsContent
                                value="overview"
                                className="mt-0 space-y-6 p-5 outline-none sm:p-6"
                            >
                                {canUpdate ? (
                                    <Section title="Dados rápidos">
                                        <div className="rounded-lg border border-border/70 px-3.5 py-3.5">
                                            <ClientQuickUpdatePanel
                                                key={`${client.uuid}:${client.updated_at}`}
                                                client={client}
                                                columns={quickUpdateColumns}
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
                            </TabsContent>

                            <TabsContent
                                value="addresses"
                                className="mt-0 p-5 outline-none sm:p-6"
                            >
                                <AddressManager
                                    model="client"
                                    modelId={client.uuid}
                                    initialAddresses={addresses}
                                    variant="plain"
                                    title="Lista de endereços"
                                    emptyMessage="Cadastre o endereço de cobrança ou entrega deste cliente."
                                />
                            </TabsContent>

                            <TabsContent
                                value="notes"
                                className="mt-0 space-y-6 p-5 outline-none sm:p-6"
                            >
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
                                                    Editar
                                                </Link>
                                            </Button>
                                        ) : null
                                    }
                                >
                                    {isBlank(client.notes) ? (
                                        <div className="flex min-h-36 flex-col items-start justify-center gap-3 rounded-lg border border-dashed bg-muted/25 px-4 py-8">
                                            <p className="text-sm text-muted-foreground">
                                                Nenhuma observação cadastrada.
                                            </p>
                                            {canUpdate ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link href={editUrl}>
                                                        Adicionar observação
                                                    </Link>
                                                </Button>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-border/70 px-4 py-3 text-sm leading-6 whitespace-pre-wrap text-foreground">
                                            {client.notes}
                                        </div>
                                    )}
                                </Section>

                                <TechnicalIdsDisclosure
                                    items={technicalItems}
                                />
                            </TabsContent>

                            <TabsContent
                                value="activity"
                                className="mt-0 p-5 outline-none sm:p-6"
                            >
                                <ActivityTimeline
                                    showHeader={false}
                                    modelType={timelineSubject.type}
                                    modelId={timelineSubject.id}
                                    title=""
                                    allowAttachments
                                    className="m-0 border-0 bg-transparent p-0 shadow-none"
                                />
                            </TabsContent>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
