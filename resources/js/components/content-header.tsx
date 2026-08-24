import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ContentHeaderProps {
    title?: ReactNode;
    description?: ReactNode;
    backHref?: InertiaLinkProps['href'];
    backLabel?: string;
    actions?: ReactNode;
    children?: ReactNode;
    className?: string;
}

function ContentHeader({
    title,
    description,
    backHref,
    backLabel = 'Voltar',
    actions,
    children,
    className,
}: ContentHeaderProps) {
    const hasStructuredContent =
        title != null || description != null || backHref != null;

    return (
        <header
            className={cn(
                'sticky top-0 z-20 flex min-h-16 w-full shrink-0 flex-col gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-xl sm:flex-row sm:flex-wrap sm:items-center sm:justify-between md:px-6',
                className,
            )}
        >
            {children ??
                (hasStructuredContent ? (
                    <div className="flex min-w-0 items-center gap-3">
                        {backHref ? (
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8"
                                asChild
                            >
                                <Link href={backHref} aria-label={backLabel}>
                                    <ArrowLeft className="size-4" />
                                </Link>
                            </Button>
                        ) : null}
                        <div className="min-w-0">
                            {title ? (
                                <h1 className="text-xl font-semibold">{title}</h1>
                            ) : null}
                            {description ? (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            ) : null}
                        </div>
                    </div>
                ) : null)}

            {actions ? (
                <div className="flex flex-wrap items-center gap-2">{actions}</div>
            ) : null}
        </header>
    );
}

export { ContentHeader };
export default ContentHeader;
