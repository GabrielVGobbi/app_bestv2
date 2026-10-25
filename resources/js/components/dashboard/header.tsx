import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';

interface HeaderProps {
    title: string;
    subtitle?: string;
    backHref?: string;
    /** Ações e sub-navegação à direita do título. */
    children?: ReactNode;
}

/** Faixa fixa no topo das telas de módulo; o visual vem do PageHeader. */
export default function Header({
    title,
    subtitle,
    backHref,
    children,
}: HeaderProps) {
    return (
        <div className="sticky top-0 z-20 bg-background/95 px-4 pt-5 pb-4 backdrop-blur-sm md:px-6">
            <PageHeader
                title={title}
                description={subtitle || undefined}
                backHref={backHref}
                actions={children}
                className="md:items-center"
            />
        </div>
    );
}
