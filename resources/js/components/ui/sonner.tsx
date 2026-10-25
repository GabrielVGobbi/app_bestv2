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
            closeButton
            toastOptions={{
                classNames: {
                    toast: 'rounded-xl border shadow-lg backdrop-blur-sm',
                    title: 'text-sm font-semibold',
                    description: 'text-xs opacity-90',
                    actionButton: 'rounded-lg font-medium',
                    cancelButton: 'rounded-lg',
                    success:
                        'border-success-soft-foreground/20 bg-success-soft text-success-soft-foreground',
                    error: 'border-destructive-soft-foreground/20 bg-destructive-soft text-destructive-soft-foreground',
                    warning:
                        'border-warning-soft-foreground/20 bg-warning-soft text-warning-soft-foreground',
                    info: 'border-info-soft-foreground/20 bg-info-soft text-info-soft-foreground',
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
