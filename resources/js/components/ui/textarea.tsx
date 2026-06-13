import * as React from 'react';

import { cn } from '@/lib/utils';

type TextareaProps = React.ComponentProps<'textarea'> & {
    autoResize?: boolean;
};

function Textarea({
    className,
    autoResize = false,
    onInput,
    rows = 4,
    value,
    defaultValue,
    ...props
}: TextareaProps) {
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

    const resize = React.useCallback(
        (textarea: HTMLTextAreaElement | null = textareaRef.current): void => {
            if (!autoResize || !textarea) {
                return;
            }

            textarea.style.height = 'auto';
            textarea.style.height = `${textarea.scrollHeight}px`;
        },
        [autoResize],
    );

    React.useEffect(() => {
        resize();
    }, [defaultValue, resize, value]);

    return (
        <textarea
            ref={textareaRef}
            data-slot="textarea"
            rows={rows}
            value={value}
            defaultValue={defaultValue}
            className={cn(
                'border-input bg-background/60 placeholder:text-muted-foreground flex min-h-24 w-full rounded-md border px-3 py-2 text-base shadow-xs outline-none transition-[border-color,box-shadow,color] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'focus-visible:border-ring focus-visible:ring-ring/15 focus-visible:ring-[2px]',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                autoResize && 'resize-none overflow-hidden',
                className,
            )}
            onInput={(event) => {
                onInput?.(event);
                resize(event.currentTarget);
            }}
            {...props}
        />
    );
}

export { Textarea };
