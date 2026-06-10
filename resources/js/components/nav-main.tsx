import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

function isAnyChildActive(items: NavItem[], isActive: (href: string) => boolean): boolean {
    return items.some(
        (item) =>
            isActive(item.href as string) ||
            (item.items ? isAnyChildActive(item.items, isActive) : false),
    );
}

function NavSubItems({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarMenuSub>
            {items.map((item) => {
                const hasChildren = (item.items?.length ?? 0) > 0;
                const isActive =
                    isCurrentOrParentUrl(item.href as string) ||
                    (hasChildren && isAnyChildActive(item.items!, isCurrentOrParentUrl));

                if (hasChildren) {
                    return (
                        <SidebarMenuSubItem key={item.title}>
                            <Collapsible defaultOpen={isActive} className="group/sub">
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuSubButton isActive={isActive}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto size-3 transition-transform group-data-[state=open]/sub:rotate-90" />
                                    </SidebarMenuSubButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <NavSubItems items={item.items!} />
                                </CollapsibleContent>
                            </Collapsible>
                        </SidebarMenuSubItem>
                    );
                }

                return (
                    <SidebarMenuSubItem key={item.title}>
                        <SidebarMenuSubButton asChild isActive={isCurrentOrParentUrl(item.href as string)}>
                            <Link href={item.href as string} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                );
            })}
        </SidebarMenuSub>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasChildren = (item.items?.length ?? 0) > 0;
                    const isActive =
                        isCurrentOrParentUrl(item.href as string) ||
                        (hasChildren && isAnyChildActive(item.items!, isCurrentOrParentUrl));

                    if (hasChildren) {
                        return (
                            <Collapsible
                                key={item.title}
                                defaultOpen={isActive}
                                className="group/collapsible"
                                asChild
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={isActive}
                                            tooltip={{ children: item.title }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <NavSubItems items={item.items!} />
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        );
                    }

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(item.href as string)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href as string} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
