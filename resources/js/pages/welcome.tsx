import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="BestPlay" />
            <div className="flex min-h-dvh flex-col bg-canvas p-6 sm:p-10">
                <header>
                    <AppLogo text="BestPlay" />
                </header>
                <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col justify-center py-16">
                    <div className="max-w-2xl">
                        <h1 className="text-4xl leading-tight font-medium tracking-tight text-balance sm:text-6xl">
                            Um espaço para toda a sua operação.
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-7 text-muted-foreground">
                            Clientes, campanhas, playlists e financeiro. Acesse
                            a BestPlay e continue seu trabalho.
                        </p>
                        <div className="mt-9 flex flex-wrap items-center gap-3">
                            <Button size="lg" asChild>
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user
                                        ? 'Acessar plataforma'
                                        : 'Entrar na BestPlay'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            {!auth.user && canRegister && (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={register()}>Criar conta</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                    <div className="mt-16 flex flex-wrap gap-x-8 gap-y-3 border-t pt-6 text-sm text-muted-foreground">
                        <span>Comercial</span>
                        <span>Gestão</span>
                        <span>Financeiro</span>
                        <span>Administração</span>
                    </div>
                </main>
                <footer className="text-xs text-muted-foreground">
                    BestPlay
                </footer>
            </div>
        </>
    );
}
