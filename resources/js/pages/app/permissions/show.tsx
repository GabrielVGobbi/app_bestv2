import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { KeyRound, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import { toast } from 'sonner';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { usePrivateState } from '@/contexts/private-state-context';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpDelete, toApiError } from '@/lib/http';
import { edit, index, show } from '@/routes/permissions';
import type { Permission } from '@/types/app/permission';

interface ShowProps {
    permissionData: { data: Permission };
}

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function InfoField({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="text-sm">{value || '-'}</p>
        </div>
    );
}

export default function ShowPermission({ permissionData }: ShowProps) {
    const { scope } = usePrivateState();
    const permission = permissionData.data;
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Permissões', href: index.url() },
            { title: permission.name, href: show.url(permission.id) },
        ],
    });

    const handleDelete = async () => {
        setDeleting(true);

        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpDelete(apiRoutes.permissions.destroy(permission.id));

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Permissão removida com sucesso!');
            router.visit(index.url());
        } catch (failure) {
            if (!scope.isCurrent() || toApiError(failure).type === 'canceled') {
                return;
            }

            toast.error('Erro ao remover permissão.');
            setDeleting(false);
        }
    };

    return (
        <>
            <Head title={permission.name} />

            <Header title={permission.name} backHref={index.url()}>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleteOpen(true)}
                    >
                        <Trash2 className="mr-2 size-4" />
                        Excluir
                    </Button>
                    <Button size="sm" asChild>
                        <Link href={edit.url(permission.id)}>
                            <Pencil className="mr-2 size-4" />
                            Editar
                        </Link>
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground">
                                    <KeyRound className="size-6" />
                                </div>
                                <div>
                                    <h2 className="font-bold">
                                        {permission.name}
                                    </h2>
                                    <p className="font-mono text-sm text-muted-foreground">
                                        {permission.slug}
                                    </p>
                                </div>
                                {permission.group && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-auto"
                                    >
                                        {permission.group}
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InfoField
                                    label="Nome"
                                    value={permission.name}
                                />
                                <InfoField
                                    label="Slug"
                                    value={permission.slug}
                                />
                                <InfoField
                                    label="Grupo"
                                    value={permission.group}
                                />
                                <div className="sm:col-span-2">
                                    <InfoField
                                        label="Descrição"
                                        value={permission.description}
                                    />
                                </div>
                            </div>
                            <Separator />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InfoField
                                    label="Criado em"
                                    value={formatDate(permission.created_at)}
                                />
                                <InfoField
                                    label="Atualizado em"
                                    value={formatDate(permission.updated_at)}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Excluir Permissão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir a permissão{' '}
                            <strong>{permission.name}</strong>? Esta ação não
                            pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={handleDelete}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            {deleting ? 'Excluindo...' : 'Excluir'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
