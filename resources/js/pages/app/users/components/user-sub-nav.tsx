import { Eye, KeyRound, Pencil, Shield } from 'lucide-react';

import {
    edit,
    roles,
    show,
    userPermissions,
} from '@/actions/App/Http/Controllers/UserController';
import { SubNavigation } from '@/components/sub-navigation';

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
        <SubNavigation
            current={currentPage}
            items={navItems.map((item) => ({
                ...item,
                href: item.getHref(userUuid),
            }))}
        />
    );
}
