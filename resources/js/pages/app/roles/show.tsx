import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { KeyRound, Shield, Trash2, Users, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Header from '@/components/dashboard/header';
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
import { usePrivateState } from '@/contexts/private-state-context';

import { apiRoutes } from '@/lib/api/api-routes';
import { httpDelete, toApiError } from '@/lib/http';
import { index, show } from '@/routes/roles';
import type { Permission } from '@/types/app/permission';
import type { Role } from '@/types/app/role';

import { RoleSubNav } from './components/role-sub-nav';

interface ShowProps {
    roleData: { data: Role };
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

export default function ShowRole({ roleData }: ShowProps) {
    const { scope } = usePrivateState();
    const role = roleData.data;
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Papéis', href: index.url() },
            { title: role.name, href: show.url(role.id) },
        ],
    });

    const rolePermissions = role.permissions ?? [];
    const groupedPermissions = rolePermissions.reduce<
        Record<string, Permission[]>
    >((acc, perm) => {
        const group = perm.group || 'Geral';

        if (!acc[group]) {
            acc[group] = [];
        }

        acc[group].push(perm);

        return acc;
    }, {});

    const hasUsers = (role.users_count ?? 0) > 0;

    const handleDelete = async () => {
        setDeleting(true);

        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpDelete(apiRoutes.roles.destroy(role.id));

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Papel removido com sucesso!');
            router.visit(index.url());
        } catch (failure) {
            if (!scope.isCurrent() || toApiError(failure).type === 'canceled') {
                return;
            }

            toast.error('Erro ao remover papel.');
            setDeleting(false);
        }
    };

    return (
        <>
            <Head title={role.name} />

            <Header title={role.name} backHref={index.url()}>
                <div className="flex items-center gap-2">
                    <RoleSubNav roleId={role.id} currentPage="show" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <Button
                        variant="outline"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleteOpen(true)}
                    >
                        <Trash2 className="mr-2 size-4" />
                        Excluir
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-6 md:flex-row">
                        {/* Sidebar */}
                        <aside className="w-full shrink-0 space-y-6 md:w-72">
                            <Card>
                                <CardContent className="flex flex-col gap-5 p-6">
                                    <div className="flex flex-col items-center">
                                        <div className="mb-3 flex size-20 items-center justify-center rounded-2xl bg-primary-soft text-primary-soft-foreground">
                                            <Shield className="size-10" />
                                        </div>
                                        <h2 className="w-full text-center text-lg font-bold [word-break:break-word]">
                                            {role.name}
                                        </h2>
                                        <p className="text-center font-mono text-sm text-muted-foreground">
                                            {role.slug}
                                        </p>
                                        {role.description && (
                                            <p className="mt-1 text-center text-sm text-muted-foreground">
                                                {role.description}
                                            </p>
                                        )}
                                    </div>
                                    <Separator />
                                    <div className="flex flex-col gap-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <KeyRound className="size-4 shrink-0" />
                                                <span>Permissões</span>
                                            </div>
                                            <Badge variant="secondary">
                                                {rolePermissions.length}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Users className="size-4 shrink-0" />
                                                <span>Usuários</span>
                                            </div>
                                            <Badge
                                                variant={
                                                    hasUsers
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {role.users_count ?? 0}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Separator />
                                    <div className="space-y-1 text-xs text-muted-foreground">
                                        <p>
                                            Criado em{' '}
                                            {formatDate(role.created_at)}
                                        </p>
                                        <p>
                                            Atualizado em{' '}
                                            {formatDate(role.updated_at)}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>

                        {/* Main */}
                        <main className="min-w-0 flex-1 space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informações</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <InfoField label="Nome" value={role.name} />
                                    <InfoField label="Slug" value={role.slug} />
                                    <div className="sm:col-span-2">
                                        <InfoField
                                            label="Descrição"
                                            value={role.description}
                                        />
                                    </div>
                                    <InfoField
                                        label="Criado em"
                                        value={formatDate(role.created_at)}
                                    />
                                    <InfoField
                                        label="Atualizado em"
                                        value={formatDate(role.updated_at)}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <KeyRound className="size-5" />
                                            Permissões Atribuídas
                                        </CardTitle>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/roles/${role.id}/permissions`}
                                            >
                                                <KeyRound className="mr-2 size-4" />
                                                Gerenciar
                                            </Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {rolePermissions.length > 0 ? (
                                        <div className="space-y-6">
                                            {Object.entries(
                                                groupedPermissions,
                                            ).map(([group, perms]) => (
                                                <div key={group}>
                                                    <div className="mb-3 flex items-center gap-2 border-b pb-2">
                                                        <span className="text-sm font-semibold">
                                                            {group}
                                                        </span>
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            {perms.length}
                                                        </Badge>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                        {perms.map((perm) => (
                                                            <div
                                                                key={perm.id}
                                                                className="flex items-start gap-2 rounded-md bg-muted/50 p-2.5"
                                                            >
                                                                <KeyRound className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                                                <div className="flex flex-col">
                                                                    <span className="text-sm leading-tight font-medium">
                                                                        {
                                                                            perm.name
                                                                        }
                                                                    </span>
                                                                    {perm.description && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {
                                                                                perm.description
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-dashed p-8 text-center">
                                            <KeyRound className="mx-auto mb-2 size-8 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">
                                                Nenhuma permissão atribuída.
                                            </p>
                                            <Button
                                                variant="link"
                                                size="sm"
                                                asChild
                                                className="mt-2"
                                            >
                                                <Link
                                                    href={`/roles/${role.id}/permissions`}
                                                >
                                                    <KeyRound
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Atribuir permissões
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </main>
                    </div>
                </div>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Excluir papel</DialogTitle>
                        <DialogDescription>
                            {hasUsers ? (
                                <>
                                    O papel <strong>{role.name}</strong> possui{' '}
                                    {role.users_count} usuário(s) vinculado(s).
                                    Remova os usuários antes de excluí-lo.
                                </>
                            ) : (
                                <>
                                    Tem certeza que deseja excluir o papel{' '}
                                    <strong>{role.name}</strong>? Esta ação não
                                    pode ser desfeita.
                                </>
                            )}
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
                            disabled={hasUsers || deleting}
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
