import { Link } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { CopyableText } from '@/components/ui/copyable-text';
import { cn } from '@/lib/utils';
import type { Address } from '@/types/address';

export type DetailItem = {
    label: string;
    value: string | null | undefined;
};

const EMPTY_VALUE = '-';

export function isBlank(value: string | null | undefined): boolean {
    return !value || value.trim() === '';
}

export function Section({
    title,
    children,
    className,
    action,
}: {
    title?: string;
    children: ReactNode;
    className?: string;
    action?: ReactNode;
}) {
    return (
        <section className={cn('space-y-3', className)}>
            {title || action ? (
                <header className="flex min-h-7 items-center justify-between gap-3">
                    {title ? (
                        <h2 className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {title}
                        </h2>
                    ) : (
                        <span />
                    )}
                    {action}
                </header>
            ) : null}
            {children}
        </section>
    );
}

export function FieldGrid({
    items,
    columns = 'sm:grid-cols-2',
    variant = 'grid',
}: {
    items: DetailItem[];
    columns?: string;
    variant?: 'grid' | 'rows';
}) {
    if (variant === 'rows') {
        return (
            <dl className="divide-y divide-separator rounded-lg border border-border">
                {items.map((item) => {
                    const blank = isBlank(item.value);

                    return (
                        <div
                            key={item.label}
                            className="grid grid-cols-[minmax(7rem,9.5rem)_minmax(0,1fr)] gap-3 px-3.5 py-2.5 sm:grid-cols-[11rem_minmax(0,1fr)]"
                        >
                            <dt className="text-sm text-muted-foreground">
                                {item.label}
                            </dt>
                            <dd
                                className={cn(
                                    'min-w-0 text-sm break-words',
                                    blank
                                        ? 'text-muted-foreground'
                                        : 'font-medium text-foreground',
                                )}
                            >
                                {blank ? EMPTY_VALUE : item.value}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        );
    }

    return (
        <dl className={cn('grid gap-x-6 gap-y-4', columns)}>
            {items.map((item) => {
                const blank = isBlank(item.value);

                return (
                    <div key={item.label} className="min-w-0">
                        <dt className="text-xs font-medium text-muted-foreground">
                            {item.label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-1 min-w-0 text-sm break-words',
                                blank
                                    ? 'text-muted-foreground'
                                    : 'font-medium text-foreground',
                            )}
                        >
                            {blank ? EMPTY_VALUE : item.value}
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}

export function SidebarContactRow({
    icon: Icon,
    label,
    value,
    copyable = false,
    emptyHref,
    emptyLabel,
}: {
    icon: LucideIcon;
    label: string;
    value: string | null | undefined;
    copyable?: boolean;
    emptyHref?: string;
    emptyLabel?: string;
}) {
    const blank = isBlank(value);

    if (blank) {
        if (emptyHref && emptyLabel) {
            return (
                <Link
                    href={emptyHref}
                    className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    aria-label={emptyLabel}
                >
                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                    <span className="truncate">{emptyLabel}</span>
                </Link>
            );
        }

        return (
            <div className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground">
                <Icon className="size-4 shrink-0" aria-hidden="true" />
                <span className="sr-only">{label}</span>
                <span className="truncate">{EMPTY_VALUE}</span>
            </div>
        );
    }

    const content = (
        <>
            <Icon className="size-4 shrink-0" aria-hidden="true" />
            <span className="sr-only">{label}</span>
            <span className="truncate">{value}</span>
        </>
    );

    if (copyable) {
        return (
            <CopyableText
                value={value}
                label={label}
                className="w-full px-2 py-1.5 text-sm text-muted-foreground"
            >
                <span className="flex min-w-0 items-center gap-2">
                    {content}
                </span>
            </CopyableText>
        );
    }

    return (
        <div className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground">
            {content}
        </div>
    );
}

export function SidebarMetaRow({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string | null | undefined;
}) {
    const blank = isBlank(value);

    return (
        <div className="flex min-w-0 items-start gap-2 rounded-md px-2 py-1.5 text-sm">
            <Icon
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p
                    className={cn(
                        'mt-0.5 break-words',
                        blank
                            ? 'text-muted-foreground'
                            : 'font-medium text-foreground',
                    )}
                >
                    {blank ? EMPTY_VALUE : value}
                </p>
            </div>
        </div>
    );
}

export function DefaultAddressSummary({ address }: { address?: Address }) {
    if (!address) {
        return (
            <div className="flex items-start gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground">
                <MapPin className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>Nenhum endereço padrão</span>
            </div>
        );
    }

    return (
        <div className="space-y-1.5 rounded-md px-2 py-1.5">
            <div className="flex min-w-0 items-center gap-2">
                <MapPin
                    className="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <p className="truncate text-sm font-medium">
                    {address.name || 'Endereço'}
                </p>
                <Badge variant="secondary">Padrão</Badge>
            </div>
            <p className="pl-6 text-xs leading-5 text-muted-foreground">
                {address.full_address}
            </p>
        </div>
    );
}

export function TechnicalIdsDisclosure({ items }: { items: DetailItem[] }) {
    return (
        <Collapsible>
            <CollapsibleTrigger className="group flex w-full items-center justify-between rounded-md px-1 py-2 text-left text-xs font-medium text-muted-foreground transition-colors hover:text-foreground">
                Dados técnicos
                <ChevronDown
                    className="size-3.5 transition-transform group-data-[state=open]:rotate-180"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="pt-2">
                <FieldGrid columns="grid-cols-1" items={items} />
            </CollapsibleContent>
        </Collapsible>
    );
}
