import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type Props = { className?: string };

export function BrandWordmark({ className }: Props) {
    const { branding } = usePage().props;

    return (
        <span
            title={branding.name}
            className={cn(
                'block max-w-40 truncate font-brand text-[15px] leading-none font-bold tracking-tight',
                className,
            )}
        >
            {branding.name}
        </span>
    );
}
