import { cn } from '@/lib/utils';

export type MoneyDirection =
    | 'receivable'
    | 'payable'
    | 'inflow'
    | 'outflow'
    | 'neutral';

interface MoneyAmountProps {
    value: number;
    currency?: string;
    direction?: MoneyDirection;
    overdue?: boolean;
    signed?: boolean;
    className?: string;
}

function formatMoney(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function isOutflow(direction: MoneyDirection): boolean {
    return direction === 'payable' || direction === 'outflow';
}

function isInflow(direction: MoneyDirection): boolean {
    return direction === 'receivable' || direction === 'inflow';
}

export function MoneyAmount({
    value,
    currency = 'BRL',
    direction = 'neutral',
    overdue = false,
    signed = false,
    className,
}: MoneyAmountProps) {
    const formatted = formatMoney(value, currency);
    const sign = signed
        ? isInflow(direction)
            ? '+'
            : isOutflow(direction)
              ? '-'
              : ''
        : '';

    return (
        <span
            className={cn(
                'inline-flex items-baseline justify-end gap-0.5 font-tabular',
                overdue
                    ? 'font-semibold text-destructive'
                    : isInflow(direction)
                      ? 'text-inflow'
                      : isOutflow(direction)
                        ? 'text-outflow'
                        : 'text-foreground',
                className,
            )}
        >
            {sign ? (
                <span aria-hidden="true" className="text-[0.85em] opacity-80">
                    {sign}
                </span>
            ) : null}
            <span>{formatted}</span>
        </span>
    );
}
