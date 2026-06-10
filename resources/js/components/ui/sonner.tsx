import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-center"
            richColors
            closeButton
            toastOptions={{
                classNames: {
                    toast: 'rounded-xl border shadow-lg backdrop-blur-sm',
                    title: 'text-sm font-semibold',
                    description: 'text-xs opacity-90',
                    actionButton: 'rounded-lg font-medium',
                    cancelButton: 'rounded-lg',
                    success:
                        'border-emerald-500/30 bg-emerald-50 text-emerald-950 dark:bg-emerald-950/90 dark:text-emerald-50',
                    error:
                        'border-red-500/30 bg-red-50 text-red-950 dark:bg-red-950/90 dark:text-red-50',
                    warning:
                        'border-amber-500/40 bg-amber-50 text-amber-950 dark:bg-amber-950/90 dark:text-amber-50',
                    info: 'border-sky-500/30 bg-sky-50 text-sky-950 dark:bg-sky-950/90 dark:text-sky-50',
                    loading:
                        'border-muted-foreground/20 bg-popover text-popover-foreground',
                    default:
                        'border-border bg-popover text-popover-foreground',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
