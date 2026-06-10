import { Link } from '@inertiajs/react';
import { Eye, KeyRound, Pencil } from 'lucide-react';

import { cn } from '@/lib/utils';
import { show, edit } from '@/routes/roles';

type RolePage = 'show' | 'edit' | 'permissions';

interface RoleSubNavProps {
    roleId: number;
    currentPage: RolePage;
}

const navItems: {
    key: RolePage;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    getHref: (id: number) => string;
}[] = [
    { key: 'show', label: 'Visão Geral', icon: Eye, getHref: (id) => show.url(id) },
    { key: 'edit', label: 'Editar', icon: Pencil, getHref: (id) => edit.url(id) },
    { key: 'permissions', label: 'Permissões', icon: KeyRound, getHref: (id) => `/roles/${id}/permissions` },
];

export function RoleSubNav({ roleId, currentPage }: RoleSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                const isActive = key === currentPage;
                return (
                    <Link
                        key={key}
                        href={getHref(roleId)}
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
