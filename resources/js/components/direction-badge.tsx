import { TrendingDown, TrendingUp } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type CashDirection = 'receivable' | 'payable' | 'inflow' | 'outflow';

interface DirectionBadgeProps {
    direction: CashDirection;
    label: string;
    className?: string;
    iconOnly?: boolean;
}

function isOutflow(direction: CashDirection): boolean {
    return direction === 'payable' || direction === 'outflow';
}

export function DirectionBadge({
    direction,
    label,
    className,
    iconOnly = false,
}: DirectionBadgeProps) {
    const outflow = isOutflow(direction);
    const Icon = outflow ? TrendingDown : TrendingUp;

    if (iconOnly) {
        return (
            <span
                className={cn(
                    'inline-flex size-8 items-center justify-center',
                    outflow ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400',
                    className,
                )}
                aria-label={label}
                title={label}
            >
                <Icon className="size-4" aria-hidden="true" />
            </span>
        );
    }

    return (
        <Badge
            variant={outflow ? 'outflow' : 'inflow'}
            className={cn('rounded-md', className)}
        >
            <Icon className="size-3" aria-hidden="true" />
            {label}
        </Badge>
    );
}
