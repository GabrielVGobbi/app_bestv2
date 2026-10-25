import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import { SubNavigation } from '@/components/sub-navigation';

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
        <SubNavigation
            current={currentPage}
            items={navItems
                .filter((item) => item.key !== 'edit' || canUpdate)
                .map((item) => ({ ...item, href: item.getHref(playlistUuid) }))}
        />
    );
}
