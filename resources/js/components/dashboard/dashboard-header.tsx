import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavModuleSwitcher } from '@/components/nav-module-switcher';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/app';
import type { BreadcrumbItem } from '@/types';
import { NotificationBell } from '../notification-bell';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    showLogo?: boolean;
    headerActions?: ReactNode;
    onMobileToggle?: () => void;
};

export function DashboardHeader({
    breadcrumbs = [],
    showLogo = false,
    headerActions,
    onMobileToggle,
}: Props) {
    const { auth, nav } = usePage().props;
    const getInitials = useInitials();
    const isFullWidth = showLogo;
    const showModuleSwitcher =
        nav.style === 'modules' && nav.modulesMenuPosition === 'header';

    return (
        <header
            className={
                isFullWidth
                    ? 'pointer-events-none fixed top-0 right-0 left-0 z-50 flex h-header items-center justify-between border-b border-header-border app-glass px-4 text-header-foreground shadow-[var(--shadow-app-header)] md:px-5'
                    : 'sticky top-0 z-30 flex h-header shrink-0 items-center justify-between border-b border-header-border app-glass px-4 text-header-foreground shadow-[var(--shadow-app-header)] md:px-5'
            }
        >
            <div
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-3',
                    isFullWidth && 'pointer-events-auto',
                )}
            >
                <button
                    type="button"
                    onClick={onMobileToggle}
                    className="flex size-9 items-center justify-center rounded-md border border-border/80 bg-card text-foreground shadow-xs transition-colors hover:border-primary/20 hover:bg-secondary md:hidden"
                    aria-label="Abrir menu"
                >
                    <Menu className="size-5" />
                </button>

                {showLogo && (
                    <Link
                        href={dashboard()}
                        prefetch
                        className="flex shrink-0 items-center"
                    >
                        <AppLogo />
                    </Link>
                )}

                {breadcrumbs.length > 0 && (
                    <>
                        {showLogo && (
                            <div className="mx-1 h-5 w-px bg-border/80" />
                        )}

                        <div className="min-w-0 truncate">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    </>
                )}
            </div>

            {showModuleSwitcher && (
                <div
                    className={cn(
                        'hidden shrink-0 md:flex',
                        isFullWidth && 'pointer-events-auto',
                    )}
                >
                    <NavModuleSwitcher variant="header" />
                </div>
            )}

            <div
                className={cn(
                    'flex flex-1 items-center justify-end gap-2',
                    isFullWidth && 'pointer-events-auto',
                )}
            >
                {headerActions}

                <NotificationBell />

                <ThemeToggle />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="relative size-9 rounded-full border border-border/80 bg-card p-0 transition-colors duration-150 ease-out hover:bg-secondary"
                        >
                            <Avatar className="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={auth.user?.avatar}
                                    alt={auth.user?.name}
                                />
                                <AvatarFallback className="rounded-full bg-runey-orange text-sm font-bold text-white">
                                    {getInitials(auth.user?.name ?? '')}
                                </AvatarFallback>
                            </Avatar>
                            <span className="absolute top-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-background bg-success" />
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
