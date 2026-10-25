import { usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/brand/brand-mark';
import { BrandWordmark } from '@/components/brand/brand-wordmark';
import { cn } from '@/lib/utils';

type Props = {
    /** Mostra o nome ao lado do símbolo. */
    showText?: boolean;
    className?: string;
};

export default function AppLogo({ showText = true, className }: Props) {
    const { branding } = usePage().props;
    const fullLogo = branding.logo.light || branding.logo.dark;

    if (showText && fullLogo) {
        return (
            <span className={cn('flex h-8 min-w-0 items-center', className)}>
                <img
                    src={branding.logo.light ?? branding.logo.dark ?? ''}
                    alt={branding.name}
                    className={cn(
                        'h-8 w-auto max-w-full object-contain object-left',
                        branding.logo.dark && 'dark:hidden',
                    )}
                />
                {branding.logo.dark && (
                    <img
                        src={branding.logo.dark}
                        alt={branding.name}
                        className="hidden h-8 w-auto max-w-full object-contain object-left dark:block"
                    />
                )}
            </span>
        );
    }

    return (
        <span className={cn('flex items-center gap-2.5', className)}>
            {branding.logo.mark ? (
                <img
                    src={branding.logo.mark}
                    alt=""
                    className="size-7 shrink-0"
                />
            ) : (
                <BrandMark className="size-7" />
            )}
            {showText && <BrandWordmark />}
            {/* The mark is decorative; keep the logo link named when the wordmark is hidden. */}
            {!showText && <span className="sr-only">{branding.name}</span>}
        </span>
    );
}
