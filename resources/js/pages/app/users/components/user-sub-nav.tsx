import { Link } from '@inertiajs/react';
import { Eye, KeyRound, Pencil, Shield } from 'lucide-react';

import {
    edit,
    roles,
    show,
    userPermissions,
} from '@/actions/App/Http/Controllers/UserController';
import { cn } from '@/lib/utils';

type UserPage = 'show' | 'edit' | 'roles' | 'permissions';

interface UserSubNavProps {
    userUuid: string;
    currentPage: UserPage;
}

const navItems: {
    key: UserPage;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    getHref: (uuid: string) => string;
}[] = [
    {
        key: 'show',
        label: 'Visão Geral',
        icon: Eye,
        getHref: (uuid) => show.url(uuid),
    },
    {
        key: 'edit',
        label: 'Editar',
        icon: Pencil,
        getHref: (uuid) => edit.url(uuid),
    },
    {
        key: 'roles',
        label: 'Papéis',
        icon: Shield,
        getHref: (uuid) => roles.url(uuid),
    },
    {
        key: 'permissions',
        label: 'Permissões',
        icon: KeyRound,
        getHref: (uuid) => userPermissions.url(uuid),
    },
];

export function UserSubNav({ userUuid, currentPage }: UserSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref(userUuid)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
                        )}
                    >
                        <Icon className="size-3.5" />
                        <span className="hidden sm:inline">{label}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
