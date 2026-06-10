import { Head, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Edit, Eye, KeyRound, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import PermissionController, { destroy, edit, index, show } from '@/actions/App/Http/Controllers/PermissionController';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiRoutes } from '@/lib/api/api-routes';
import { formatDate } from '@/lib/utils';
import { httpDelete, httpPost } from '@/lib/http';
import type { Permission } from '@/types/app/permission';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

const columns: ColumnDef<Permission>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        size: 220,
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium">{row.original.name}</span>
                <span className="font-mono text-xs text-muted-foreground">{row.original.slug}</span>
            </div>
        ),
        meta: { label: 'Nome' },
    },
    {
        id: 'group',
        accessorKey: 'group',
        header: 'Grupo',
        size: 140,
        cell: ({ row }) =>
            row.original.group ? (
                <Badge variant="secondary" className="text-xs">
                    {row.original.group}
                </Badge>
            ) : (
                <span className="text-xs text-muted-foreground">—</span>
            ),
        meta: { label: 'Grupo' },
    },
    {
        id: 'description',
        accessorKey: 'description',
        header: 'Descrição',
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">{row.original.description || '—'}</span>
        ),
        meta: { label: 'Descrição' },
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

export default function PermissionsIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Permissões', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [createOpen, setCreateOpen] = useState(false);
    const [permissionToDelete, setPermissionToDelete] = useState<Permission | null>(null);
    const [form, setForm] = useState({ name: '', slug: '', group: '', description: '' });
    const [submitting, setSubmitting] = useState(false);

    const handleCreate = async () => {
        setSubmitting(true);
        try {
            await httpPost(apiRoutes.permissions.store, form);
            toast.success('Permissão criada com sucesso!');
            setCreateOpen(false);
            setForm({ name: '', slug: '', group: '', description: '' });
            void queryClient.invalidateQueries({ queryKey: ['permissions'] });
        } catch {
            toast.error('Erro ao criar permissão.');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (permission: Permission) => {
        try {
            await httpDelete(apiRoutes.permissions.destroy(permission.id));
            toast.success('Permissão removida com sucesso!');
            setPermissionToDelete(null);
            void queryClient.invalidateQueries({ queryKey: ['permissions'] });
        } catch {
            toast.error('Erro ao remover permissão.');
        }
    };

    const actions = useMemo<DataTableAction<Permission>[]>(
        () => [
            {
                label: 'Ver',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (permission) => show.url(permission.id),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Editar',
                icon: <Edit className="size-4" />,
                variant: 'outline',
                href: (permission) => edit.url(permission.id),
                showLabel: false,
                tooltip: 'Editar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (permission) => setPermissionToDelete(permission),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Permissões" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between gap-4">
                        <Heading title="Permissões" description="Gerencie as permissões do sistema" />
                        <Button type="button" onClick={() => setCreateOpen(true)}>
                            <Plus className="size-4" />
                            Nova Permissão
                        </Button>
                    </div>

                    {/* Create dialog */}
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Nova Permissão</DialogTitle>
                                <DialogDescription>Crie uma nova permissão de acesso.</DialogDescription>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <Label>Nome *</Label>
                                    <Input
                                        value={form.name}
                                        onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                        placeholder="Ex: Gerenciar Usuários"
                                        disabled={submitting}
                                    />
                                </div>

                                <div className="space-y-1">
                                    <Label>Grupo</Label>
                                    <Input
                                        value={form.group}
                                        onChange={(e) => setForm((f) => ({ ...f, group: e.target.value }))}
                                        placeholder="Ex: Usuários"
                                        disabled={submitting}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label>Descrição</Label>
                                    <Input
                                        value={form.description}
                                        onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                        placeholder="Descrição da permissão"
                                        disabled={submitting}
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" disabled={submitting} onClick={() => setCreateOpen(false)}>
                                    Cancelar
                                </Button>
                                <Button onClick={handleCreate} disabled={submitting || !form.name }>
                                    {submitting ? 'Criando...' : 'Criar'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    {/* Delete dialog */}
                    <Dialog
                        open={permissionToDelete !== null}
                        onOpenChange={(open) => !open && setPermissionToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir Permissão</DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja excluir a permissão{' '}
                                    <strong>{permissionToDelete?.name}</strong>? Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setPermissionToDelete(null)}>
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => permissionToDelete && handleDelete(permissionToDelete)}
                                >
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<Permission>
                        queryKey="permissions"
                        endpoint={apiRoutes.permissions.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(permission) => permission.id}
                        features={{ search: true, columnVisibility: true, refresh: true }}
                        actions={actions}
                        actionsConfig={{ display: 'inline', label: 'Ações', buttonSize: 'icon', showTooltips: true }}
                        infiniteScrollConfig={{ fitViewport: true, viewportBottomOffset: 96, minHeight: 320 }}
                        searchPlaceholder="Buscar por nome, slug ou grupo..."
                        appearance={{ size: 'md', gridLines: 'none', rowStyle: 'soft-striped' }}
                    />
                </div>
            </div>
        </>
    );
}
