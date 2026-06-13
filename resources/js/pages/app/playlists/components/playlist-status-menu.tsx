import { router } from '@inertiajs/react';
import { Check, ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { PlaylistStatus } from '@/types/app/playlist';

const STATUS_META: Record<PlaylistStatus, { label: string; dotClass: string }> =
    {
        active: { label: 'Ativa', dotClass: 'bg-emerald-500' },
        inactive: { label: 'Inativa', dotClass: 'bg-muted-foreground' },
        archived: { label: 'Arquivada', dotClass: 'bg-amber-500' },
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
                <Button
                    type="button"
                    variant="outline"
                    size={size}
                    className={cn('gap-2', className)}
                >
                    <span
                        className={cn('size-2 rounded-full', current.dotClass)}
                    />
                    {current.label}
                    <ChevronDown className="size-4 text-muted-foreground" />
                </Button>
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
