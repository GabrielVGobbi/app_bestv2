import { Link } from '@inertiajs/react';
import type { ComponentType } from 'react';
import type { NavItem } from '@/types';

export type SubNavigationItem = {
    key: string;
    label: string;
    href: NavItem['href'];
    icon: ComponentType<{ className?: string }>;
};

export function SubNavigation({
    items,
    current,
    label = 'Navegação do registro',
}: {
    items: SubNavigationItem[];
    current: string;
    label?: string;
}) {
    return (
        <nav aria-label={label} className="detail-subnav">
            {items.map(({ key, label: title, href, icon: Icon }) => (
                <Link
                    key={key}
                    href={href}
                    prefetch
                    className="detail-subnav-link"
                    aria-current={key === current ? 'page' : undefined}
                >
                    <Icon className="size-4" />
                    <span>{title}</span>
                </Link>
            ))}
        </nav>
    );
}
