import { Head, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Edit, Eye, Plus, Shield, Trash2, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import { toast } from 'sonner';
import {
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/RoleController';
import Header from '@/components/dashboard/header';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { usePrivateState } from '@/contexts/private-state-context';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpDelete, httpPost, toApiError } from '@/lib/http';
import { formatDate } from '@/lib/utils';
import type { Role } from '@/types/app/role';

const columns: ColumnDef<Role>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        size: 120,
        cell: ({ row }) => (
            <div className="flex items-center gap-3">
                <div className="flex size-9 items-center justify-center rounded-full bg-primary-soft text-primary-soft-foreground">
                    <Shield className="size-4" />
                </div>
                <div className="flex flex-col">
                    <span className="font-medium">{row.original.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.original.slug}
                    </span>
                </div>
            </div>
        ),
        meta: { label: 'Nome' },
    },
    {
        id: 'description',
        accessorKey: 'description',
        header: 'Descrição',
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {row.original.description || '-'}
            </span>
        ),
        meta: { label: 'Descrição', truncate: true },
    },
    {
        id: 'permissions_count',
        header: 'Permissões',
        size: 110,
        cell: ({ row }) => (
            <span className="inline-flex items-center gap-1.5 text-sm tabular-nums">
                <Shield className="size-3.5 text-muted-foreground" />
                {row.original.permissions_count ?? 0}
            </span>
        ),
        meta: { label: 'Permissões', align: 'center' },
    },
    {
        id: 'users_count',
        header: 'Usuários',
        size: 100,
        cell: ({ row }) => (
            <span className="inline-flex items-center gap-1.5 text-sm tabular-nums">
                <Users className="size-3.5 text-muted-foreground" />
                {row.original.users_count ?? 0}
            </span>
        ),
        meta: { label: 'Usuários', align: 'center' },
    },
    {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Criado em',
        size: 150,
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: { label: 'Criado em', type: 'date' },
    },
];

export default function RolesIndex() {
    const { scope } = usePrivateState();
    setLayoutProps({
        breadcrumbs: [{ title: 'Papéis', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [createOpen, setCreateOpen] = useState(false);
    const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);
    const [form, setForm] = useState({ name: '', slug: '', description: '' });
    const [submitting, setSubmitting] = useState(false);

    const handleCreate = async () => {
        setSubmitting(true);

        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpPost(apiRoutes.roles.store, form);

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Papel criado com sucesso!');
            setCreateOpen(false);
            setForm({ name: '', slug: '', description: '' });
            void queryClient.invalidateQueries({ queryKey: ['roles'] });
        } catch (failure) {
            if (!scope.isCurrent() || toApiError(failure).type === 'canceled') {
                return;
            }

            toast.error('Erro ao criar papel.');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (role: Role) => {
        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpDelete(apiRoutes.roles.destroy(role.id));

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Papel removido com sucesso!');
            setRoleToDelete(null);
            void queryClient.invalidateQueries({ queryKey: ['roles'] });
        } catch (failure) {
            if (!scope.isCurrent() || toApiError(failure).type === 'canceled') {
                return;
            }

            toast.error('Erro ao remover papel.');
        }
    };

    const actions = useMemo<DataTableAction<Role>[]>(
        () => [
            {
                label: 'Ver',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (role) => show.url(role.id),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Editar',
                icon: <Edit className="size-4" />,
                variant: 'outline',
                href: (role) => edit.url(role.id),
                showLabel: false,
                tooltip: 'Editar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (role) => setRoleToDelete(role),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Papéis" />

            <Header
                title="Papéis"
                subtitle="Gerencie os papéis de acesso do sistema"
            >
                <Button type="button" onClick={() => setCreateOpen(true)}>
                    <Plus className="size-4" />
                    Novo papel
                </Button>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {/* Create dialog */}
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Novo papel</DialogTitle>
                                <DialogDescription>
                                    Crie um novo papel de acesso.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <FormField label="Nome" required>
                                    <Input
                                        id="role-name"
                                        value={form.name}
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                name: e.target.value,
                                            }))
                                        }
                                        placeholder="Ex: Administrador"
                                        disabled={submitting}
                                    />
                                </FormField>

                                <FormField label="Descrição">
                                    <Input
                                        id="role-description"
                                        value={form.description}
                                        onChange={(e) =>
                                            setForm((f) => ({
                                                ...f,
                                                description: e.target.value,
                                            }))
                                        }
                                        placeholder="Descrição do papel"
                                        disabled={submitting}
                                    />
                                </FormField>
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    disabled={submitting}
                                    onClick={() => setCreateOpen(false)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    onClick={handleCreate}
                                    disabled={submitting || !form.name}
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {submitting ? 'Criando...' : 'Criar'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* Delete dialog */}
                    <Dialog
                        open={roleToDelete !== null}
                        onOpenChange={(open) => !open && setRoleToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir papel</DialogTitle>
                                <DialogDescription>
                                    {roleToDelete &&
                                    (roleToDelete.users_count ?? 0) > 0 ? (
                                        <>
                                            O papel{' '}
                                            <strong>
                                                {roleToDelete?.name}
                                            </strong>{' '}
                                            possui {roleToDelete?.users_count}{' '}
                                            usuário(s) vinculado(s). Remova os
                                            usuários antes de excluí-lo.
                                        </>
                                    ) : (
                                        <>
                                            Tem certeza que deseja excluir o
                                            papel{' '}
                                            <strong>
                                                {roleToDelete?.name}
                                            </strong>
                                            ? Esta ação não pode ser desfeita.
                                        </>
                                    )}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setRoleToDelete(null)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    disabled={
                                        (roleToDelete?.users_count ?? 0) > 0
                                    }
                                    onClick={() =>
                                        roleToDelete &&
                                        handleDelete(roleToDelete)
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

                    <DataTable<Role>
                        queryKey="roles"
                        tableId="roles"
                        endpoint={apiRoutes.roles.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(role) => role.id}
                        features={{
                            search: true,
                            columnVisibility: true,
                            refresh: true,
                        }}
                        actions={actions}
                        actionsConfig={{
                            display: 'inline',
                            label: 'Ações',
                            buttonSize: 'icon',
                            showTooltips: true,
                        }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                        }}
                        searchPlaceholder="Buscar por nome ou slug..."
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
