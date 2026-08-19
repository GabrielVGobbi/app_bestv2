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
        <header className="bg-header relative z-50 flex h-[60px] shrink-0 items-center border-b border-header-border px-3 text-header-foreground md:px-5">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <button
                    id="mobile-navigation-trigger"
                    type="button"
                    onClick={onMobileToggle}
                    className="flex size-9 items-center justify-center rounded-lg border border-border bg-background text-foreground transition-colors hover:bg-muted md:hidden"
                    aria-label="Abrir navegação"
                >
                    <Menu className="size-5" />
                </button>

                <Link
                    href={dashboard()}
                    prefetch
                    className="flex size-9 shrink-0 items-center justify-center rounded-lg text-primary hover:bg-muted"
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

            <div className="mx-3 flex w-auto justify-center md:w-[min(34vw,360px)]">
                <GlobalCommandPalette />
            </div>

            <div className="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                {headerActions}
                <NotificationBell />
                <ThemeToggle />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="relative size-9 rounded-full p-0"
                            aria-label="Abrir menu do usuário"
                        >
                            <Avatar className="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={auth.user?.avatar}
                                    alt={auth.user?.name}
                                />
                                <AvatarFallback className="rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                    {getInitials(auth.user?.name ?? '')}
                                </AvatarFallback>
                            </Avatar>
                            <span className="absolute end-0 top-0 size-2.5 rounded-full border-2 border-background bg-success" />
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
