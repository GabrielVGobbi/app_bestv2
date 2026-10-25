import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth, branding } = usePage().props;

    return (
        <>
            <Head title="Início" />
            <div className="flex min-h-svh flex-col bg-background">
                <header className="flex items-center justify-between gap-4 px-6 py-5 md:px-10">
                    <AppLogo className="text-foreground" />
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button variant="outline" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Entrar</Link>
                                </Button>
                                {canRegister && (
                                    <Button variant="outline" asChild>
                                        <Link href={register()}>
                                            Criar conta
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </header>
                <main className="flex flex-1 items-center px-6 pb-16 md:px-10">
                    <div className="mx-auto w-full max-w-2xl">
                        <h1 className="text-3xl font-bold tracking-tight text-balance text-foreground md:text-4xl">
                            {branding.name}
                        </h1>
                        <p className="mt-3 text-base text-pretty text-muted-foreground">
                            Base para aplicações administrativas com usuários,
                            papéis, permissões, exportações, notificações e
                            uploads prontos para usar.
                        </p>
                        <div className="mt-8">
                            <Button size="lg" asChild>
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user
                                        ? 'Ir para o dashboard'
                                        : 'Entrar na sua conta'}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
