import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

type RecordIdentityProps = {
    name: string;
    description?: string | null;
    href: InertiaLinkProps['href'];
};

export function RecordIdentity({
    name,
    description,
    href,
}: RecordIdentityProps) {
    const initials = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toLocaleUpperCase('pt-BR');

    return (
        <Link
            href={href}
            className="group flex min-w-0 items-center gap-3 rounded-lg py-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <span
                aria-hidden="true"
                className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-sidebar text-sm font-medium text-muted-foreground"
            >
                {initials}
            </span>
            <span className="min-w-0">
                <span
                    title={name}
                    className="block truncate text-[15px] font-medium underline-offset-4 group-hover:underline"
                >
                    {name}
                </span>
                {description && (
                    <span
                        title={description}
                        className="mt-1 block truncate text-xs font-normal text-muted-foreground"
                    >
                        {description}
                    </span>
                )}
            </span>
        </Link>
    );
}
