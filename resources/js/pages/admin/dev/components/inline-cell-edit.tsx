import { Head, router, setLayoutProps } from '@inertiajs/react';
import type { InfiniteData } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { BookOpen, Code2, PencilLine, Table2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    components,
    inlineCellEdit,
    updateInlineCellEditUser,
} from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import { DataTable, InlineCellEditor } from '@/components/data-table';
import type { CellEditMode } from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Paginated } from '@/types/pagination';
import type { User } from '@/types/user';

const usageCode = `import { InlineCellEditor } from '@/components/data-table'
import { updateInlineCellEditUser } from '@/actions/App/Http/Controllers/Admin/DevToolsController'

const columns: ColumnDef<User>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Inline',
        cell: ({ row }) => (
            <InlineCellEditor
                mode="inline"
                value={row.original.name}
                onSave={async (name) => {
                    await router.patch(updateInlineCellEditUser.url(row.original.uuid), {
                        field: 'name',
                        value: name,
                    })
                }}
            >
                {({ value, onChange, save, isLoading }) => (
                    <Input
                        value={value}
                        disabled={isLoading}
                        autoFocus
                        onChange={(event) => onChange(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') void save()
                        }}
                    />
                )}
            </InlineCellEditor>
        ),
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'Popover',
        cell: ({ row }) => (
            <InlineCellEditor
                mode="popover"
                label="Editar email"
                value={row.original.email}
                onSave={async (email) => {
                    await router.patch(update.url(row.original.uuid), { email })
                }}
            >
                {({ value, onChange, save, isLoading }) => (
                    <Input
                        value={value}
                        disabled={isLoading}
                        autoFocus
                        onChange={(event) => onChange(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') void save()
                        }}
                    />
                )}
            </InlineCellEditor>
        ),
    },
    {
        id: 'verified',
        header: 'Modal',
        cell: ({ row }) => (
            <InlineCellEditor
                mode="modal"
                label="Editar verificacao"
                value={row.original.email_verified_at ? 'verified' : 'pending'}
                displayValue={row.original.email_verified_at ? 'Sim' : 'Nao'}
                onSave={async (status) => {
                    await router.patch(update.url(row.original.uuid), {
                        email_verified_at: status === 'verified' ? new Date().toISOString() : null,
                    })
                }}
            >
                {({ value, onChange }) => (
                    <Select value={value} onValueChange={onChange}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="verified">Verificado</SelectItem>
                            <SelectItem value="pending">Pendente</SelectItem>
                        </SelectContent>
                    </Select>
                )}
            </InlineCellEditor>
        ),
    },
]`;

function CodeBlock({ code }: { code: string }) {
    return (
        <pre className="overflow-x-auto rounded-lg border bg-muted/50 p-4 text-xs leading-relaxed">
            <code>{code}</code>
        </pre>
    );
}

function updateCachedUser(
    queryClient: ReturnType<typeof useQueryClient>,
    userUuid: string,
    patch: Partial<User>,
) {
    queryClient.setQueriesData<InfiniteData<Paginated<User>>>(
        { queryKey: ['dev-components-users', 'infinite'], exact: false },
        (old) => {
            if (!old) {
                return old;
            }

            return {
                ...old,
                pages: old.pages.map((page) => ({
                    ...page,
                    data: page.data.map((user) =>
                        user.uuid === userUuid ? { ...user, ...patch } : user,
                    ),
                })),
            };
        },
    );
}

function persistUserCell(
    queryClient: ReturnType<typeof useQueryClient>,
    userUuid: string,
    field: 'name' | 'email' | 'email_verified_at',
    value: string,
    patch: Partial<User>,
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.patch(
            updateInlineCellEditUser.url(userUuid),
            { field, value },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    updateCachedUser(queryClient, userUuid, patch);
                    resolve();
                },
                onError: (errors) => {
                    reject(
                        new Error(
                            errors.value ??
                                errors.field ??
                                'Nao foi possivel salvar a celula.',
                        ),
                    );
                },
            },
        );
    });
}

export default function InlineCellEdit() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Components', href: components.url() },
            { title: 'Inline cell edit', href: inlineCellEdit.url() },
        ],
    });

    const queryClient = useQueryClient();
    const [activeMode, setActiveMode] = useState<CellEditMode>('inline');

    const columns = useMemo<ColumnDef<User>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nome',
                size: 260,
                cell: ({ row }) => (
                    <InlineCellEditor
                        mode={activeMode}
                        label={`Editar nome (${activeMode})`}
                        modalTitle="Editar nome"
                        modalDescription="Este modo abre o editor em um dialog centralizado."
                        popoverWidth={320}
                        value={row.original.name}
                        validate={(value) =>
                            value.trim().length === 0
                                ? 'Informe um nome.'
                                : null
                        }
                        onSave={async (name) => {
                            await persistUserCell(
                                queryClient,
                                row.original.uuid,
                                'name',
                                name,
                                { name },
                            );
                        }}
                    >
                        {({ value, onChange, save, cancel, isLoading }) => (
                            <Input
                                value={value}
                                disabled={isLoading}
                                autoFocus
                                className="h-8"
                                onChange={(event) =>
                                    onChange(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        void save();
                                    }

                                    if (event.key === 'Escape') {
                                        cancel();
                                    }
                                }}
                            />
                        )}
                    </InlineCellEditor>
                ),
                meta: { label: 'Nome' },
            },
            {
                id: 'email',
                accessorKey: 'email',
                header: 'Email',
                size: 280,
                cell: ({ row }) => (
                    <InlineCellEditor
                        mode="popover"
                        label="Editar email"
                        popoverWidth={320}
                        value={row.original.email}
                        validate={(value) =>
                            value.includes('@')
                                ? null
                                : 'Informe um email valido.'
                        }
                        onSave={async (email) => {
                            await persistUserCell(
                                queryClient,
                                row.original.uuid,
                                'email',
                                email,
                                {
                                    email,
                                    email_verified_at: null,
                                },
                            );
                        }}
                    >
                        {({ value, onChange, save, cancel, isLoading }) => (
                            <Input
                                type="email"
                                value={value}
                                disabled={isLoading}
                                autoFocus
                                className="h-8"
                                onChange={(event) =>
                                    onChange(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        void save();
                                    }

                                    if (event.key === 'Escape') {
                                        cancel();
                                    }
                                }}
                            />
                        )}
                    </InlineCellEditor>
                ),
                meta: { label: 'Email' },
            },
            {
                id: 'email_verified_at',
                accessorKey: 'email_verified_at',
                header: 'Verificado',
                size: 140,
                cell: ({ row }) => {
                    const verifiedValue = row.original.email_verified_at
                        ? 'verified'
                        : 'pending';

                    return (
                        <InlineCellEditor
                            mode="modal"
                            label="Editar verificacao"
                            modalTitle="Editar status de verificacao"
                            modalDescription="Esta alteracao faz PATCH no backend e sincroniza a tabela ao concluir."
                            value={verifiedValue}
                            displayValue={
                                row.original.email_verified_at ? (
                                    <Badge variant="secondary">Sim</Badge>
                                ) : (
                                    <Badge variant="outline">Nao</Badge>
                                )
                            }
                            onSave={async (status) => {
                                await persistUserCell(
                                    queryClient,
                                    row.original.uuid,
                                    'email_verified_at',
                                    status,
                                    {
                                        email_verified_at:
                                            status === 'verified'
                                                ? new Date().toISOString()
                                                : null,
                                    },
                                );
                            }}
                        >
                            {({ value, onChange, isLoading }) => (
                                <Select
                                    value={value}
                                    disabled={isLoading}
                                    onValueChange={onChange}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="verified">
                                            Verificado
                                        </SelectItem>
                                        <SelectItem value="pending">
                                            Pendente
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                        </InlineCellEditor>
                    );
                },
                meta: { label: 'Verificado', align: 'center' },
            },
        ],
        [activeMode, queryClient],
    );

    return (
        <>
            <Head title="Inline cell edit" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <Heading
                            title="Inline cell edit"
                            description="Editor reutilizavel para celulas do DataTable com estado de loading, erro e cancelamento."
                        />
                        <Badge variant="secondary" className="w-fit">
                            resources/js/components/data-table
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <PencilLine className="size-4" />
                                    Modos
                                </CardTitle>
                                <CardDescription>
                                    Teste nome como `inline`, email como
                                    `popover` e verificacao como `modal`.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Table2 className="size-4" />
                                    DataTable
                                </CardTitle>
                                <CardDescription>
                                    Use dentro do `cell` da coluna do TanStack
                                    Table.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BookOpen className="size-4" />
                                    Persistencia
                                </CardTitle>
                                <CardDescription>
                                    `onSave` pode chamar Inertia, API JSON ou
                                    atualizar cache otimista.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Table2 className="size-5" />
                                        Demo no DataTable
                                    </CardTitle>
                                    <CardDescription className="mt-1.5">
                                        Escolha o modo e clique em qualquer
                                        nome. Email continua como popover e
                                        verificacao como modal para comparar.
                                    </CardDescription>
                                </div>

                                <div className="grid gap-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">
                                        Modo do campo Nome
                                    </span>
                                    <Select
                                        value={activeMode}
                                        onValueChange={(value) =>
                                            setActiveMode(value as CellEditMode)
                                        }
                                    >
                                        <SelectTrigger className="w-44">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="inline">
                                                Inline
                                            </SelectItem>
                                            <SelectItem value="popover">
                                                Popover
                                            </SelectItem>
                                            <SelectItem value="modal">
                                                Modal
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 p-3 text-sm">
                                <span className="text-muted-foreground">
                                    Modo ativo:
                                </span>
                                <Badge variant="secondary">{activeMode}</Badge>
                                <span className="text-muted-foreground">
                                    Agora clique em uma celula da coluna Nome.
                                </span>
                            </div>

                            <DataTable<User>
                                queryKey="dev-components-users"
                                endpoint={apiRoutes.users.index}
                                mode="infinite-scroll"
                                columns={columns}
                                keyExtractor={(user) => user.uuid}
                                features={{
                                    search: true,
                                    filters: false,
                                    columnVisibility: true,
                                    refresh: true,
                                }}
                                infiniteScrollConfig={{
                                    minHeight: 280,
                                    loadMoreThreshold: '120px',
                                }}
                                searchPlaceholder="Buscar usuarios"
                                appearance={{
                                    size: 'sm',
                                    gridLines: 'horizontal',
                                    rowStyle: 'soft-striped',
                                }}
                            />
                        </CardContent>
                    </Card>

                    <Card className="rounded-lg">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Code2 className="size-5" />
                                Uso em coluna
                            </CardTitle>
                            <CardDescription>
                                Exemplo base para adaptar em uma coluna real do
                                DataTable.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <CodeBlock code={usageCode} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
