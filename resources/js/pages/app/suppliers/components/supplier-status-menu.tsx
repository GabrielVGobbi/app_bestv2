import { router } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { Supplier } from '@/types/app/supplier';

type SupplierStatus = Supplier['status'];

const STATUS_META: Record<SupplierStatus, { label: string; dotClass: string }> =
    {
        active: { label: 'Ativo', dotClass: 'bg-emerald-500' },
        inactive: { label: 'Inativo', dotClass: 'bg-muted-foreground' },
        archived: { label: 'Arquivado', dotClass: 'bg-amber-500' },
    };

const STATUS_ORDER: SupplierStatus[] = ['active', 'inactive', 'archived'];

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
    const current = STATUS_META[status];

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
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size={size}
                    className={cn('gap-2', className)}
                >
                    <span className={cn('size-2 rounded-full', current.dotClass)} />
                    {current.label}
                    <ChevronDown className="size-4 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="w-44">
                {STATUS_ORDER.map((value) => {
                    const meta = STATUS_META[value];

                    return (
                        <DropdownMenuItem
                            key={value}
                            onSelect={() => changeStatus(value)}
                            className="gap-2"
                        >
                            <span
                                className={cn('size-2 rounded-full', meta.dotClass)}
                            />
                            <span className="flex-1">{meta.label}</span>
                            {value === status ? <Check className="size-4" /> : null}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
