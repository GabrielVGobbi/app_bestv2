import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Mail,
    Phone,
    SquarePen,
    UserRound,
} from 'lucide-react';

import {
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CopyableText } from '@/components/ui/copyable-text';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn, formatCpfCnpj, formatDate, formatPhone } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import type { SupplierResource } from '@/types/app/supplier';

import { SupplierStatusMenu } from './components/supplier-status-menu';

interface Props {
    supplierData: SupplierResource;
    timelineSubject: {
        type: string;
        id: string | number;
    };
}

type DetailItem = {
    label: string;
    value: string | null | undefined;
};

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

export default function ShowSupplier({ supplierData, timelineSubject }: Props) {
    const supplier = supplierData.data;
    const canUpdate = supplier.permissions.can_update;
    const canDelete = supplier.permissions.can_delete;

    const supplierName = String(supplier.name ?? supplier.uuid);
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
        { label: 'Email financeiro', value: supplier.email },
        { label: 'Telefone', value: formatPhone(supplier.phone) },
        { label: 'Empresa ID', value: supplier.company_id?.toString() ?? null },
        { label: 'Status', value: supplier.status },
    ];

    return (
        <>
            <Head title={supplierName} />

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
                        <h1 className="text-xl font-semibold">{supplierName}</h1>
                        <p className="text-sm text-muted-foreground">
                            {supplier.uuid}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {canUpdate ? (
                        <>
                            <Button variant="outline" size="action" asChild>
                                <Link href={edit.url(supplier.uuid)}>
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

                    {canDelete ? (
                        <DeleteButton
                            url={destroy.url(supplier.uuid)}
                            resourceName={supplierName}
                            resourceType="fornecedor"
                            redirectTo={index.url()}
                            size="action"
                        />
                    ) : null}
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
                                    {supplierName}
                                </h2>
                                <p className="truncate text-sm text-muted-foreground">
                                    {cleanValue(
                                        supplier.company_name ?? supplier.email,
                                    )}
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
                                    <Badge
                                        variant="secondary"
                                        className="w-full justify-center"
                                    >
                                        {supplier.status === 'active'
                                            ? 'Ativo'
                                            : supplier.status === 'inactive'
                                              ? 'Inativo'
                                              : 'Arquivado'}
                                    </Badge>
                                )}
                            </div>

                            <div className="mt-5 space-y-1">
                                <SidebarItem
                                    icon={UserRound}
                                    label="Fornecedor"
                                    value={supplierName}
                                />
                                <SidebarItem
                                    icon={FileText}
                                    label="Documento"
                                    value={formatCpfCnpj(supplier.document)}
                                    copyable
                                />
                                <SidebarItem
                                    icon={Mail}
                                    label="Email"
                                    value={supplier.email}
                                    copyable
                                />
                                <SidebarItem
                                    icon={Phone}
                                    label="Telefone"
                                    value={formatPhone(supplier.phone)}
                                    copyable
                                />
                            </div>
                        </div>

                        <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                            <p>Criado - {formatDate(supplier.created_at)}</p>
                            <p className="mt-1">
                                Última edição - {formatDate(supplier.updated_at)}
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
                                    <Panel title="Dados do Fornecedor">
                                        <FieldGrid items={profileItems} />
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

                                    <ActivityTimeline
                                        showHeader={false}
                                        modelType={timelineSubject.type}
                                        modelId={timelineSubject.id}
                                        title=""
                                        allowAttachments
                                        className="m-0 border-0 bg-transparent p-0 shadow-none"
                                    />
                                </div>
                            </TabsContent>

                            <TabsContent
                                value="notes"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >
                                <Panel title="Observações">
                                    <div className="min-h-40 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                        {supplier.notes ||
                                            'Nenhuma observação cadastrada.'}
                                    </div>
                                </Panel>
                            </TabsContent>

                            <TabsContent
                                value="timeline"
                                className="max-h-[70vh] overflow-y-auto p-5 outline-none"
                            >
                                <ActivityTimeline
                                    modelType={timelineSubject.type}
                                    modelId={timelineSubject.id}
                                    allowAttachments
                                />
                            </TabsContent>
                        </Tabs>
                    </main>
                </div>
            </div>
        </>
    );
}
