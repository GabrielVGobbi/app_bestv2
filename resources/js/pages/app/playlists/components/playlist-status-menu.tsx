import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

import { updateStatus } from '@/actions/App/Modules/Playlists/Http/Controllers/PlaylistController';
import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { cn } from '@/lib/utils';
import type { PlaylistStatus } from '@/types/app/playlist';

const STATUS_META: Record<PlaylistStatus, { label: string; dotClass: string }> =
    {
        active: { label: 'Ativa', dotClass: 'bg-success' },
        inactive: { label: 'Inativa', dotClass: 'bg-muted-foreground' },
        archived: { label: 'Arquivada', dotClass: 'bg-warning' },
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
            <DropdownTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        size={size}
                        className={cn('gap-2', className)}
                        aria-label={`Status: ${current.label}`}
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2 rounded-full',
                                current.dotClass,
                            )}
                        />
                        {current.label}
                        <ChevronDown className="size-4 text-muted-foreground" />
                    </Button>
                }
            />
            <DropdownContent
                align={align}
                className="w-44"
                checkedIndex={STATUS_ORDER.indexOf(status)}
            >
                {STATUS_ORDER.map((value, index) => (
                    <MenuItem
                        key={value}
                        index={index}
                        label={STATUS_META[value].label}
                        checked={value === status}
                        onSelect={() => changeStatus(value)}
                    />
                ))}
            </DropdownContent>
        </DropdownMenu>
    );
}
