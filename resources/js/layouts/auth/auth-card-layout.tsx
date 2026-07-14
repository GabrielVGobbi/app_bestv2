import { Link } from '@inertiajs/react';
import { Music2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';

import AppLogo from '@/components/app-logo';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="auth-workspace flex min-h-svh items-center justify-center p-4 sm:p-6 lg:p-10">
            <div className="grid w-full max-w-5xl overflow-hidden rounded-2xl border border-border/80 bg-card shadow-[var(--shadow-app-floating)] lg:min-h-[38rem] lg:grid-cols-[minmax(18rem,0.9fr)_minmax(25rem,1.1fr)]">
                <aside className="relative hidden overflow-hidden bg-sidebar p-9 text-sidebar-foreground lg:flex lg:flex-col lg:justify-between">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,color-mix(in_oklab,var(--primary)_38%,transparent),transparent_24rem)]" />
                    <div className="absolute -right-16 bottom-12 size-64 rounded-full border border-white/8" />
                    <div className="absolute -right-7 bottom-21 size-44 rounded-full border border-white/8" />
                    <div className="absolute inset-y-0 right-0 w-1/2 [background-image:repeating-linear-gradient(115deg,transparent_0_13px,white_13px_14px)] opacity-10" />

                    <Link
                        href={home()}
                        className="relative z-10 flex w-fit items-center rounded-lg text-white outline-none focus-visible:ring-3 focus-visible:ring-sidebar-ring"
                    >
                        <AppLogo text="BestPlay" />
                    </Link>

                    <div className="relative z-10 max-w-sm">
                        <div
                            className="mb-7 flex h-12 items-end gap-1.5"
                            aria-hidden="true"
                        >
                            <span className="h-5 w-1.5 rounded-full bg-white/35" />
                            <span className="h-9 w-1.5 rounded-full bg-white/55" />
                            <span className="h-12 w-1.5 rounded-full bg-primary" />
                            <span className="h-7 w-1.5 rounded-full bg-white/70" />
                            <span className="h-10 w-1.5 rounded-full bg-white/45" />
                        </div>
                        <p className="text-[11px] font-semibold tracking-[0.16em] text-primary uppercase">
                            Operação conectada
                        </p>
                        <h1 className="mt-3 text-3xl font-semibold tracking-[-0.04em] text-balance text-white">
                            Gestão musical, comercial e operacional em um só
                            ritmo.
                        </h1>
                        <p className="mt-4 text-sm leading-6 text-white/60">
                            Uma experiência clara e consistente para acompanhar
                            cada etapa do trabalho da BestPlay.
                        </p>
                    </div>

                    <div className="relative z-10 flex items-center gap-2 text-xs text-white/45">
                        <Music2 className="size-4 text-primary" />
                        BestPlay · Plataforma de gestão
                    </div>
                </aside>

                <main className="flex items-center px-6 py-9 sm:px-10 lg:px-14">
                    <div className="mx-auto w-full max-w-md">
                        <Link
                            href={home()}
                            className="mb-10 flex w-fit items-center rounded-lg outline-none focus-visible:ring-3 focus-visible:ring-ring lg:hidden"
                        >
                            <AppLogo text="BestPlay" />
                        </Link>

                        <header className="mb-8">
                            <p className="text-[11px] font-semibold tracking-[0.14em] text-primary uppercase">
                                Área segura
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold tracking-[-0.03em] text-foreground">
                                {title}
                            </h2>
                            {description && (
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </header>

                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
