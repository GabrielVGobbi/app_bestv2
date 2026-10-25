import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    /** Linha discreta acima do título, como a saudação do dashboard. */
    eyebrow?: ReactNode;
    /** Mostra o botão de voltar antes do título. */
    backHref?: string;
    actions?: ReactNode;
    className?: string;
};

/** Cabeçalho único das páginas: título, descrição, voltar e ações. */
export function PageHeader({
    title,
    description,
    eyebrow,
    backHref,
    actions,
    className,
}: Props) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 md:flex-row md:items-end md:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {backHref && (
                    <Button
                        variant="outline"
                        size="icon"
                        className="mt-0.5 size-8 shrink-0"
                        asChild
                    >
                        <Link href={backHref} aria-label="Voltar">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                )}
                <div className="min-w-0">
                    {eyebrow && (
                        <p className="text-xs text-muted-foreground">
                            {eyebrow}
                        </p>
                    )}
                    <h1 className="text-2xl font-bold tracking-tight text-balance [overflow-wrap:anywhere] text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 text-sm text-pretty [overflow-wrap:anywhere] text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {actions && (
                <div className="flex min-w-0 shrink-0 flex-wrap items-center gap-2 [&>div]:max-w-full [&>div]:flex-wrap">
                    {actions}
                </div>
            )}
        </header>
    );
}
