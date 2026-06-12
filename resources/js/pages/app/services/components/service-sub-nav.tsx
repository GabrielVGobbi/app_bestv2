import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import { cn } from '@/lib/utils';

type ServicePage = 'show' | 'edit';

interface ServiceSubNavProps {
    serviceUuid: string;
    currentPage: ServicePage;
    canUpdate?: boolean;
}

const navItems: {
    key: ServicePage;
    label: string;
    icon: ComponentType<{ className?: string }>;
    getHref: (uuid: string) => string;
}[] = [
    {
        key: 'show',
        label: 'Visao geral',
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

export function ServiceSubNav({
    serviceUuid,
    currentPage,
    canUpdate = false,
}: ServiceSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                if (key === 'edit' && !canUpdate) {
                    return null;
                }

                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref(serviceUuid)}
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
