import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalCommandPalette } from '@/components/global-command-palette';
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
import { useNavModule } from '@/contexts/nav-module-context';
import { useInitials } from '@/hooks/use-initials';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    headerActions?: ReactNode;
    onMobileToggle?: () => void;
    mobileOpen?: boolean;
};

export function DashboardHeader({
    breadcrumbs = [],
    headerActions,
    onMobileToggle,
    mobileOpen,
}: Props) {
    const { auth } = usePage().props;
    const { activeModule } = useNavModule();
    const getInitials = useInitials();

    return (
        <header className="flex h-12 shrink-0 items-center gap-3 bg-header-background px-4 text-header-foreground md:px-8">
            <Button
                id="mobile-navigation-trigger"
                type="button"
                variant="ghost"
                size="icon"
                onClick={onMobileToggle}
                className="shrink-0 md:hidden"
                aria-label="Abrir navegação"
                aria-expanded={mobileOpen}
            >
                <Menu className="size-6" />
            </Button>
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <span className="hidden shrink-0 text-sm text-muted-foreground lg:inline">
                    {activeModule?.label ?? 'BestPlay'}
                </span>
                {breadcrumbs.length > 0 && (
                    <span
                        className="hidden text-border lg:inline"
                        aria-hidden="true"
                    >
                        /
                    </span>
                )}
                <div className="min-w-0 overflow-hidden text-sm [&_li]:min-w-0 [&_li]:shrink [&_ol]:flex-nowrap [&_span]:truncate">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                {headerActions}
                <GlobalCommandPalette />
                <div className="mx-1 hidden h-5 border-l sm:block" />
                <NotificationBell />
                <ThemeToggle />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="ml-1 size-9 rounded-full p-0"
                            aria-label="Abrir menu do usuário"
                        >
                            <Avatar className="size-8">
                                <AvatarImage
                                    src={auth.user?.avatar}
                                    alt={auth.user?.name}
                                />
                                <AvatarFallback className="bg-secondary text-xs font-semibold text-foreground">
                                    {getInitials(auth.user?.name ?? '')}
                                </AvatarFallback>
                            </Avatar>
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
