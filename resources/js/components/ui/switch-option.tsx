import * as React from 'react';

import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type SwitchOptionProps = Omit<React.ComponentProps<'label'>, 'onChange'> & {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    title: string;
    description?: string;
    icon?: React.ReactNode;
    disabled?: boolean;
};

function SwitchOption({
    checked,
    onCheckedChange,
    title,
    description,
    icon,
    disabled = false,
    className,
    ...props
}: SwitchOptionProps) {
    const generatedId = React.useId();
    const switchId = props.htmlFor ?? generatedId;
    const descriptionId = description ? `${switchId}-description` : undefined;

    return (
        <label
            htmlFor={switchId}
            className={cn(
                'group flex min-h-16 cursor-pointer items-center justify-between gap-4 rounded-lg border bg-card px-4 py-3 text-sm shadow-xs transition-[border-color,background-color,box-shadow]',
                'hover:border-primary/30 hover:bg-secondary/40',
                'focus-within:border-ring focus-within:ring-[2px] focus-within:ring-ring/15',
                checked && 'border-primary/40 bg-primary/5',
                disabled && 'cursor-not-allowed opacity-60',
                className,
            )}
            {...props}
        >
            <span className="flex min-w-0 items-start gap-3">
                {icon ? (
                    <span
                        className={cn(
                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground transition-colors',
                            checked && 'border-primary/30 bg-primary/10 text-primary',
                        )}
                        aria-hidden="true"
                    >
                        {icon}
                    </span>
                ) : null}

                <span className="min-w-0 space-y-0.5">
                    <span className="block font-medium text-foreground">{title}</span>
                    {description ? (
                        <span id={descriptionId} className="block text-sm leading-snug text-muted-foreground">
                            {description}
                        </span>
                    ) : null}
                </span>
            </span>

            <Switch
                id={switchId}
                checked={checked}
                disabled={disabled}
                aria-describedby={descriptionId}
                onCheckedChange={onCheckedChange}
                className="shrink-0"
            />
        </label>
    );
}

export { SwitchOption };
