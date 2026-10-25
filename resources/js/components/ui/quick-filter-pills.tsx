import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import {
    type QuickFilterTone,
    quickFilterPillClassName,
} from './quick-filter-pill-classes';

export type { QuickFilterTone };

export interface QuickFilterPillOption<T extends string> {
    value: T;
    label: string;
    icon?: LucideIcon;
    tone?: QuickFilterTone;
    disabled?: boolean;
    badge?: ReactNode;
    ariaLabel?: string;
    className?: string;
}

interface QuickFilterPillsProps<T extends string> {
    value: T;
    options: ReadonlyArray<QuickFilterPillOption<T>>;
    onChange: (value: T) => void;
    label?: string;
}

export function QuickFilterPills<T extends string>({
    value,
    options,
    onChange,
    label = 'Filtro rápido:',
}: QuickFilterPillsProps<T>) {
    return (
        <div
            role="group"
            aria-label={label}
            className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-muted/50 px-3 py-2"
        >
            <span className="mr-1 text-sm font-semibold text-muted-foreground">
                {label}
            </span>
            {options.map((option) => {
                const Icon = option.icon;
                const isActive = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={isActive}
                        aria-label={option.ariaLabel ?? option.label}
                        disabled={option.disabled}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'inline-flex h-9 items-center gap-1.5 rounded-full border px-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50',
                            quickFilterPillClassName(isActive, option.tone),
                            option.className,
                        )}
                    >
                        {Icon && <Icon className="size-3.5" />}
                        {option.label}
                        {option.badge}
                    </button>
                );
            })}
        </div>
    );
}
