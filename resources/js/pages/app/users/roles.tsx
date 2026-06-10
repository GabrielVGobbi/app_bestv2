import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Check, RotateCcw, Save, Shield, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { toast } from 'sonner';
import {
    index,
    roles as userRoles,
    show,
} from '@/actions/App/Http/Controllers/UserController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpPut } from '@/lib/http';
import type { Role } from '@/types/app/role';
import type { UserResource } from '@/types/user';

import { UserSubNav } from './components/user-sub-nav';

interface RolesProps {
    userData: UserResource;
    allRoles: { data: Role[] };
}

export default function UserRoles({ userData, allRoles }: RolesProps) {
    const user = userData.data;
    const roles = allRoles.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuários', href: index.url() },
            { title: user.name, href: show.url(user.uuid) },
            { title: 'Papéis', href: userRoles.url(user.uuid) },
        ],
    });

    const initialRoleIds = useMemo(
        () => (user.roles ?? []).map((r) => r.id),
        [user.roles],
    );
    const [selectedRoleIds, setSelectedRoleIds] =
        useState<number[]>(initialRoleIds);
    const [isSaving, setIsSaving] = useState(false);

    const hasChanges = useMemo(() => {
        if (selectedRoleIds.length !== initialRoleIds.length) {
            return true;
        }

        return selectedRoleIds.some((id) => !initialRoleIds.includes(id));
    }, [selectedRoleIds, initialRoleIds]);

    const toggleRole = useCallback((id: number) => {
        setSelectedRoleIds((prev) =>
            prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id],
        );
    }, []);

    const handleSelectAll = useCallback(
        () => setSelectedRoleIds(roles.map((r) => r.id)),
        [roles],
    );
    const handleClearAll = useCallback(() => setSelectedRoleIds([]), []);
    const handleReset = useCallback(
        () => setSelectedRoleIds(initialRoleIds),
        [initialRoleIds],
    );

    const handleSave = async () => {
        setIsSaving(true);

        try {
            const currentPermissionIds = (user.permissions ?? []).map(
                (p) => p.id,
            );
            await httpPut(apiRoutes.users.syncRolesPermissions(user.uuid), {
                roles: selectedRoleIds,
                permissions: currentPermissionIds,
            });
            toast.success('Papéis atualizados com sucesso!');
            router.reload({ only: ['userData'] });
        } catch {
            toast.error('Erro ao atualizar papéis.');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <>
            <Head title={`Papéis - ${user.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(user.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Gerenciar Papéis
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {user.name}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <UserSubNav userUuid={user.uuid} currentPage="roles" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show.url(user.uuid)}>
                            <X className="mr-1.5 size-4" />
                            Fechar
                        </Link>
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Shield className="size-5" />
                                        Papéis do Usuário
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Selecione os papéis para{' '}
                                        <span className="font-medium text-foreground">
                                            {user.name}
                                        </span>
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {selectedRoleIds.length} de{' '}
                                        {roles.length}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleSelectAll}
                                        disabled={
                                            isSaving ||
                                            selectedRoleIds.length ===
                                                roles.length
                                        }
                                    >
                                        <Check className="mr-1.5 size-3.5" />
                                        Todos
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleClearAll}
                                        disabled={
                                            isSaving ||
                                            selectedRoleIds.length === 0
                                        }
                                    >
                                        <X className="mr-1.5 size-3.5" />
                                        Limpar
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {roles.length > 0 ? (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {roles.map((role) => {
                                        const isSelected =
                                            selectedRoleIds.includes(role.id);

                                        return (
                                            <label
                                                key={role.id}
                                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-all ${
                                                    isSelected
                                                        ? 'border-primary/50 bg-primary/5 shadow-sm'
                                                        : 'border-border hover:border-muted-foreground/30 hover:bg-muted/50'
                                                } ${isSaving ? 'pointer-events-none opacity-60' : ''}`}
                                            >
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={() =>
                                                        toggleRole(role.id)
                                                    }
                                                    disabled={isSaving}
                                                    className="mt-0.5"
                                                />
                                                <div className="flex flex-1 flex-col gap-1">
                                                    <div className="flex items-center gap-2">
                                                        <Shield
                                                            className={`size-4 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`}
                                                        />
                                                        <span className="text-sm leading-tight font-medium">
                                                            {role.name}
                                                        </span>
                                                    </div>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {role.slug}
                                                    </span>
                                                    {role.description && (
                                                        <span className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                                            {role.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed p-8 text-center">
                                    <Shield className="mx-auto mb-2 size-8 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum papel cadastrado.
                                    </p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        asChild
                                        className="mt-2"
                                    >
                                        <Link href="/roles">Criar papéis</Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="sticky bottom-0 -mx-6 flex items-center gap-3 bg-background/80 pt-4 pb-2 backdrop-blur-sm">
                        <CardContent>
                            <div className="flex gap-2">
                                {hasChanges && (
                                    <Button
                                        variant="outline"
                                        onClick={handleReset}
                                        disabled={isSaving}
                                    >
                                        <RotateCcw />
                                        Desfazer
                                    </Button>
                                )}
                                <Button
                                    onClick={handleSave}
                                    disabled={isSaving || !hasChanges}
                                >
                                    <Save />
                                    {isSaving ? 'Salvando...' : 'Salvar'}
                                </Button>
                            </div>
                        </CardContent>
                    </div>
                </div>
            </div>
        </>
    );
}
