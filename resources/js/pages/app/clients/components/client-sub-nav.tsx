import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { cn } from '@/lib/utils';

type ClientPage = 'show' | 'edit';

interface ClientSubNavProps {
    clientUuid: string;
    currentPage: ClientPage;
    /** Esconde a aba Editar para quem não pode atualizar o cliente. */
    canEdit?: boolean;
}

const navItems: {
    key: ClientPage;
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

export function ClientSubNav({
    clientUuid,
    currentPage,
    canEdit = true,
}: ClientSubNavProps) {
    return (
        <nav
            aria-label="Seções do cliente"
            className="flex gap-1 rounded-lg border border-border bg-muted p-1"
        >
            {navItems
                .filter(({ key }) => key !== 'edit' || canEdit)
                .map(({ key, label, icon: Icon, getHref }) => {
                    const isActive = key === currentPage;

                    return (
                        <Link
                            key={key}
                            href={getHref(clientUuid)}
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
                            <Icon className="size-3.5" aria-hidden="true" />
                            <span className="hidden sm:inline">{label}</span>
                        </Link>
                    );
                })}
        </nav>
    );
}
