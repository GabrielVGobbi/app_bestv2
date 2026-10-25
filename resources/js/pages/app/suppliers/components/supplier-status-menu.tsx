import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { cn } from '@/lib/utils';
import type { Supplier } from '@/types/app/supplier';

type SupplierStatus = Supplier['status'];

export const SUPPLIER_STATUS_META: Record<
    SupplierStatus,
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

const STATUS_ORDER: SupplierStatus[] = ['active', 'inactive', 'archived'];

export function SupplierStatusBadge({
    status,
    className,
}: {
    status: SupplierStatus;
    className?: string;
}) {
    return (
        <Badge
            variant={SUPPLIER_STATUS_META[status].badge}
            className={className}
        >
            {SUPPLIER_STATUS_META[status].label}
        </Badge>
    );
}

interface SupplierStatusMenuProps {
    uuid: string;
    status: SupplierStatus;
    size?: 'sm' | 'default';
    align?: 'start' | 'center' | 'end';
    className?: string;
}

export function SupplierStatusMenu({
    uuid,
    status,
    size = 'default',
    align = 'start',
    className,
}: SupplierStatusMenuProps) {
    const current = SUPPLIER_STATUS_META[status];

    function changeStatus(next: SupplierStatus): void {
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
                        label={SUPPLIER_STATUS_META[value].label}
                        checked={value === status}
                        onSelect={() => changeStatus(value)}
                    />
                ))}
            </DropdownContent>
        </DropdownMenu>
    );
}
