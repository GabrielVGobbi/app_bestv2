import { Link } from '@inertiajs/react';
import { Palette, ShieldCheck, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';

import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Segurança',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Aparência',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="page-wrapper-full">
            <Heading
                title="Configurações"
                description="Gerencie seu perfil, segurança e preferências da conta."
            />

            <div className="mt-6 grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <aside className="h-fit rounded-xl border border-border/80 bg-card p-3 shadow-[var(--shadow-app-panel)]">
                    <nav
                        className="grid gap-1 sm:grid-cols-3 lg:grid-cols-1"
                        aria-label="Configurações"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-auto w-full justify-start gap-3 rounded-lg border border-transparent px-3 py-3 text-left text-[13px] font-medium text-muted-foreground',
                                    {
                                        'border-primary/15 bg-brand-surface text-primary shadow-xs':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href} prefetch="click">
                                    {item.icon && (
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-background text-current shadow-xs">
                                            <item.icon className="size-4" />
                                        </span>
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                <div className="min-w-0 rounded-xl border border-border/80 bg-card p-5 shadow-[var(--shadow-app-panel)] sm:p-7">
                    <section className="max-w-2xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
