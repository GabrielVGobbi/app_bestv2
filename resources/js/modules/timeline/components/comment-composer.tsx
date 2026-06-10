import { SendHorizontal, Type } from 'lucide-react';
import { lazy, Suspense, useId, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

import type { CommentPayload } from '../api/types';

const LazyRichTextEditor = lazy(() =>
    import('@/components/rich-text-editor').then((module) => ({
        default: module.RichTextEditor,
    })),
);

interface CommentComposerProps {
    label?: string;
    placeholder?: string;
    submitLabel?: string;
    initialValue?: string;
    initialFormat?: 'plain' | 'html';
    disabled?: boolean;
    autoFocus?: boolean;
    compact?: boolean;
    onCancel?: () => void;
    onSubmit: (payload: CommentPayload) => Promise<void> | void;
}

export function CommentComposer({
    label = 'Comentario',
    placeholder = 'Adicionar comentario...',
    submitLabel = 'Enviar',
    initialValue = '',
    initialFormat = 'plain',
    disabled = false,
    autoFocus = false,
    compact = false,
    onCancel,
    onSubmit,
}: CommentComposerProps) {
    const id = useId();
    const [body, setBody] = useState(initialValue);
    const [bodyFormat, setBodyFormat] = useState<'plain' | 'html'>(
        initialFormat,
    );
    const [submitting, setSubmitting] = useState(false);

    const plainText = useMemo(
        () => textFromBody(body, bodyFormat),
        [body, bodyFormat],
    );
    const canSubmit = plainText.length > 0 && !disabled && !submitting;

    async function submit(): Promise<void> {
        if (!canSubmit) {
            return;
        }

        setSubmitting(true);

        try {
            await onSubmit({
                body: bodyFormat === 'html' ? body : body.trim(),
                body_format: bodyFormat,
            });
            setBody('');
            setBodyFormat('plain');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div
            className={cn(
                'space-y-2 rounded-lg border border-border/70 bg-muted/20 p-3 transition-colors focus-within:border-ring/60 focus-within:bg-background focus-within:ring-2 focus-within:ring-ring/10',
                compact && 'rounded-md bg-background/80 p-2.5',
            )}
        >
            <Label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </Label>
            {bodyFormat === 'html' ? (
                <Suspense
                    fallback={
                        <div className="min-h-18 rounded-md border border-border/70 bg-background/70 px-3 py-2 text-sm text-muted-foreground">
                            Carregando editor...
                        </div>
                    }
                >
                    <LazyRichTextEditor
                        value={body}
                        onChange={setBody}
                        placeholder={placeholder}
                        disabled={disabled || submitting}
                        minHeight={compact ? 88 : 104}
                        toolbarPreset="timeline"
                        allowImages={false}
                    />
                </Suspense>
            ) : (
                <textarea
                    id={id}
                    value={body}
                    disabled={disabled || submitting}
                    autoFocus={autoFocus}
                    placeholder={placeholder}
                    rows={compact ? 2 : 3}
                    onChange={(event) => setBody(event.target.value)}
                    onKeyDown={(event) => {
                        if (
                            (event.ctrlKey || event.metaKey) &&
                            event.key === 'Enter'
                        ) {
                            event.preventDefault();
                            void submit();
                        }
                    }}
                    className="min-h-18 w-full resize-y rounded-md border border-transparent bg-transparent px-0 py-1 text-sm leading-relaxed shadow-none transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-transparent focus-visible:ring-0 disabled:cursor-not-allowed disabled:opacity-50"
                />
            )}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/50 pt-2">
                <Button
                    type="button"
                    variant={bodyFormat === 'html' ? 'secondary' : 'ghost'}
                    size="sm"
                    className="h-7 gap-1.5 px-2 text-xs text-muted-foreground hover:text-foreground"
                    disabled={disabled || submitting}
                    onClick={() => {
                        setBodyFormat((current) =>
                            current === 'html' ? 'plain' : 'html',
                        );
                    }}
                >
                    <Type className="size-3.5" />
                    {bodyFormat === 'html' ? 'Rich text' : 'Texto'}
                </Button>
                <div className="flex items-center justify-end gap-2">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            disabled={submitting}
                            onClick={onCancel}
                        >
                            Cancelar
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        className="h-7 gap-1.5 rounded-full px-3 text-xs"
                        disabled={!canSubmit}
                        onClick={() => void submit()}
                    >
                        <SendHorizontal className="size-3.5" />
                        {submitting ? 'Enviando' : submitLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function textFromBody(body: string, format: 'plain' | 'html'): string {
    if (format === 'plain') {
        return body.trim();
    }

    if (typeof document === 'undefined') {
        return body.replace(/<[^>]*>/g, '').trim();
    }

    const element = document.createElement('div');
    element.innerHTML = body;

    return (element.textContent ?? '').trim();
}
