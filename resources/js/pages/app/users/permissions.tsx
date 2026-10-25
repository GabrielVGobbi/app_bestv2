import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Check, KeyRound, RotateCcw, Save, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { toast } from 'sonner';
import {
    index,
    show,
    userPermissions,
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
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpPut } from '@/lib/http';
import type { Permission } from '@/types/app/permission';
import type { UserResource } from '@/types/user';

import { UserSubNav } from './components/user-sub-nav';

interface PermissionsProps {
    userData: UserResource;
    allPermissions: { data: Permission[] };
}

export default function UserPermissions({
    userData,
    allPermissions,
}: PermissionsProps) {
    const user = userData.data;
    const permissions = allPermissions.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuários', href: index.url() },
            { title: user.name, href: show.url(user.uuid) },
            { title: 'Permissões', href: userPermissions.url(user.uuid) },
        ],
    });

    const initialPermissionIds = useMemo(
        () => (user.permissions ?? []).map((p) => p.id),
        [user.permissions],
    );
    const [selectedPermissionIds, setSelectedPermissionIds] =
        useState<number[]>(initialPermissionIds);
    const [isSaving, setIsSaving] = useState(false);

    const hasChanges = useMemo(() => {
        if (selectedPermissionIds.length !== initialPermissionIds.length) {
            return true;
        }

        return selectedPermissionIds.some(
            (id) => !initialPermissionIds.includes(id),
        );
    }, [selectedPermissionIds, initialPermissionIds]);

    const groupedPermissions = useMemo(() => {
        return permissions.reduce<Record<string, Permission[]>>((acc, perm) => {
            const group = perm.group || 'Geral';

            if (!acc[group]) {
                acc[group] = [];
            }

            acc[group].push(perm);

            return acc;
        }, {});
    }, [permissions]);

    const togglePermission = useCallback((id: number) => {
        setSelectedPermissionIds((prev) =>
            prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id],
        );
    }, []);

    const toggleGroup = useCallback(
        (groupPerms: Permission[]) => {
            const groupIds = groupPerms.map((p) => p.id);
            const allSelected = groupIds.every((id) =>
                selectedPermissionIds.includes(id),
            );

            if (allSelected) {
                setSelectedPermissionIds((prev) =>
                    prev.filter((id) => !groupIds.includes(id)),
                );
            } else {
                setSelectedPermissionIds((prev) => [
                    ...new Set([...prev, ...groupIds]),
                ]);
            }
        },
        [selectedPermissionIds],
    );

    const handleSelectAll = useCallback(
        () => setSelectedPermissionIds(permissions.map((p) => p.id)),
        [permissions],
    );
    const handleClearAll = useCallback(() => setSelectedPermissionIds([]), []);
    const handleReset = useCallback(
        () => setSelectedPermissionIds(initialPermissionIds),
        [initialPermissionIds],
    );

    const handleSave = async () => {
        setIsSaving(true);

        try {
            const currentRoleIds = (user.roles ?? []).map((r) => r.id);
            await httpPut(apiRoutes.users.syncRolesPermissions(user.uuid), {
                roles: currentRoleIds,
                permissions: selectedPermissionIds,
            });
            toast.success('Permissões atualizadas com sucesso!');
            router.reload({ only: ['userData'] });
        } catch {
            toast.error('Erro ao atualizar permissões.');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <>
            <Head title={`Permissões - ${user.name}`} />

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
                        <h1 className="page-heading-title">
                            Gerenciar Permissões
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {user.name}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <UserSubNav
                        userUuid={user.uuid}
                        currentPage="permissions"
                    />
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
                <div className="page-section">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <KeyRound className="size-5" />
                                        Permissões Diretas
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Permissões atribuídas diretamente a{' '}
                                        <span className="font-medium text-foreground">
                                            {user.name}
                                        </span>
                                        , independente dos papéis
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {selectedPermissionIds.length} de{' '}
                                        {permissions.length}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleSelectAll}
                                        disabled={
                                            isSaving ||
                                            selectedPermissionIds.length ===
                                                permissions.length
                                        }
                                    >
                                        <Check className="mr-1.5 size-3.5" />
                                        Todas
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleClearAll}
                                        disabled={
                                            isSaving ||
                                            selectedPermissionIds.length === 0
                                        }
                                    >
                                        <X className="mr-1.5 size-3.5" />
                                        Limpar
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {permissions.length > 0 ? (
                                <div className="space-y-8">
                                    {Object.entries(groupedPermissions).map(
                                        ([group, perms]) => {
                                            const groupIds = perms.map(
                                                (p) => p.id,
                                            );
                                            const selectedInGroup =
                                                groupIds.filter((id) =>
                                                    selectedPermissionIds.includes(
                                                        id,
                                                    ),
                                                ).length;
                                            const allChecked =
                                                selectedInGroup ===
                                                perms.length;
                                            const someChecked =
                                                selectedInGroup > 0 &&
                                                !allChecked;

                                            return (
                                                <div key={group}>
                                                    <div className="mb-3 flex items-center gap-3 border-b pb-2.5">
                                                        <Checkbox
                                                            checked={
                                                                allChecked
                                                                    ? true
                                                                    : someChecked
                                                                      ? 'indeterminate'
                                                                      : false
                                                            }
                                                            onCheckedChange={() =>
                                                                toggleGroup(
                                                                    perms,
                                                                )
                                                            }
                                                            disabled={isSaving}
                                                        />
                                                        <Label className="cursor-pointer text-sm font-semibold">
                                                            {group}
                                                        </Label>
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            {selectedInGroup}/
                                                            {perms.length}
                                                        </Badge>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-2 pl-2 sm:grid-cols-2">
                                                        {perms.map((perm) => {
                                                            const isSelected =
                                                                selectedPermissionIds.includes(
                                                                    perm.id,
                                                                );

                                                            return (
                                                                <label
                                                                    key={
                                                                        perm.id
                                                                    }
                                                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all ${
                                                                        isSelected
                                                                            ? 'border-primary/40 bg-primary/5'
                                                                            : 'border-transparent hover:bg-muted/50'
                                                                    } ${isSaving ? 'pointer-events-none opacity-60' : ''}`}
                                                                >
                                                                    <Checkbox
                                                                        checked={
                                                                            isSelected
                                                                        }
                                                                        onCheckedChange={() =>
                                                                            togglePermission(
                                                                                perm.id,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            isSaving
                                                                        }
                                                                        className="mt-0.5"
                                                                    />
                                                                    <div className="flex flex-col gap-0.5">
                                                                        <span className="text-sm leading-tight font-medium">
                                                                            {
                                                                                perm.name
                                                                            }
                                                                        </span>
                                                                        <span className="font-mono text-xs text-muted-foreground">
                                                                            {
                                                                                perm.slug
                                                                            }
                                                                        </span>
                                                                        {perm.description && (
                                                                            <span className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                                                                {
                                                                                    perm.description
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed p-8 text-center">
                                    <KeyRound className="mx-auto mb-2 size-8 text-muted-foreground/50" />
                                    <p className="text-sm text-muted-foreground">
                                        Nenhuma permissão cadastrada.
                                    </p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        asChild
                                        className="mt-2"
                                    >
                                        <Link href="/permissions">
                                            Criar permissões
                                        </Link>
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
