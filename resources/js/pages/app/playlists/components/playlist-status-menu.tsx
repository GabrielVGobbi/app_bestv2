import { router } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { PlaylistStatus } from '@/types/app/playlist';

const STATUS_META: Record<
    PlaylistStatus,
    { label: string; chipClass: string; dotClass: string }
> = {
    active: {
        label: 'Ativa',
        chipClass:
            'border-success/25 bg-success-soft text-success-soft-foreground',
        dotClass: 'bg-emerald-500',
    },
    inactive: {
        label: 'Inativa',
        chipClass: 'border-border/70 bg-secondary text-muted-foreground',
        dotClass: 'bg-muted-foreground',
    },
    archived: {
        label: 'Arquivada',
        chipClass:
            'border-warning/25 bg-warning-soft text-warning-soft-foreground',
        dotClass: 'bg-amber-500',
    },
};

const STATUS_ORDER: PlaylistStatus[] = ['active', 'inactive', 'archived'];

interface PlaylistStatusMenuProps {
    uuid: string;
    status: PlaylistStatus;
    size?: 'sm' | 'default';
    align?: 'start' | 'center' | 'end';
    className?: string;
}

export function PlaylistStatusMenu({
    uuid,
    status,
    size = 'default',
    align = 'start',
    className,
}: PlaylistStatusMenuProps) {
    const current = STATUS_META[status];

    function changeStatus(next: PlaylistStatus): void {
        if (next === status) {
            return;
        }

        router.patch(
            updateStatus.url(uuid),
            { status: next },
            { preserveScroll: true },
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex items-center gap-1 rounded-md border font-semibold tracking-[0.01em] transition-colors hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        size === 'sm'
                            ? 'px-2 py-0.5 text-[11px]'
                            : 'px-2.5 py-1 text-xs',
                        current.chipClass,
                        className,
                    )}
                    aria-label={`Status da playlist: ${current.label}`}
                >
                    {current.label}
                    <ChevronDown className="size-3 opacity-70" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="w-44">
                {STATUS_ORDER.map((value) => {
                    const meta = STATUS_META[value];

                    return (
                        <DropdownMenuItem
                            key={value}
                            onSelect={() => changeStatus(value)}
                            className="gap-2"
                        >
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    meta.dotClass,
                                )}
                            />
                            <span className="flex-1">{meta.label}</span>
                            {value === status ? (
                                <Check className="size-4" />
                            ) : null}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
