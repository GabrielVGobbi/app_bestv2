import { Link } from '@inertiajs/react';
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
        <div className="auth-workspace grid min-h-dvh grid-rows-[auto_1fr_auto] bg-background px-5 py-6 sm:px-8">
            <header>
                <Link
                    href={home()}
                    className="flex w-fit items-center rounded-lg text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <AppLogo text="BestPlay" />
                </Link>
            </header>

            <main className="flex items-center justify-center py-12">
                <div className="w-full max-w-[25rem]">
                    <header className="mb-8">
                        <h1 className="text-3xl font-semibold text-balance text-foreground">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-2 text-sm leading-6 text-pretty text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </header>

                    {children}
                </div>
            </main>

            <footer className="text-center text-xs text-muted-foreground">
                BestPlay
            </footer>
        </div>
    );
}
