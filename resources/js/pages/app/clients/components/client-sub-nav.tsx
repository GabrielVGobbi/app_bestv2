import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';

import { edit, show } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { cn } from '@/lib/utils';

type ClientPage = 'show' | 'edit';

interface ClientSubNavProps {
    clientUuid: string;
    currentPage: ClientPage;
}

const navItems: {
    key: ClientPage;
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
];

export function ClientSubNav({ clientUuid, currentPage }: ClientSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref(clientUuid)}
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
