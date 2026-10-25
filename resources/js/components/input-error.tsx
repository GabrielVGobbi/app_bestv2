import { AlertCircle } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** Props for the control whose error is rendered by `<InputError field={field} />`. */
export function errorProps(field: string, message?: string) {
    return message
        ? { 'aria-invalid': true, 'aria-describedby': `${field}-error` }
        : {};
}

export default function InputError({
    message,
    field,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & {
    message?: string;
    field?: string;
}) {
    return message ? (
        <p
            id={field && `${field}-error`}
            {...props}
            className={cn(
                'flex items-start gap-1.5 text-xs text-destructive-soft-foreground',
                className,
            )}
        >
            <AlertCircle
                aria-hidden="true"
                className="mt-px size-3.5 shrink-0"
            />
            {message}
        </p>
    ) : null;
}
