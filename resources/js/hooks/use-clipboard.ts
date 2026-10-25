// Credit: https://usehooks-ts.com/
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);
    const lifetime = useRef({ mounted: false, attempt: 0 });

    useEffect(() => {
        const active = lifetime.current;
        active.mounted = true;

        return () => {
            active.mounted = false;
            ++active.attempt;
        };
    }, []);

    const copy: CopyFn = useCallback(async (text) => {
        const active = lifetime.current;

        if (!active.mounted) {
            return false;
        }

        const attempt = ++active.attempt;
        const isCurrent = () => active.mounted && active.attempt === attempt;
        setCopiedText(null);

        try {
            const clipboard =
                typeof navigator === 'undefined'
                    ? undefined
                    : navigator.clipboard;

            if (typeof clipboard?.writeText !== 'function') {
                throw new Error('Clipboard unavailable');
            }

            await clipboard.writeText(text);

            if (!isCurrent()) {
                return false;
            }

            setCopiedText(text);

            return true;
        } catch {
            if (!isCurrent()) {
                return false;
            }

            setCopiedText(null);
            toast.error('Não foi possível copiar.', {
                description:
                    'Selecione o texto e copie manualmente com Ctrl+C ou Command+C.',
            });

            return false;
        }
    }, []);

    return [copiedText, copy];
}
