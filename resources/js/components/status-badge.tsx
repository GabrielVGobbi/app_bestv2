import {
    Archive,
    BadgeAlert,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    CircleHelp,
    FilePenLine,
    Link2Off,
    PauseCircle,
    PlayCircle,
    Radio,
    SearchX,
    Send,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusPresenter } from '@/types/status';

const colorClasses: Record<string, string> = {
    amber: 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    blue: 'border-blue-500/25 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    emerald:
        'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    red: 'border-red-500/25 bg-red-500/10 text-red-700 dark:text-red-300',
    sky: 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    violet: 'border-violet-500/25 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    zinc: 'border-zinc-500/25 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
};

const icons: Record<string, LucideIcon> = {
    Archive,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    CircleHelp,
    FilePenLine,
    BadgeAlert,
    Link2Off,
    PauseCircle,
    PlayCircle,
    Radio,
    SearchX,
    Send,
    XCircle,
};

interface StatusBadgeProps {
    presenter: StatusPresenter;
    className?: string;
    showIcon?: boolean;
}

export function StatusBadge({
    presenter,
    className,
    showIcon = false,
}: StatusBadgeProps) {
    const Icon = icons[presenter.icon];

    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-md',
                colorClasses[presenter.color] ?? colorClasses.zinc,
                className,
            )}
        >
            {showIcon && Icon ? <Icon className="size-3" /> : null}
            {presenter.label}
        </Badge>
    );
}
