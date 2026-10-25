import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavModuleSwitcher } from '@/components/nav-module-switcher';
import { NotificationBell } from '@/components/notification-bell';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { SidebarTrigger, useSidebar } from '@/components/ui/fluid/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useNavModule } from '@/contexts/nav-module-context';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes/app';
import type { BreadcrumbItem } from '@/types';

function HeaderModules() {
    const { modules, activeModule, selectModule } = useNavModule();
    const activeIndex = modules.findIndex(
        (module) => module.slug === activeModule?.slug,
    );

    return (
        <>
            <div className="hidden @4xl:block">
                <NavModuleSwitcher variant="header" />
            </div>
            <div className="@4xl:hidden">
                <DropdownMenu>
                    <DropdownTrigger
                        render={
                            <Button
                                variant="outline"
                                size="sm"
                                aria-label={`Trocar módulo: ${activeModule?.label ?? 'Módulos'}`}
                            >
                                {activeModule?.icon && (
                                    <activeModule.icon className="size-4" />
                                )}
                                <span className="hidden sm:inline">
                                    {activeModule?.label ?? 'Módulos'}
                                </span>
                                <ChevronDown className="size-3.5" />
                            </Button>
                        }
                    />
                    <DropdownContent
                        align="end"
                        className="w-56"
                        checkedIndex={
                            activeIndex === -1 ? undefined : activeIndex
                        }
                    >
                        {modules.map((module, index) => {
                            const href = module.defaultHref ?? module.href;

                            return (
                                <MenuItem
                                    key={module.slug}
                                    index={index}
                                    icon={module.icon ?? undefined}
                                    label={module.label}
                                    checked={index === activeIndex}
                                    onSelect={() => {
                                        selectModule(module.slug);

                                        if (href) {
                                            router.visit(href);
                                        }
                                    }}
                                />
                            );
                        })}
                    </DropdownContent>
                </DropdownMenu>
            </div>
        </>
    );
}

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    showLogo?: boolean;
};

export function DashboardHeader({ breadcrumbs = [], showLogo = false }: Props) {
    const { auth, nav } = usePage().props;
    const getInitials = useInitials();
    const { open, setOpenMobile } = useSidebar();

    return (
        <header
            className={`@container z-30 flex h-header shrink-0 items-center justify-between gap-2 border-b border-header-border bg-background/80 px-5 backdrop-blur-xl ${showLogo ? 'fixed inset-x-0 top-0' : 'sticky top-0'}`}
        >
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <button
                    type="button"
                    onClick={() => setOpenMobile(true)}
                    aria-label="Abrir menu"
                    aria-haspopup="dialog"
                    className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-foreground md:hidden"
                >
                    <Menu className="size-5" />
                </button>
                {showLogo && (
                    <Link
                        href={dashboard()}
                        className="hidden items-center md:flex"
                    >
                        <AppLogo showText={open} />
                    </Link>
                )}
                <SidebarTrigger
                    aria-expanded={open}
                    className="hidden md:inline-flex"
                />
                {breadcrumbs.length > 0 && (
                    <>
                        <div className="hidden h-5 w-px bg-border md:block" />
                        <div className="min-w-0 truncate">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    </>
                )}
            </div>
            {nav.style === 'modules' &&
                nav.modulesMenuPosition === 'header' && (
                    <div className="shrink-0 px-2">
                        <HeaderModules />
                    </div>
                )}
            <div className="flex shrink-0 items-center justify-end gap-1.5 sm:gap-3 @4xl:flex-1">
                <NotificationBell />
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownTrigger
                        render={
                            <Button
                                variant="ghost"
                                aria-label="Menu da conta"
                                className="size-9 rounded-full border border-border bg-card p-0"
                            >
                                <Avatar className="size-8 rounded-full">
                                    <AvatarImage
                                        src={auth.user?.avatar}
                                        alt={auth.user?.name}
                                    />
                                    <AvatarFallback className="rounded-full bg-primary-soft text-sm font-bold text-primary-soft-foreground">
                                        {getInitials(auth.user?.name ?? '')}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        }
                    />
                    <DropdownContent className="w-56" align="end">
                        {auth.user && <UserMenuContent user={auth.user} />}
                    </DropdownContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
