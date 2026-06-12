import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import { cn } from '@/lib/utils';

type CompanyPage = 'show' | 'edit';

interface CompanySubNavProps {
    companyUuid: string;
    currentPage: CompanyPage;
    canManage?: boolean;
}

const navItems: {
    key: CompanyPage;
    label: string;
    icon: ComponentType<{ className?: string }>;
    getHref: (uuid: string) => string;
}[] = [
    {
        key: 'show',
        label: 'Visão geral',
        icon: Eye,
        getHref: (uuid) => show.url(uuid),
    },
    {
        key: 'edit',
        label: 'Editar',
        icon: Pencil,
        getHref: (uuid) => edit.url(uuid),
    },
];

export function CompanySubNav({
    companyUuid,
    currentPage,
    canManage = false,
}: CompanySubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                if (key === 'edit' && !canManage) {
                    return null;
                }

                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref(companyUuid)}
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
