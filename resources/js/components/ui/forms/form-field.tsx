import * as React from 'react';

import { cn } from '@/lib/utils';

import { Label } from '../label';

type FormFieldVariant = 'stacked' | 'inline' | 'overlap' | 'inset';

interface FormFieldProps {
    label?: string;
    required?: boolean;
    children: React.ReactNode;
    error?: string;
    description?: string;
    id?: string;
    className?: string;
    variant?: FormFieldVariant;
}

function getChildId(children: React.ReactNode): string | undefined {
    const child = React.Children.toArray(children)[0];

    if (React.isValidElement<{ id?: string }>(child)) {
        return child.props.id;
    }

    return undefined;
}

function withFieldInputClasses(
    children: React.ReactNode,
    className: string,
): React.ReactNode {
    return React.Children.map(children, (child) => {
        if (!React.isValidElement<{ className?: string }>(child)) {
            return child;
        }

        return React.cloneElement(child, {
            className: cn(child.props.className, className),
        });
    });
}

function RequiredMark({ show }: { show?: boolean }) {
    if (!show) {
        return null;
    }

    return <span className="text-destructive">*</span>;
}

function FieldLabel({
    id,
    label,
    required,
    className,
}: Pick<FormFieldProps, 'label' | 'required'> & {
    id?: string;
    className?: string;
}) {
    if (!label) {
        return null;
    }

    return (
        <Label htmlFor={id} className={className}>
            {label} <RequiredMark show={required} />
        </Label>
    );
}

function FieldMessage({
    error,
    description,
}: Pick<FormFieldProps, 'error' | 'description'>) {
    if (error) {
        return <p className="text-sm text-destructive">{error}</p>;
    }

    if (description) {
        return <p className="text-sm text-muted-foreground">{description}</p>;
    }

    return null;
}

function FormField({
    label,
    required,
    children,
    error,
    description,
    id,
    className,
    variant = 'stacked',
}: FormFieldProps) {
    const fieldId = id ?? getChildId(children);

    if (variant === 'inline') {
        return (
            <div
                className={cn(
                    'grid gap-2 sm:grid-cols-[10rem_1fr] sm:items-start',
                    className,
                )}
            >
                <FieldLabel
                    id={fieldId}
                    label={label}
                    required={required}
                    className="pt-2 text-sm font-semibold leading-none text-foreground peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                />
                <div className="space-y-2">
                    <div aria-invalid={!!error}>{children}</div>
                    <FieldMessage error={error} description={description} />
                </div>
            </div>
        );
    }

    if (variant === 'overlap') {
        return (
            <div className={cn('space-y-2', className)}>
                <div className="relative">
                    <FieldLabel
                        id={fieldId}
                        label={label}
                        required={required}
                        className="bg-background absolute -top-2 left-2 z-10 inline-flex items-center gap-1 rounded-md px-1 text-xs font-medium text-foreground"
                    />
                    <div aria-invalid={!!error}>{children}</div>
                </div>
                <FieldMessage error={error} description={description} />
            </div>
        );
    }

    if (variant === 'inset') {
        return (
            <div className={cn('space-y-2', className)}>
                <div
                    className={cn(
                        'rounded-md border border-input bg-background px-3 pt-2 pb-1.5 shadow-xs transition-[border-color,box-shadow]',
                        'focus-within:border-ring focus-within:ring-ring/15 focus-within:ring-[2px]',
                        error && 'border-destructive ring-destructive/20',
                    )}
                    aria-invalid={!!error}
                >
                    <FieldLabel
                        id={fieldId}
                        label={label}
                        required={required}
                        className="mb-1 block text-xs font-medium leading-none text-muted-foreground"
                    />
                    {withFieldInputClasses(
                        children,
                        'h-7 border-0 bg-transparent px-0 py-0 shadow-none focus-visible:border-transparent focus-visible:ring-0',
                    )}
                </div>
                <FieldMessage error={error} description={description} />
            </div>
        );
    }

    return (
        <div className={cn('space-y-2', className)}>
            <FieldLabel
                id={fieldId}
                label={label}
                required={required}
                className="text-sm font-semibold leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
            />
            <div aria-invalid={!!error}>{children}</div>
            <FieldMessage error={error} description={description} />
        </div>
    );
}

export default FormField;
