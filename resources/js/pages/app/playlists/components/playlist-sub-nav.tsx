import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import { cn } from '@/lib/utils';

type PlaylistPage = 'show' | 'edit';

interface PlaylistSubNavProps {
    playlistUuid: string;
    currentPage: PlaylistPage;
    canUpdate?: boolean;
}

const navItems: {
    key: PlaylistPage;
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

export function PlaylistSubNav({
    playlistUuid,
    currentPage,
    canUpdate = false,
}: PlaylistSubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border border-border bg-muted p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                if (key === 'edit' && !canUpdate) {
                    return null;
                }

                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref(playlistUuid)}
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
