import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveNavItems, resolveNavModules } from '@/lib/resolve-nav-items';
import { dashboard } from '@/routes/app';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { nav } = usePage().props;

    // Flatten modules → single nav list (shadcn sidebar não usa módulos)
    // Módulo sem items + com href → vira link direto
    const modules = resolveNavModules(nav.modules);
    const mainItems: NavItem[] = modules.flatMap((m) => {
        if (m.items.length === 0 && m.href) {
            return [{ title: m.label, href: m.href, icon: m.icon ?? undefined }];
        }
        return m.items;
    });
    const footerItems = resolveNavItems(nav.footer);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
