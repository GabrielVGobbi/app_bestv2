import { Check, Loader2, Pencil, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type CellEditMode = 'inline' | 'popover' | 'modal';

export interface CellEditorRenderProps<TValue> {
    value: TValue;
    onChange: (value: TValue) => void;
    save: () => Promise<void>;
    cancel: () => void;
    isLoading: boolean;
    error: string | null;
}

export interface InlineCellEditorProps<TValue> {
    mode?: CellEditMode;
    value: TValue;
    onSave: (value: TValue) => Promise<void> | void;
    children: (props: CellEditorRenderProps<TValue>) => ReactNode;
    displayValue?: ReactNode;
    label?: string;
    disabled?: boolean;
    showEditIcon?: boolean;
    triggerClassName?: string;
    editorClassName?: string;
    popoverWidth?: number;
    align?: 'start' | 'center' | 'end';
    modalTitle?: string;
    modalDescription?: string;
    saveLabel?: string;
    cancelLabel?: string;
    validate?: (value: TValue) => string | null | undefined;
}

function errorMessage(error: unknown): string {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return 'Nao foi possivel salvar a alteracao.';
}

export function InlineCellEditor<TValue>({
    mode = 'inline',
    value,
    onSave,
    children,
    displayValue,
    label = 'Editar celula',
    disabled = false,
    showEditIcon = true,
    triggerClassName,
    editorClassName,
    popoverWidth = 280,
    align = 'start',
    modalTitle,
    modalDescription,
    saveLabel = 'Salvar',
    cancelLabel = 'Cancelar',
    validate,
}: InlineCellEditorProps<TValue>) {
    const [open, setOpen] = useState(false);
    const [draftValue, setDraftValue] = useState<TValue>(value);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const renderedDisplayValue = useMemo(
        () => displayValue ?? <span>{String(value ?? '')}</span>,
        [displayValue, value],
    );

    const cancel = () => {
        if (isLoading) {
            return;
        }

        setDraftValue(value);
        setError(null);
        setOpen(false);
    };

    const save = async () => {
        const validationError = validate?.(draftValue);

        if (validationError) {
            setError(validationError);

            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            await onSave(draftValue);

            setOpen(false);
        } catch (caughtError) {
            setError(errorMessage(caughtError));
        } finally {
            setIsLoading(false);
        }
    };

    const renderEditor = () => (
        <div className={cn('grid gap-2', editorClassName)}>
            {children({
                value: draftValue,
                onChange: setDraftValue,
                save,
                cancel,
                isLoading,
                error,
            })}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );

    const renderActions = () => (
        <div className="flex items-center justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={isLoading}
                onClick={cancel}
            >
                <X className="size-4" />
                {cancelLabel}
            </Button>
            <Button
                type="button"
                size="sm"
                disabled={isLoading}
                onClick={() => void save()}
            >
                {isLoading ? (
                    <Loader2 className="size-4 animate-spin" />
                ) : (
                    <Check className="size-4" />
                )}
                {saveLabel}
            </Button>
        </div>
    );

    const trigger = (
        <button
            type="button"
            disabled={disabled}
            onClick={() => {
                setDraftValue(value);
                setError(null);
                setOpen(true);
            }}
            className={cn(
                'group/cell inline-flex min-h-8 w-full min-w-0 items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition-colors outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-60',
                triggerClassName,
            )}
        >
            <span className="min-w-0 truncate">{renderedDisplayValue}</span>
            {showEditIcon && (
                <Pencil className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/cell:opacity-100 group-focus-visible/cell:opacity-100" />
            )}
        </button>
    );

    if (mode === 'inline') {
        return open ? (
            <div className="min-w-0 space-y-2 py-2">
                {renderEditor()}
                {renderActions()}
            </div>
        ) : (
            trigger
        );
    }

    if (mode === 'popover') {
        return (
            <Popover
                open={open}
                onOpenChange={(nextOpen) =>
                    nextOpen ? setOpen(true) : cancel()
                }
            >
                <PopoverTrigger asChild>{trigger}</PopoverTrigger>
                <PopoverContent
                    align={align}
                    className="grid gap-3 p-3"
                    style={{ width: popoverWidth }}
                >
                    <div className="text-sm font-medium">{label}</div>
                    {renderEditor()}
                    {renderActions()}
                </PopoverContent>
            </Popover>
        );
    }

    return (
        <>
            {trigger}
            <Dialog
                open={open}
                onOpenChange={(nextOpen) =>
                    nextOpen ? setOpen(true) : cancel()
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{modalTitle ?? label}</DialogTitle>
                        {modalDescription && (
                            <DialogDescription>
                                {modalDescription}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    {renderEditor()}
                    <DialogFooter>{renderActions()}</DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
