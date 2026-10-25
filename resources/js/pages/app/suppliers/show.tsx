import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText, Mail, Phone, SquarePen, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { Separator } from '@/components/ui/separator';
import { formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import {
    FieldGrid,
    SidebarContactRow,
    isBlank,
} from '@/pages/app/clients/partials/client-show-helpers';
import type { DetailItem } from '@/pages/app/clients/partials/client-show-helpers';
import type { SupplierResource } from '@/types/app/supplier';

import {
    SUPPLIER_STATUS_META,
    SupplierStatusBadge,
    SupplierStatusMenu,
} from './components/supplier-status-menu';

interface Props {
    supplierData: SupplierResource;
    timelineSubject: {
        type: string;
        id: string | number;
    };
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

export default function ShowSupplier({ supplierData, timelineSubject }: Props) {
    const supplier = supplierData.data;
    const canUpdate = supplier.permissions.can_update;
    const canDelete = supplier.permissions.can_delete;

    const supplierName = String(supplier.name ?? supplier.uuid);
    const summary =
        [supplier.company_name, supplier.email].find(
            (value) => !isBlank(value),
        ) ?? '-';
    const initials = supplierName
        .split(' ')
        .map((namePart) => namePart[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Fornecedores', href: index.url() },
            {
                title: supplierName,
                href: show.url(supplier.uuid),
            },
        ],
    });

    const profileItems: DetailItem[] = [
        { label: 'Razão social', value: supplier.company_name },
        { label: 'CPF/CNPJ', value: formatCpfCnpj(supplier.document) },
        { label: 'E-mail financeiro', value: supplier.email },
        { label: 'Telefone', value: formatPhone(supplier.phone) },
        { label: 'Empresa ID', value: supplier.company_id?.toString() ?? null },
        { label: 'Status', value: SUPPLIER_STATUS_META[supplier.status].label },
    ];

    return (
        <>
            <Head title={supplierName} />

            <Header title={supplierName} backHref={index.url()}>
                <div className="flex flex-wrap items-center gap-2">
                    {canUpdate ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={edit.url(supplier.uuid)}>
                                <SquarePen
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Editar
                            </Link>
                        </Button>
                    ) : null}

                    {canUpdate && canDelete ? (
                        <Separator
                            orientation="vertical"
                            className="mx-1 h-6"
                        />
                    ) : null}

                    {canDelete ? (
                        <DeleteButton
                            url={destroy.url(supplier.uuid)}
                            resourceName={supplierName}
                            resourceType="fornecedor"
                            redirectTo={index.url()}
                        />
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <Card className="gap-0 py-0">
                            <CardContent className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-primary-soft text-3xl font-semibold text-primary-soft-foreground">
                                    {initials}
                                </div>

                                <div className="mt-4 space-y-1">
                                    <h2 className="truncate text-base font-semibold text-foreground">
                                        {supplierName}
                                    </h2>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {summary}
                                    </p>
                                </div>

                                <div className="mt-4">
                                    {canUpdate ? (
                                        <SupplierStatusMenu
                                            uuid={supplier.uuid}
                                            status={supplier.status}
                                            size="sm"
                                            className="w-full justify-between"
                                        />
                                    ) : (
                                        <SupplierStatusBadge
                                            status={supplier.status}
                                            className="w-full justify-center"
                                        />
                                    )}
                                </div>

                                <div className="mt-5 space-y-0.5">
                                    <SidebarContactRow
                                        icon={UserRound}
                                        label="Fornecedor"
                                        value={supplierName}
                                    />
                                    <SidebarContactRow
                                        icon={FileText}
                                        label="Documento"
                                        value={formatCpfCnpj(supplier.document)}
                                        copyable
                                    />
                                    <SidebarContactRow
                                        icon={Mail}
                                        label="E-mail"
                                        value={supplier.email}
                                        copyable
                                    />
                                    <SidebarContactRow
                                        icon={Phone}
                                        label="Telefone"
                                        value={formatPhone(supplier.phone)}
                                        copyable
                                    />
                                </div>
                            </CardContent>

                            <div className="border-t border-border px-5 py-4 text-xs text-muted-foreground tabular-nums">
                                <p>
                                    Criado em {formatDate(supplier.created_at)}
                                </p>
                                <p className="mt-1">
                                    Última edição em{' '}
                                    {formatDate(supplier.updated_at)}
                                </p>
                            </div>
                        </Card>
                    </aside>

                    <main className="min-w-0">
                        <Tabs defaultValue="overview" className="space-y-4">
                            <TabsList
                                aria-label="Seções do fornecedor"
                                className="max-w-full overflow-x-auto"
                            >
                                <TabItem value="overview" label="Detalhes" />
                                <TabItem value="notes" label="Observações" />
                                <TabItem
                                    value="timeline"
                                    label="Linha do tempo"
                                />
                            </TabsList>

                            <TabPanel value="overview" className="space-y-4">
                                <Panel title="Dados do fornecedor">
                                    <FieldGrid
                                        items={profileItems}
                                        columns="sm:grid-cols-2 xl:grid-cols-3"
                                    />
                                </Panel>

                                <Panel title="Histórico do cadastro">
                                    <FieldGrid
                                        columns="sm:grid-cols-3"
                                        items={[
                                            {
                                                label: 'Criado em',
                                                value: formatDate(
                                                    supplier.created_at,
                                                ),
                                            },
                                            {
                                                label: 'Atualizado em',
                                                value: formatDate(
                                                    supplier.updated_at,
                                                ),
                                            },
                                            {
                                                label: 'Bloqueios de exclusão',
                                                value:
                                                    supplier.deletion_blockers?.join(
                                                        ', ',
                                                    ) ?? 'Nenhum',
                                            },
                                        ]}
                                    />
                                </Panel>

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

                            <TabPanel value="notes">
                                <Panel title="Observações">
                                    <div className="min-h-40 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                        {supplier.notes ||
                                            'Nenhuma observação cadastrada.'}
                                    </div>
                                </Panel>
                            </TabPanel>

                            <TabPanel value="timeline">
                                <ActivityTimeline
                                    modelType={timelineSubject.type}
                                    modelId={timelineSubject.id}
                                    allowAttachments
                                />
                            </TabPanel>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
