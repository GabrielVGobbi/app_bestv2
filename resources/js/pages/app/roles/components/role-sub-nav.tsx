import { Eye, KeyRound, Pencil } from 'lucide-react';

import { SubNavigation } from '@/components/sub-navigation';
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
    {
        key: 'show',
        label: 'Visão geral',
        icon: Eye,
        getHref: (id) => show.url(id),
    },
    {
        key: 'edit',
        label: 'Editar',
        icon: Pencil,
        getHref: (id) => edit.url(id),
    },
    {
        key: 'permissions',
        label: 'Permissões',
        icon: KeyRound,
        getHref: (id) => `/roles/${id}/permissions`,
    },
];

export function RoleSubNav({ roleId, currentPage }: RoleSubNavProps) {
    return (
        <SubNavigation
            current={currentPage}
            items={navItems.map((item) => ({
                ...item,
                href: item.getHref(roleId),
            }))}
        />
    );
}
