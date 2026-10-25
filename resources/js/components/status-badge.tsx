import {
    AlertCircle,
    AlertTriangle,
    Archive,
    BadgeAlert,
    CalendarClock,
    CalendarDays,
    Check,
    CheckCircle2,
    Circle,
    CircleDashed,
    CircleDollarSign,
    CircleHelp,
    CircleSlash,
    Clock,
    Clock3,
    Eye,
    FilePenLine,
    Link2Off,
    ListMinus,
    ListPlus,
    Loader2,
    Mail,
    MousePointerClick,
    PauseCircle,
    PlayCircle,
    Radio,
    RefreshCw,
    Replace,
    RotateCcw,
    SearchX,
    Send,
    ShieldOff,
    Sparkles,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusPresenter } from '@/types/status';

const colorClasses: Record<string, string> = {
    amber: 'text-warning',
    orange: 'text-warning',
    yellow: 'text-warning',
    emerald: 'text-success',
    green: 'text-success',
    red: 'text-destructive',
    rose: 'text-destructive',
    blue: 'text-info',
    sky: 'text-info',
    cyan: 'text-info',
    indigo: 'text-info',
    violet: 'text-info',
    zinc: 'text-muted-foreground',
    slate: 'text-muted-foreground',
    stone: 'text-muted-foreground',
};

const icons: Record<string, LucideIcon> = {
    AlertCircle,
    AlertTriangle,
    Archive,
    BadgeAlert,
    CalendarClock,
    CalendarDays,
    Check,
    CheckCircle2,
    Circle,
    CircleDashed,
    CircleDollarSign,
    CircleHelp,
    CircleSlash,
    Clock,
    Clock3,
    Eye,
    FilePenLine,
    Link2Off,
    ListMinus,
    ListPlus,
    Loader2,
    Mail,
    MousePointerClick,
    PauseCircle,
    PlayCircle,
    Radio,
    RefreshCw,
    Replace,
    RotateCcw,
    SearchX,
    Send,
    ShieldOff,
    Sparkles,
    XCircle,
};

const spinningIcons = new Set(['Loader2']);

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
    const shouldSpin = spinningIcons.has(presenter.icon);

    return (
        <Badge
            variant="outline"
            className={cn('rounded-md bg-card text-foreground', className)}
        >
            {showIcon && Icon ? (
                <Icon
                    className={cn(
                        'size-3',
                        colorClasses[presenter.color] ?? colorClasses.zinc,
                        shouldSpin && 'animate-spin',
                    )}
                />
            ) : (
                <span
                    aria-hidden="true"
                    className={cn(
                        'size-1.5 shrink-0 rounded-full bg-current',
                        colorClasses[presenter.color] ?? colorClasses.zinc,
                    )}
                />
            )}
            {presenter.label}
        </Badge>
    );
}
