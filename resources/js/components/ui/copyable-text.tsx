import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

interface CopyableTextProps {
    value: string | null | undefined;
    label?: string;
    copyValue?: string | null | undefined;
    emptyValue?: ReactNode;
    children?: ReactNode;
    className?: string;
}

function normalizeValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value.trim() : '';
}

export function CopyableText({
    value,
    label = 'Valor',
    copyValue,
    emptyValue = '-',
    children,
    className,
}: CopyableTextProps) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);
    const timeoutRef = useRef<number | null>(null);

    const displayValue = normalizeValue(value);
    const resolvedCopyValue = normalizeValue(copyValue ?? displayValue);
    const canCopy = resolvedCopyValue !== '';

    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                window.clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    async function handleCopy(): Promise<void> {
        if (!canCopy) {
            return;
        }

        const copiedToClipboard = await copy(resolvedCopyValue);

        if (!copiedToClipboard) {
            toast.error(
                `Não foi possível copiar ${label.toLocaleLowerCase('pt-BR')}.`,
            );

            return;
        }

        setCopied(true);
        toast.success(`${label} copiado.`);

        if (timeoutRef.current) {
            window.clearTimeout(timeoutRef.current);
        }

        timeoutRef.current = window.setTimeout(() => {
            setCopied(false);
        }, 1600);
    }

    return (
        <button
            type="button"
            disabled={!canCopy}
            onClick={handleCopy}
            aria-label={`Copiar ${label.toLocaleLowerCase('pt-BR')}`}
            className={cn(
                'group/copyable inline-flex min-w-0 items-center gap-2 rounded-md text-left transition-colors',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                canCopy
                    ? 'cursor-pointer hover:bg-secondary/60'
                    : 'cursor-default',
                className,
            )}
        >
            <span className="min-w-0 flex-1">
                {children ?? (displayValue || emptyValue)}
            </span>
            {canCopy && (
                <span
                    aria-hidden="true"
                    className="shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/copyable:opacity-100 group-focus-visible/copyable:opacity-100"
                >
                    {copied ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                </span>
            )}
        </button>
    );
}
