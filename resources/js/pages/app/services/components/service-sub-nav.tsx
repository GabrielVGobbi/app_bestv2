import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import { SubNavigation } from '@/components/sub-navigation';

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

export function ServiceSubNav({
    serviceUuid,
    currentPage,
    canUpdate = false,
}: ServiceSubNavProps) {
    return (
        <SubNavigation
            current={currentPage}
            items={navItems
                .filter((item) => item.key !== 'edit' || canUpdate)
                .map((item) => ({ ...item, href: item.getHref(serviceUuid) }))}
        />
    );
}
