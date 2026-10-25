import { cn } from '@/lib/utils';

type Props = { className?: string };

/** Símbolo do kit: dois blocos arredondados sobrepostos. Herda a cor do texto. */
export function BrandMark({ className }: Props) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className={cn('size-6 shrink-0', className)}
        >
            <rect
                x="9"
                y="2"
                width="13"
                height="13"
                rx="3.5"
                fill="currentColor"
                opacity="0.55"
            />
            <rect
                x="2"
                y="9"
                width="13"
                height="13"
                rx="3.5"
                fill="currentColor"
            />
        </svg>
    );
}
