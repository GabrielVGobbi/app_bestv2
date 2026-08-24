import { resolveIcon } from '@/lib/nav-icons';
import type { NavItem, NavModule, RawNavItem, RawNavModule } from '@/types';

export function resolveNavItems(raw: RawNavItem[]): NavItem[] {
    return raw
        .filter((item) => item.visible !== false)
        .map((item) => ({
            title: item.title,
            type: item.type ?? (item.items?.length ? 'group' : 'link'),
            href: (item.href ?? '#') as NavItem['href'],
            icon: resolveIcon(item.icon),
            tone: item.tone,
            items: item.items ? resolveNavItems(item.items) : undefined,
        }));
}

export function resolveNavModules(raw: RawNavModule[]): NavModule[] {
    return raw.map((m) => ({
        slug: m.slug,
        label: m.label,
        icon: resolveIcon(m.icon),
        default: m.default,
        routePrefixes: m.routePrefixes,
        href: m.href ?? null,
        defaultHref: m.defaultHref ?? null,
        items: resolveNavItems(m.items),
    }));
}
