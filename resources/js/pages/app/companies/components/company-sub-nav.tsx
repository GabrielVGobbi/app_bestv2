import { Eye, Pencil } from 'lucide-react';
import type { ComponentType } from 'react';

import {
    edit,
    show,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import { SubNavigation } from '@/components/sub-navigation';

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
        <SubNavigation
            current={currentPage}
            items={navItems
                .filter((item) => item.key !== 'edit' || canManage)
                .map((item) => ({ ...item, href: item.getHref(companyUuid) }))}
        />
    );
}
