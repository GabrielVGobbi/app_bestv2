import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavTone = 'inflow' | 'outflow';

/** Formato consumido pelos componentes de navegação (ícone já resolvido). */
export type NavItem = {
    title: string;
    type?: 'link' | 'group' | 'section';
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    tone?: NavTone;
    isActive?: boolean;
    items?: NavItem[];
};

/** Formato recebido do backend via Inertia (ícone como string). */
export type RawNavItem = {
    title: string;
    type?: 'link' | 'group' | 'section';
    href?: string;
    icon?: string | null;
    tone?: NavTone;
    visible?: boolean;
    items?: RawNavItem[];
};

/** Módulo de navegação com ícones já resolvidos. */
export type NavModule = {
    slug: string;
    label: string;
    icon: LucideIcon | null;
    default: boolean;
    routePrefixes: string[];
    href?: string | null;
    defaultHref?: string | null;
    items: NavItem[];
};

/** Módulo de navegação recebido do backend. */
export type RawNavModule = {
    slug: string;
    label: string;
    icon?: string | null;
    default: boolean;
    routePrefixes: string[];
    href?: string | null;
    defaultHref?: string | null;
    items: RawNavItem[];
};
