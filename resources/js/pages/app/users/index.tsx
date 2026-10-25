import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import type { InfiniteData } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Edit,
    Eye,
    KeyRound,
    Plus,
    Trash2,
    UserCheck,
    UserPlus,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import {
    bulkDestroy,
    index as usersIndex,
} from '@/actions/App/Http/Controllers/Api/UserApiController';

import {
    destroy,
    edit,
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/UserController';
import Header from '@/components/dashboard/header';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
    DataTableQuickFilter,
    DataTableSelectionResolver,
} from '@/components/data-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePrivateState } from '@/contexts/private-state-context';
import { apiRoutes } from '@/lib/api/api-routes';
import { getErrorMessage, http, httpGet, toApiError } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';
import type { Paginated } from '@/types/pagination';
import type { User } from '@/types/user';

// Defined outside the component: no state/props dependencies, never recreated.
const columns: ColumnDef<User>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        meta: { label: 'Nome' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'E-mail',
        meta: { label: 'E-mail', truncate: true },
    },
    {
        id: 'email_verified_at',
        accessorKey: 'email_verified_at',
        header: 'Verificado',
        cell: ({ row }) =>
            row.original.email_verified_at ? (
                <Badge variant="success">Sim</Badge>
            ) : (
                <Badge variant="secondary">Não</Badge>
            ),
        meta: { label: 'Verificado', align: 'center' },
    },
    {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Criado em',
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: { label: 'Criado em', type: 'date' },
    },
];

const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Nome',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por nome',
    },
    {
        id: 'email',
        label: 'E-mail',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por e-mail',
    },
];

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Data de criação' },
    { value: 'updated_at', label: 'Última atualização' },
];

const quickFilters: DataTableQuickFilter[] = [
    {
        id: 'email_verified_at',
        label: 'Verificação',
        placeholder: 'Todos',
        icon: UserCheck,
        options: [
            { label: 'Verificados', value: true, operator: 'not_null' },
            { label: 'Não verificados', value: false, operator: 'null' },
        ],
        clearable: true,
    },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Models\\User',
    columnsEndpoint: '/api/v1/exports/columns',
};

const bulkAggregates: DataTableBulkAggregate<User>[] = [
    {
        id: 'verified-count',
        label: 'Verificados',
        fn: (rows) => rows.filter((u) => u.email_verified_at !== null).length,
        format: (value) => String(value),
    },
];

interface Props {
    can: { create: boolean; update: boolean; delete: boolean };
}

const resolveUsersSelection: DataTableSelectionResolver<User> = async (
    ids,
    { signal },
) => {
    const rows: User[] = [];
    const requested = new Set(ids);

    if (requested.size !== ids.length) {
        throw new Error('A seleção contém identificadores repetidos.');
    }

    for (let offset = 0; offset < ids.length; offset += 100) {
        const batch = ids.slice(offset, offset + 100);
        const result = await httpGet<Paginated<User>>(usersIndex.url(), {
            signal,
            params: {
                page: 1,
                per_page: 100,
                filter: { uuid: { operator: 'in', value: batch } },
            },
        });

        if (
            !Array.isArray(result.data) ||
            result.meta?.current_page !== 1 ||
            result.meta?.total !== result.data.length ||
            result.meta?.last_page !== 1 ||
            result.data.some((user) => !batch.includes(user.uuid))
        ) {
            throw new Error(
                'Não foi possível obter a seleção completa. Tente novamente.',
            );
        }

        rows.push(...result.data);
    }

    const returned = new Set(rows.map((user) => user.uuid));

    if (returned.size !== rows.length) {
        throw new Error('A resposta contém identificadores repetidos.');
    }

    return { rows, unavailableIds: ids.filter((id) => !returned.has(id)) };
};

export default function Index({ can }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Usuários', href: index.url() }],
    });

    const [createUserOpen, setCreateUserOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<User | null>(null);
    const [bulkDeleteUsers, setBulkDeleteUsers] = useState<User[]>([]);
    const [bulkDeletePending, setBulkDeletePending] = useState(false);
    const [bulkDeleteError, setBulkDeleteError] = useState<string | null>(null);
    const bulkOperation = useRef<AbortController | null>(null);
    const { scope } = usePrivateState();

    // Ref instead of state: storing a function reference doesn't need re-renders.
    const clearSelectionRef = useRef<(() => void) | null>(null);

    const queryClient = useQueryClient();

    useEffect(
        () => () => {
            bulkOperation.current?.abort();
            bulkOperation.current = null;
        },
        [scope],
    );

    const handleDelete = useCallback(
        (user: User) => {
            if (!can.delete || user.can?.delete !== true) {
                return;
            }

            queryClient.setQueriesData<InfiniteData<Paginated<User>>>(
                { queryKey: ['users', 'infinite'], exact: false },
                (old) => {
                    if (!old) {
                        return old;
                    }

                    return {
                        ...old,
                        pages: old.pages.map((page) => ({
                            ...page,
                            data: page.data.filter((u) => u.uuid !== user.uuid),
                        })),
                    };
                },
            );

            setUserToDelete(null);

            router.delete(destroy.url(user.uuid), {
                preserveScroll: true,
                onError: () =>
                    void queryClient.invalidateQueries({ queryKey: ['users'] }),
            });
        },
        [queryClient, can.delete],
    );

    const handleBulkDelete = useCallback(
        async (users: User[]) => {
            if (
                !can.delete ||
                users.length === 0 ||
                users.length > 100 ||
                !scope.isCurrent() ||
                bulkOperation.current ||
                users.some((user) => user.can?.delete !== true)
            ) {
                return;
            }

            const controller = new AbortController();
            bulkOperation.current = controller;
            const clearSnapshot = clearSelectionRef.current;
            const abort = () => controller.abort();
            scope.signal.addEventListener('abort', abort, { once: true });
            const isCurrent = () =>
                scope.isCurrent() &&
                !controller.signal.aborted &&
                bulkOperation.current === controller;
            setBulkDeletePending(true);
            setBulkDeleteError(null);

            try {
                const ids = users.map((user) => user.uuid);
                const fresh = await resolveUsersSelection(ids, {
                    signal: controller.signal,
                });

                if (!isCurrent()) {
                    return;
                }

                if (
                    fresh.unavailableIds.length ||
                    fresh.rows.some((user) => user.can?.delete !== true)
                ) {
                    throw new Error(
                        'Há usuários indisponíveis, removidos ou sem permissão de exclusão. Revise a seleção.',
                    );
                }

                const response = await http.delete<{
                    data: { deleted_ids: string[]; deleted_count: number };
                }>(bulkDestroy.url(), {
                    data: { ids },
                    signal: controller.signal,
                });

                if (!isCurrent()) {
                    return;
                }

                const receipt = response.data.data;

                if (
                    !Array.isArray(receipt?.deleted_ids) ||
                    receipt.deleted_count !== ids.length ||
                    receipt.deleted_ids.length !== ids.length ||
                    new Set(receipt.deleted_ids).size !== ids.length ||
                    receipt.deleted_ids.some((id) => !ids.includes(id))
                ) {
                    throw new Error(
                        'A confirmação da exclusão está incompleta. Atualize a lista antes de continuar.',
                    );
                }

                clearSnapshot?.();
                setBulkDeleteUsers([]);
                clearSelectionRef.current = null;
                void queryClient.invalidateQueries({ queryKey: ['users'] });
            } catch (error) {
                if (isCurrent()) {
                    setBulkDeleteError(
                        error instanceof Error && !('type' in error)
                            ? error.message
                            : getErrorMessage(toApiError(error)),
                    );
                }
            } finally {
                scope.signal.removeEventListener('abort', abort);

                if (isCurrent()) {
                    setBulkDeletePending(false);
                }

                if (bulkOperation.current === controller) {
                    bulkOperation.current = null;
                }
            }
        },
        [queryClient, can.delete, scope],
    );

    const actions = useMemo<DataTableAction<User>[]>(() => {
        const userActions: DataTableAction<User>[] = [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (user) => show.url(user.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Editar',
                disabled: (user) => user.can?.update !== true,
                icon: <Edit className="size-4" />,
                variant: 'outline',
                href: (user) => edit.url(user.uuid),
                showLabel: false,
                tooltip: 'Editar',
            },
            {
                label: 'Excluir',
                disabled: (user) => user.can?.delete !== true,
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (user) => setUserToDelete(user),
            },
        ];

        return userActions.filter((action) =>
            action.label === 'Editar'
                ? can.update
                : action.label === 'Excluir'
                  ? can.delete
                  : true,
        );
    }, [can.update, can.delete]);

    const bulkActions = useMemo<DataTableBulkAction<User>[]>(
        () => [
            ...(can.delete
                ? [
                      {
                          id: 'bulk-delete',
                          label: 'Excluir',
                          icon: <Trash2 className="size-4" />,
                          variant: 'destructive' as const,
                          disabled: (rows: User[]) =>
                              rows.length === 0 ||
                              rows.length > 100 ||
                              rows.some((user) => user.can?.delete !== true),
                          onClick: (
                              rows: User[],
                              clearSelection: () => void,
                          ) => {
                              clearSelectionRef.current = clearSelection;
                              setBulkDeleteError(null);
                              setBulkDeleteUsers(rows);
                          },
                      },
                  ]
                : []),
        ],
        [can.delete],
    );

    return (
        <>
            <Head title="Usuários" />

            <Header title="Usuários" subtitle="Gerencie as contas de usuário">
                {can.create && (
                    <Button
                        type="button"
                        onClick={() => setCreateUserOpen(true)}
                    >
                        <Plus className="size-4" />
                        Novo usuário
                    </Button>
                )}
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Dialog
                        open={can.create && createUserOpen}
                        onOpenChange={setCreateUserOpen}
                    >
                        <DialogContent className="outline-hidden sm:max-w-xl">
                            <DialogHeader>
                                <DialogTitle>Novo usuário</DialogTitle>
                                <DialogDescription>
                                    Crie uma conta sem sair da lista.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                action={store.url()}
                                method="post"
                                options={{
                                    preserveScroll: true,
                                    preserveState: true,
                                }}
                                transform={(data) => ({
                                    ...data,
                                    redirect_to_index: '1',
                                })}
                                resetOnSuccess
                                className="space-y-6"
                                onSuccess={() => {
                                    setCreateUserOpen(false);
                                    void queryClient.invalidateQueries({
                                        queryKey: ['users'],
                                    });
                                }}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-4">
                                            <Alert variant="info">
                                                <KeyRound />
                                                <AlertTitle>
                                                    Primeiro acesso com senha
                                                    temporária
                                                </AlertTitle>
                                                <AlertDescription>
                                                    A conta será criada com uma
                                                    senha temporária segura.
                                                    Copie após a criação e envie
                                                    ao usuário; ele precisará
                                                    trocá-la no primeiro acesso.
                                                </AlertDescription>
                                            </Alert>

                                            <UserFormFields errors={errors} />
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() =>
                                                    setCreateUserOpen(false)
                                                }
                                            >
                                                <X
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <UserPlus
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {processing
                                                    ? 'Criando...'
                                                    : 'Criar usuário'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>

                    {/* Single delete dialog */}
                    <Dialog
                        open={can.delete && userToDelete?.can?.delete === true}
                        onOpenChange={(open) => !open && setUserToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir usuário</DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja excluir{' '}
                                    <strong>{userToDelete?.name}</strong>? Esta
                                    ação não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setUserToDelete(null)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        userToDelete &&
                                        handleDelete(userToDelete)
                                    }
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* Bulk delete dialog */}
                    <Dialog
                        open={can.delete && bulkDeleteUsers.length > 0}
                        onOpenChange={(open) => {
                            if (!open && !bulkDeletePending) {
                                setBulkDeleteUsers([]);
                                clearSelectionRef.current = null;
                            }
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Excluir {bulkDeleteUsers.length} usuários
                                </DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja excluir{' '}
                                    <strong>{bulkDeleteUsers.length}</strong>{' '}
                                    usuários selecionados? Esta ação não pode
                                    ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            {bulkDeleteError && (
                                <Alert variant="destructive">
                                    <AlertTitle>
                                        Erro ao excluir usuários
                                    </AlertTitle>
                                    <AlertDescription>
                                        {bulkDeleteError}
                                    </AlertDescription>
                                </Alert>
                            )}
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    disabled={bulkDeletePending}
                                    onClick={() => {
                                        setBulkDeleteUsers([]);
                                        clearSelectionRef.current = null;
                                    }}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    disabled={bulkDeletePending}
                                    onClick={() =>
                                        handleBulkDelete(bulkDeleteUsers)
                                    }
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {bulkDeletePending
                                        ? 'Excluindo...'
                                        : `Excluir ${bulkDeleteUsers.length} usuários`}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<User>
                        queryKey="users"
                        tableId="users"
                        endpoint={apiRoutes.users.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(user) => user.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                            selection: true,
                            export: true,
                        }}
                        exportConfig={exportConfig}
                        filters={filters}
                        quickFilters={quickFilters}
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{
                            display: 'inline',
                            label: 'Ações',
                            buttonSize: 'icon',
                            showTooltips: true,
                            sticky: true,
                        }}
                        bulkActions={bulkActions}
                        bulkAggregates={bulkAggregates}
                        selectionResolver={resolveUsersSelection}
                        bulkActionsConfig={{ maxActionCount: 100 }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                            loadMoreThreshold: '150px',
                        }}
                        searchPlaceholder="Buscar"
                        appearance={{
                            size: 'md',
                            gridLines: 'none',
                            rowStyle: 'soft-striped',
                        }}
                    />
                </div>
            </div>
        </>
    );
}
