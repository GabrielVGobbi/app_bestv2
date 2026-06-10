import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, KeyRound, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { destroy, edit, index, show } from '@/routes/permissions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpDelete } from '@/lib/http';
import type { Permission } from '@/types/app/permission';
import { toast } from 'sonner';

interface ShowProps {
    permissionData: { data: Permission };
}

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) {
        return '-';
    }
    return new Date(dateString).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

function InfoField({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="text-sm">{value || '-'}</p>
        </div>
    );
}

export default function ShowPermission({ permissionData }: ShowProps) {
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
            await httpDelete(apiRoutes.permissions.destroy(permission.id));
            toast.success('Permissão removida com sucesso!');
            router.visit(index.url());
        } catch {
            toast.error('Erro ao remover permissão.');
            setDeleting(false);
        }
    };

    return (
        <>
            <Head title={permission.name} />

            <div className="container mx-auto max-w-3xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="icon" className="size-8" asChild>
                                <Link href={index.url()}>
                                    <ArrowLeft className="size-4" />
                                </Link>
                            </Button>
                            <h1 className="text-xl font-semibold">Detalhes da Permissão</h1>
                        </div>
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
                    </div>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10">
                                    <KeyRound className="size-6 text-primary" />
                                </div>
                                <div>
                                    <h2 className="font-bold">{permission.name}</h2>
                                    <p className="font-mono text-sm text-muted-foreground">{permission.slug}</p>
                                </div>
                                {permission.group && (
                                    <Badge variant="secondary" className="ml-auto">
                                        {permission.group}
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InfoField label="Nome" value={permission.name} />
                                <InfoField label="Slug" value={permission.slug} />
                                <InfoField label="Grupo" value={permission.group} />
                                <div className="sm:col-span-2">
                                    <InfoField label="Descrição" value={permission.description} />
                                </div>
                            </div>
                            <Separator />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InfoField label="Criado em" value={formatDate(permission.created_at)} />
                                <InfoField label="Atualizado em" value={formatDate(permission.updated_at)} />
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
                            Tem certeza que deseja excluir a permissão <strong>{permission.name}</strong>? Esta ação não
                            pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteOpen(false)}>
                            Cancelar
                        </Button>
                        <Button variant="destructive" disabled={deleting} onClick={handleDelete}>
                            {deleting ? 'Excluindo...' : 'Excluir'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
