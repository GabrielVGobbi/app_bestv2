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

/** Cores dos presenters do backend mapeadas para as tintas suaves do DESIGN.md. */
const variants: Record<
    string,
    'default' | 'secondary' | 'success' | 'warning' | 'destructive' | 'info'
> = {
    amber: 'warning',
    blue: 'info',
    emerald: 'success',
    red: 'destructive',
    sky: 'info',
    violet: 'default',
    zinc: 'secondary',
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
            variant={variants[presenter.color] ?? 'secondary'}
            className={cn('rounded-md', className)}
        >
            {showIcon && Icon ? <Icon className="size-3" /> : null}
            {presenter.label}
        </Badge>
    );
}
