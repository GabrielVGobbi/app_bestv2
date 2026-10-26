import type { LucideIcon } from 'lucide-react';
import * as React from 'react';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ActionInputProps = React.ComponentProps<'input'> & {
    actionLabel: string;
    actionAriaLabel?: string;
    actionIcon?: LucideIcon;
    actionLoading?: boolean;
    actionLoadingIcon?: React.ReactNode;
    actionDisabled?: boolean;
    actionClassName?: string;
    onAction: () => void;
};

const ActionInput = React.forwardRef<HTMLInputElement, ActionInputProps>(
    (
        {
            className,
            type,
            actionLabel,
            actionAriaLabel,
            actionIcon: ActionIcon,
            actionLoading = false,
            actionLoadingIcon,
            actionDisabled = false,
            actionClassName,
            disabled,
            onAction,
            ...props
        },
        ref,
    ) => {
        return (
            <div
                className={cn(
                    'flex h-9 w-full min-w-0 items-center overflow-hidden rounded-sm border border-input-soft bg-input-surface transition-[border-color,box-shadow,color]',
                    'hover:border-input focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/15',
                    'has-[input[aria-invalid=true]]:border-destructive has-[input[aria-invalid=true]]:ring-destructive/20 dark:has-[input[aria-invalid=true]]:ring-destructive/40',
                    disabled && 'cursor-not-allowed opacity-50',
                    className,
                )}
            >
                <input
                    ref={ref}
                    type={type}
                    data-slot="action-input"
                    disabled={disabled}
                    className="h-full min-w-0 flex-1 bg-transparent px-3 py-1 text-base outline-none placeholder:text-muted-foreground selection:bg-primary/10 selection:text-primary-foreground disabled:pointer-events-none md:text-sm"
                    {...props}
                />
                <button
                    type="button"
                    aria-label={actionAriaLabel ?? actionLabel}
                    disabled={disabled || actionDisabled || actionLoading}
                    onClick={onAction}
                    className={cn(
                        buttonVariants({ variant: 'ghost', size: 'sm' }),
                        'mr-1 h-8 rounded-sm border-l border-input-soft px-2.5 text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-60',
                        actionClassName,
                    )}
                >
                    {actionLoading && actionLoadingIcon ? actionLoadingIcon : ActionIcon ? <ActionIcon className="size-4" /> : null}
                    <span className="hidden sm:inline">{actionLabel}</span>
                </button>
            </div>
        );
    },
);

ActionInput.displayName = 'ActionInput';

export { ActionInput };
