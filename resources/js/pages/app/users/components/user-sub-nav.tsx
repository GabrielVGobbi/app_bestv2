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
    can: { view: boolean; update: boolean; authority: boolean };
}

const navItems: {
    key: UserPage;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    getHref: (uuid: string) => string;
}[] = [
    {
        key: 'show',
        label: 'Detalhes',
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

export function UserSubNav({ userUuid, currentPage, can }: UserSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border border-border bg-muted p-1">
            {navItems
                .filter(({ key }) =>
                    key === 'show'
                        ? can.view
                        : key === 'edit'
                          ? can.update
                          : can.authority,
                )
                .map(({ key, label, icon: Icon, getHref }) => {
                    const isActive = key === currentPage;

                    return (
                        <Link
                            key={key}
                            href={getHref(userUuid)}
                            aria-label={label}
                            title={label}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                isActive
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
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
