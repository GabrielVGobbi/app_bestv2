import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    KeyRound,
    Mail,
    Shield,
    ShieldCheck,
} from 'lucide-react';

import {
    destroy,
    index,
    show,
} from '@/actions/App/Http/Controllers/UserController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { UserResource } from '@/types/user';

import { UserSubNav } from './components/user-sub-nav';

interface Props {
    userData: UserResource;
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
            <p className="mt-0.5 text-sm">{value || '-'}</p>
        </div>
    );
}

export default function Show({ userData }: Props) {
    const user = userData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuários', href: index.url() },
            { title: user.name, href: show.url(user.uuid) },
        ],
    });

    const initials = user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    const userRoles = user.roles ?? [];
    const userPermissions = user.permissions ?? [];

    const groupedPermissions = userPermissions.reduce<
        Record<string, typeof userPermissions>
    >((acc, perm) => {
        const group = perm.group || 'Geral';

        if (!acc[group]) {
            acc[group] = [];
        }

        acc[group].push(perm);

        return acc;
    }, {});

    return (
        <>
            <Head title={user.name} />

            {/* Header */}
            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{user.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <UserSubNav userUuid={user.uuid} currentPage="show" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url(user.uuid)}
                        resourceName={user.name}
                        resourceType="usuário"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="page-section">
                    <div className="grid gap-6 md:grid-cols-3">
                        {/* Sidebar */}
                        <aside className="space-y-4 md:col-span-1">
                            <Card>
                                <CardContent className="flex flex-col items-center gap-4 p-6">
                                    <div className="flex size-20 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary">
                                        {initials}
                                    </div>
                                    <div className="text-center">
                                        <h2 className="text-lg font-semibold">
                                            {user.name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap justify-center gap-1.5">
                                        {user.email_verified_at ? (
                                            <Badge
                                                variant="secondary"
                                                className="gap-1"
                                            >
                                                <ShieldCheck className="size-3" />
                                                Verificado
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive">
                                                Não verificado
                                            </Badge>
                                        )}
                                    </div>

                                    {userRoles.length > 0 && (
                                        <>
                                            <Separator className="w-full" />
                                            <div className="w-full space-y-2">
                                                <p className="text-xs font-medium text-muted-foreground">
                                                    Papéis
                                                </p>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {userRoles.map((role) => (
                                                        <Badge
                                                            key={role.id}
                                                            variant="outline"
                                                            className="gap-1"
                                                        >
                                                            <Shield className="size-3" />
                                                            {role.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    <Separator className="w-full" />
                                    <div className="w-full space-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-2">
                                            <CalendarDays className="size-3.5" />
                                            <span>
                                                Criado{' '}
                                                {formatDate(user.created_at)}
                                            </span>
                                        </div>
                                        {user.email_verified_at && (
                                            <div className="flex items-center gap-2">
                                                <Mail className="size-3.5" />
                                                <span>
                                                    E-mail verificado{' '}
                                                    {formatDate(
                                                        user.email_verified_at,
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>

                        {/* Main content */}
                        <main className="space-y-6 md:col-span-2">
                            {/* Account info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informações da Conta</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <InfoField label="Nome" value={user.name} />
                                    <InfoField
                                        label="E-mail"
                                        value={user.email}
                                    />
                                    <InfoField
                                        label="Status do E-mail"
                                        value={
                                            user.email_verified_at
                                                ? 'Verificado'
                                                : 'Não verificado'
                                        }
                                    />
                                    <InfoField
                                        label="Deve alterar senha"
                                        value={
                                            user.must_change_password
                                                ? 'Sim'
                                                : 'Não'
                                        }
                                    />
                                    <InfoField
                                        label="Criado em"
                                        value={formatDate(user.created_at)}
                                    />
                                    <InfoField
                                        label="Atualizado em"
                                        value={formatDate(user.updated_at)}
                                    />
                                </CardContent>
                            </Card>

                            {/* Roles */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Shield className="size-5" />
                                            Papéis Atribuídos
                                        </CardTitle>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/users/${user.uuid}/roles`}
                                            >
                                                <Shield className="mr-1.5 size-4" />
                                                Gerenciar
                                            </Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {userRoles.length > 0 ? (
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            {userRoles.map((role) => (
                                                <div
                                                    key={role.id}
                                                    className="flex flex-col gap-1 rounded-lg border border-primary/20 bg-primary/5 p-3"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Shield className="size-4 text-primary" />
                                                        <span className="text-sm font-medium">
                                                            {role.name}
                                                        </span>
                                                    </div>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {role.slug}
                                                    </span>
                                                    {role.description && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {role.description}
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-dashed p-6 text-center">
                                            <Shield className="mx-auto mb-2 size-7 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">
                                                Nenhum papel atribuído.
                                            </p>
                                            <Button
                                                variant="link"
                                                size="sm"
                                                asChild
                                                className="mt-1"
                                            >
                                                <Link
                                                    href={`/users/${user.uuid}/roles`}
                                                >
                                                    Atribuir papéis
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Direct Permissions */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <KeyRound className="size-5" />
                                            Permissões Diretas
                                        </CardTitle>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/users/${user.uuid}/permissions`}
                                            >
                                                <KeyRound className="mr-1.5 size-4" />
                                                Gerenciar
                                            </Link>
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {userPermissions.length > 0 ? (
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
                                        <div className="rounded-lg border border-dashed p-6 text-center">
                                            <KeyRound className="mx-auto mb-2 size-7 text-muted-foreground/50" />
                                            <p className="text-sm text-muted-foreground">
                                                Nenhuma permissão direta
                                                atribuída.
                                            </p>
                                            <Button
                                                variant="link"
                                                size="sm"
                                                asChild
                                                className="mt-1"
                                            >
                                                <Link
                                                    href={`/users/${user.uuid}/permissions`}
                                                >
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
        </>
    );
}
