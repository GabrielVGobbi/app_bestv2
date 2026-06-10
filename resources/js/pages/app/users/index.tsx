import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import type { InfiniteData } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Edit, Eye, Plus, ShieldCheck, Trash2, UserCheck } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';

import {
    destroy,
    edit,
    index,
    show,
    store,
} from '@/actions/App/Http/Controllers/UserController';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
    DataTableQuickFilter,
} from '@/components/data-table';
import Heading from '@/components/heading';
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
import { apiRoutes } from '@/lib/api/api-routes';
import { formatDate } from '@/lib/utils';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';
import type { Paginated } from '@/types/pagination';
import type { User } from '@/types/user';

// Defined outside the component — no state/props dependencies, never recreated.
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
        header: 'Email',
        meta: { label: 'Email', truncate: true }
    },
    {
        id: 'email_verified_at',
        accessorKey: 'email_verified_at',
        header: 'Verificado',
        cell: ({ row }) =>
            row.original.email_verified_at ? (
                <Badge variant="secondary">Sim</Badge>
            ) : (
                <Badge variant="outline">Não</Badge>
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
        label: 'Email',
        type: 'text',
        operator: 'contains',
        placeholder: 'Buscar por email',
    },
];

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Data de Criação' },
    { value: 'updated_at', label: 'Última Atualização' },
];

const quickFilters: DataTableQuickFilter[] = [
    {
        id: 'email_verified_at',
        label: 'Verificação',
        placeholder: 'Verificação',
        icon: UserCheck,
        options: [
            { label: 'Verificados', value: true },
            { label: 'Não verificados', value: false },
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

export default function Index() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Users', href: index.url() }],
    });

    const [createUserOpen, setCreateUserOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<User | null>(null);
    const [bulkDeleteUsers, setBulkDeleteUsers] = useState<User[]>([]);

    // Ref instead of state — storing a function reference doesn't need re-renders.
    const clearSelectionRef = useRef<(() => void) | null>(null);

    const queryClient = useQueryClient();

    const handleDelete = useCallback(
        (user: User) => {
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
        [queryClient],
    );

    const handleBulkDelete = useCallback(
        (users: User[]) => {
            const uuids = new Set(users.map((u) => u.uuid));

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
                            data: page.data.filter((u) => !uuids.has(u.uuid)),
                        })),
                    };
                },
            );

            setBulkDeleteUsers([]);
            clearSelectionRef.current?.();
            clearSelectionRef.current = null;

            users.forEach((user) =>
                router.delete(destroy.url(user.uuid), { preserveScroll: true }),
            );
        },
        [queryClient],
    );

    const actions = useMemo<DataTableAction<User>[]>(
        () => [
            {
                label: 'View',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (user) => show.url(user.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Edit',
                icon: <Edit className="size-4" />,
                variant: 'outline',
                href: (user) => edit.url(user.uuid),
                showLabel: false,
                tooltip: 'Editar',
            },
            {
                label: 'Delete',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (user) => setUserToDelete(user),
            },
        ],
        [],
    );

    const bulkActions = useMemo<DataTableBulkAction<User>[]>(
        () => [
            {
                id: 'bulk-delete',
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                onClick: (rows, clearSelection) => {
                    clearSelectionRef.current = clearSelection;
                    setBulkDeleteUsers(rows);
                },
            },
            {
                id: 'bulk-verify',
                label: 'Marcar verificado',
                icon: <ShieldCheck className="size-4" />,
                disabled: (rows) =>
                    rows.every((u) => u.email_verified_at !== null),
                onClick: (_rows, clearSelection) => {
                    // TODO: chamar endpoint bulk verify
                    clearSelection();
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Users" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between gap-4">
                        <Heading
                            title="Users"
                            description="Manage user accounts"
                        />
                        <Button
                            type="button"
                            onClick={() => setCreateUserOpen(true)}
                        >
                            <Plus className="size-4" />
                            New User
                        </Button>
                    </div>

                    <Dialog
                        open={createUserOpen}
                        onOpenChange={setCreateUserOpen}
                    >
                        <DialogContent className="sm:max-w-xl">
                            <DialogHeader>
                                <DialogTitle>New User</DialogTitle>
                                <DialogDescription>
                                    Create a user account without leaving the
                                    list.
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
                                            <Alert>
                                                <AlertTitle>
                                                    First access with temporary
                                                    password
                                                </AlertTitle>
                                                <AlertDescription>
                                                    The account will be created
                                                    with a secure temporary
                                                    password. Copy it after
                                                    creation and send it to the
                                                    user. They will be required
                                                    to change it after signing
                                                    in.
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
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Creating...'
                                                    : 'Create user'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>

                    {/* Single delete dialog */}
                    <Dialog
                        open={userToDelete !== null}
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
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        userToDelete &&
                                        handleDelete(userToDelete)
                                    }
                                >
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* Bulk delete dialog */}
                    <Dialog
                        open={bulkDeleteUsers.length > 0}
                        onOpenChange={(open) => {
                            if (!open) {
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
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setBulkDeleteUsers([]);
                                        clearSelectionRef.current = null;
                                    }}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        handleBulkDelete(bulkDeleteUsers)
                                    }
                                >
                                    Excluir {bulkDeleteUsers.length} usuários
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<User>
                        queryKey="users"
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
