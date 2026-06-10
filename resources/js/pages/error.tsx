import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, RefreshCw } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

interface ErrorConfig {
    title: string;
    description: string;
    showRefresh?: boolean;
}

const errorConfig: Record<number, ErrorConfig> = {
    400: {
        title: 'Requisição inválida',
        description: 'A requisição enviada não pôde ser processada. Verifique os dados e tente novamente.',
    },
    401: {
        title: 'Não autorizado',
        description: 'Você precisa estar autenticado para acessar esta página.',
    },
    403: {
        title: 'Acesso negado',
        description: 'Você não tem permissão para visualizar este conteúdo.',
    },
    404: {
        title: 'Página não encontrada',
        description: 'A página que você procura não existe ou foi removida.',
    },
    419: {
        title: 'Sessão expirada',
        description: 'Sua sessão expirou por inatividade. Faça login novamente para continuar.',
        showRefresh: true,
    },
    429: {
        title: 'Muitas requisições',
        description: 'Você fez muitas requisições em pouco tempo. Aguarde um momento e tente novamente.',
    },
    500: {
        title: 'Erro interno',
        description: 'Algo inesperado aconteceu no servidor. Nossa equipe foi notificada.',
        showRefresh: true,
    },
    503: {
        title: 'Em manutenção',
        description: 'O serviço está temporariamente indisponível. Voltaremos em breve.',
        showRefresh: true,
    },
};

const fallbackConfig: ErrorConfig = {
    title: 'Algo deu errado',
    description: 'Ocorreu um erro inesperado. Tente novamente mais tarde.',
    showRefresh: true,
};

export default function Error({ status }: { status: number }) {
    const config = errorConfig[status] ?? fallbackConfig;

    return (
        <>
            <Head title={`${status} — ${config.title}`} />

            <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background px-6">
                {/* Background decoration */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 flex items-center justify-center"
                >
                    <div className="h-[600px] w-[600px] rounded-full bg-primary/5 blur-3xl" />
                </div>

                <div className="relative z-10 flex w-full max-w-md flex-col items-center gap-8 text-center">
                    {/* Logo */}
                    <Link href={home.url()} className="flex items-center gap-2 opacity-80 transition-opacity hover:opacity-100">
                        <AppLogoIcon className="size-8 fill-current text-foreground" />
                    </Link>

                    {/* Error code */}
                    <div className="flex flex-col items-center gap-1">
                        <span className="text-[7rem] leading-none font-black tracking-tight text-foreground/10 select-none">
                            {status}
                        </span>
                        <div className="-mt-4 flex flex-col gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {config.title}
                            </h1>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {config.description}
                            </p>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="size-4" />
                            Voltar
                        </Button>

                        <Button asChild>
                            <Link href={home.url()}>
                                <Home className="size-4" />
                                Ir para o início
                            </Link>
                        </Button>

                        {config.showRefresh && (
                            <Button variant="ghost" onClick={() => window.location.reload()}>
                                <RefreshCw className="size-4" />
                                Tentar novamente
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
