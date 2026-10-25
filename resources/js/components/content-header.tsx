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
                'flex min-h-20 w-full max-w-none shrink-0 flex-col gap-4 bg-background px-5 pt-7 pb-5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between md:px-8',
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
                                <h1 className="page-heading-title">{title}</h1>
                            ) : null}
                            {description ? (
                                <p className="page-heading-description">
                                    {description}
                                </p>
                            ) : null}
                        </div>
                    </div>
                ) : null)}

            {actions ? <div className="page-actions">{actions}</div> : null}
        </header>
    );
}

export { ContentHeader };
export default ContentHeader;
