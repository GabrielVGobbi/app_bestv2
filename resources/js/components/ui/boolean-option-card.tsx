import { Check } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface BooleanOptionCardProps {
    label: string;
    description?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    icon?: ReactNode;
    disabled?: boolean;
    className?: string;
}

export function BooleanOptionCard({
    label,
    description,
    checked,
    onCheckedChange,
    icon,
    disabled = false,
    className,
}: BooleanOptionCardProps) {
    function toggle(): void {
        if (!disabled) {
            onCheckedChange(!checked);
        }
    }

    function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>): void {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggle();
        }
    }

    return (
        <button
            type="button"
            aria-pressed={checked}
            disabled={disabled}
            className={cn(
                'flex w-full items-start gap-3 rounded-md border bg-card p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                checked
                    ? 'border-primary/40 bg-primary/5 ring-1 ring-primary/20'
                    : 'border-border hover:border-primary/30 hover:bg-muted/40',
                className,
            )}
            onClick={toggle}
            onKeyDown={handleKeyDown}
        >
            {icon && (
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-md border',
                        checked
                            ? 'border-primary/30 bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground',
                    )}
                >
                    {icon}
                </span>
            )}
            <span className="min-w-0 flex-1 space-y-1">
                <span className="flex items-center gap-2">
                    <span className="font-medium text-foreground">{label}</span>
                    {checked && <Check className="size-4 text-primary" />}
                </span>
                {description && (
                    <span className="block text-sm leading-5 text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>
            <span
                aria-hidden="true"
                className={cn(
                    'relative mt-1 inline-flex h-5 w-9 shrink-0 items-center rounded-full border transition-colors',
                    checked ? 'border-primary bg-primary' : 'border-border bg-muted',
                )}
            >
                <span
                    className={cn(
                        'size-4 rounded-full bg-background shadow-xs transition-transform',
                        checked ? 'translate-x-4' : 'translate-x-0.5',
                    )}
                />
            </span>
        </button>
    );
}
