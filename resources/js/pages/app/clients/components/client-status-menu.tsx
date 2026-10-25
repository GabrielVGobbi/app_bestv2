import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { cn } from '@/lib/utils';
import type { Client } from '@/types/app/client';

type ClientStatus = Client['status'];

const STATUS_META: Record<
    ClientStatus,
    {
        label: string;
        dotClass: string;
        badge: 'success' | 'secondary' | 'warning';
    }
> = {
    active: { label: 'Ativo', dotClass: 'bg-success', badge: 'success' },
    inactive: {
        label: 'Inativo',
        dotClass: 'bg-muted-foreground',
        badge: 'secondary',
    },
    archived: { label: 'Arquivado', dotClass: 'bg-warning', badge: 'warning' },
};

const STATUS_ORDER: ClientStatus[] = ['active', 'inactive', 'archived'];

export function ClientStatusBadge({
    status,
    className,
}: {
    status: ClientStatus;
    className?: string;
}) {
    return (
        <Badge variant={STATUS_META[status].badge} className={className}>
            {STATUS_META[status].label}
        </Badge>
    );
}

interface ClientStatusMenuProps {
    uuid: string;
    status: ClientStatus;
    size?: 'sm' | 'default';
    align?: 'start' | 'center' | 'end';
    className?: string;
}

/**
 * Botão com menu para ativar, inativar ou arquivar um cliente.
 * Dispara um PATCH em clients.status preservando o scroll.
 */
export function ClientStatusMenu({
    uuid,
    status,
    size = 'default',
    align = 'start',
    className,
}: ClientStatusMenuProps) {
    const current = STATUS_META[status];

    function changeStatus(next: ClientStatus): void {
        if (next === status) {
            return;
        }

        router.patch(
            updateStatus.url(uuid),
            { status: next },
            { preserveScroll: true },
        );
    }

    return (
        <DropdownMenu>
            <DropdownTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        size={size}
                        className={cn('gap-2', className)}
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                current.dotClass,
                            )}
                            aria-hidden="true"
                        />
                        {current.label}
                        <ChevronDown
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Button>
                }
            />
            <DropdownContent
                align={align}
                className="w-44"
                checkedIndex={STATUS_ORDER.indexOf(status)}
            >
                {STATUS_ORDER.map((value, index) => (
                    <MenuItem
                        key={value}
                        index={index}
                        label={STATUS_META[value].label}
                        checked={value === status}
                        onSelect={() => changeStatus(value)}
                    />
                ))}
            </DropdownContent>
        </DropdownMenu>
    );
}
