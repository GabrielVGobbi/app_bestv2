import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { GlobalCommandPalette } from '@/components/global-command-palette';
import { NavModuleSwitcher } from '@/components/nav-module-switcher';
import { NotificationBell } from '@/components/notification-bell';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes/app';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    showLogo?: boolean;
    headerActions?: ReactNode;
    onMobileToggle?: () => void;
};

export function DashboardHeader({ headerActions, onMobileToggle }: Props) {
    const { auth, nav } = usePage().props;
    const getInitials = useInitials();
    const showModules =
        nav.style === 'modules' && nav.modulesMenuPosition === 'header';

    return (
        <header className="relative z-50 flex h-[68px] shrink-0 items-center border-b border-header-border bg-header-background px-4 text-header-foreground md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-4">
                <button
                    id="mobile-navigation-trigger"
                    type="button"
                    onClick={onMobileToggle}
                    className="flex size-10 items-center justify-center rounded-lg border border-white/10 text-header-foreground transition-colors hover:bg-white/10 md:hidden"
                    aria-label="Abrir navegação"
                >
                    <Menu className="size-5" />
                </button>

                <Link
                    href={dashboard()}
                    prefetch
                    className="flex size-10 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-white/10"
                    aria-label="BestPlay"
                >
                    <AppLogo showText={false} />
                </Link>

                {showModules && (
                    <div className="hidden min-w-0 md:block">
                        <NavModuleSwitcher variant="header" />
                    </div>
                )}
            </div>

            <div className="mx-4 flex w-auto justify-center md:w-[min(38vw,420px)]">
                <GlobalCommandPalette />
            </div>

            <div className="flex min-w-0 flex-1 items-center justify-end gap-2">
                {headerActions}
                <NotificationBell />
                <ThemeToggle />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="relative size-10 rounded-full p-0"
                            aria-label="Abrir menu do usuário"
                        >
                            <Avatar className="size-9 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={auth.user?.avatar}
                                    alt={auth.user?.name}
                                />
                                <AvatarFallback className="rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                    {getInitials(auth.user?.name ?? '')}
                                </AvatarFallback>
                            </Avatar>
                            <span className="absolute end-0 top-0 size-2.5 rounded-full border-2 border-header-background bg-success" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-56" align="end">
                        {auth.user && <UserMenuContent user={auth.user} />}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
