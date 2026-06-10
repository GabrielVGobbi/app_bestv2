import { Link, usePage } from '@inertiajs/react';
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
import { dashboard } from '@/routes/app';
import type { BreadcrumbItem } from '@/types';
import { NotificationBell } from '../notification-bell';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    logoVisibility?: 'always' | 'mobile' | 'never';
    variant?: 'fixed' | 'content';
};

export function DashboardHeader({
    breadcrumbs = [],
    logoVisibility = 'always',
    variant = 'fixed',
}: Props) {
    const { auth, nav } = usePage().props;
    const getInitials = useInitials();
    const showLogo = logoVisibility !== 'never';
    const showModuleSwitcher =
        nav.style === 'modules' && nav.modulesMenuPosition === 'header';

    return (
        <header
            className={
                variant === 'fixed'
                    ? 'pointer-events-none fixed top-0 right-0 left-0 z-50 flex h-header items-center justify-between  bg-header-background px-5 text-header-foreground backdrop-blur-xl md:grid md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:justify-normal'
                    : 'pointer-events-none z-40 flex h-header shrink-0 items-center justify-between  bg-header-background px-5 text-header-foreground backdrop-blur-xl md:grid md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:justify-normal'
            }
        >
            <div className="pointer-events-auto flex min-w-0 items-center gap-3 md:col-start-1 md:justify-self-start">
                {showLogo && (
                    <Link
                        href={dashboard()}
                        prefetch
                        className={
                            logoVisibility === 'mobile'
                                ? 'flex shrink-0 items-center md:hidden'
                                : 'flex shrink-0 items-center'
                        }
                    >
                        <AppLogo />
                    </Link>
                )}

                {breadcrumbs.length > 0 && (
                    <>
                        {showLogo && (
                            <div
                                className={
                                    logoVisibility === 'mobile'
                                        ? 'mx-1 h-5 w-px bg-border md:hidden'
                                        : 'mx-1 h-5 w-px bg-border'
                                }
                            />
                        )}

                        <div className="min-w-0 truncate">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    </>
                )}
            </div>

            {showModuleSwitcher && (
                <div className="pointer-events-auto hidden min-w-0 justify-center md:col-start-2 md:flex">
                    <NavModuleSwitcher variant="header" />
                </div>
            )}

            <div className="pointer-events-auto flex items-center gap-3 md:col-start-3 md:justify-self-end">
                <NotificationBell />

                <ThemeToggle />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="relative size-9 rounded-full border border-border bg-card p-0 transition-colors duration-150 ease-out hover:bg-secondary"
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
