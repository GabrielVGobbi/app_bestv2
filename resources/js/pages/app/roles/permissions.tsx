import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Check, KeyRound, RotateCcw, Save, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
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
import { usePrivateState } from '@/contexts/private-state-context';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpPut, toApiError } from '@/lib/http';
import { index as indexRoute, show as showRoute } from '@/routes/roles';
import type { Permission } from '@/types/app/permission';
import type { Role } from '@/types/app/role';

import { RoleSubNav } from './components/role-sub-nav';

interface PermissionsProps {
    roleData: { data: Role };
    allPermissions: { data: Permission[] };
}

export default function RolePermissions({
    roleData,
    allPermissions,
}: PermissionsProps) {
    const { scope } = usePrivateState();
    const role = roleData.data;
    const permissions = allPermissions.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Papéis', href: indexRoute.url() },
            { title: role.name, href: showRoute.url(role.id) },
            { title: 'Permissões', href: `/roles/${role.id}/permissions` },
        ],
    });

    const initialPermissionIds = useMemo(
        () => (role.permissions ?? []).map((p) => p.id),
        [role.permissions],
    );
    const [selectedIds, setSelectedIds] =
        useState<number[]>(initialPermissionIds);
    const [isSaving, setIsSaving] = useState(false);

    const hasChanges = useMemo(() => {
        if (selectedIds.length !== initialPermissionIds.length) {
            return true;
        }

        return selectedIds.some((id) => !initialPermissionIds.includes(id));
    }, [selectedIds, initialPermissionIds]);

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
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id],
        );
    }, []);

    const toggleGroup = useCallback(
        (groupPerms: Permission[]) => {
            const groupIds = groupPerms.map((p) => p.id);
            const allSelected = groupIds.every((id) =>
                selectedIds.includes(id),
            );

            if (allSelected) {
                setSelectedIds((prev) =>
                    prev.filter((id) => !groupIds.includes(id)),
                );
            } else {
                setSelectedIds((prev) => [...new Set([...prev, ...groupIds])]);
            }
        },
        [selectedIds],
    );

    const handleSelectAll = useCallback(
        () => setSelectedIds(permissions.map((p) => p.id)),
        [permissions],
    );
    const handleClearAll = useCallback(() => setSelectedIds([]), []);
    const handleReset = useCallback(
        () => setSelectedIds(initialPermissionIds),
        [initialPermissionIds],
    );

    const handleSave = async () => {
        setIsSaving(true);

        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpPut(apiRoutes.roles.syncPermissions(role.id), {
                permissions: selectedIds,
            });

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Permissões atualizadas com sucesso!');
            router.reload({ only: ['roleData'] });
        } catch (failure) {
            if (!scope.isCurrent() || toApiError(failure).type === 'canceled') {
                return;
            }

            toast.error('Erro ao atualizar permissões.');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <>
            <Head title={`Permissões - ${role.name}`} />

            <Header
                title="Gerenciar Permissões"
                subtitle={role.name}
                backHref={showRoute.url(role.id)}
            >
                <div className="flex items-center gap-2">
                    <RoleSubNav roleId={role.id} currentPage="permissions" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    {hasChanges && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            disabled={isSaving}
                        >
                            <RotateCcw className="mr-2 size-4" />
                            Desfazer
                        </Button>
                    )}
                    <Button
                        size="sm"
                        onClick={handleSave}
                        disabled={isSaving || !hasChanges}
                    >
                        <Save className="mr-2 size-4" />
                        {isSaving ? 'Salvando...' : 'Salvar'}
                    </Button>
                </div>
            </Header>

            <div className="container mx-auto max-w-4xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <KeyRound className="size-5" />
                                        Permissões do Papel
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Selecione as permissões para o papel{' '}
                                        <span className="font-medium text-foreground">
                                            {role.name}
                                        </span>
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {selectedIds.length} de{' '}
                                        {permissions.length}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleSelectAll}
                                        disabled={
                                            isSaving ||
                                            selectedIds.length ===
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
                                            isSaving || selectedIds.length === 0
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
                                                    selectedIds.includes(id),
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
                                                                selectedIds.includes(
                                                                    perm.id,
                                                                );

                                                            return (
                                                                <label
                                                                    key={
                                                                        perm.id
                                                                    }
                                                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all ${
                                                                        isSelected
                                                                            ? 'border-primary/40 bg-primary-soft/40'
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
                                        Nenhuma permissão cadastrada no sistema.
                                    </p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        asChild
                                        className="mt-2"
                                    >
                                        <Link href="/permissions">
                                            <KeyRound
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Criar permissões
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
