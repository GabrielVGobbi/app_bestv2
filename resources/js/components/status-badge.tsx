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
    amber: 'border-warning/25 bg-warning-soft text-warning-soft-foreground',
    orange: 'border-warning/25 bg-warning-soft text-warning-soft-foreground',
    yellow: 'border-warning/25 bg-warning-soft text-warning-soft-foreground',
    emerald: 'border-success/25 bg-success-soft text-success-soft-foreground',
    green: 'border-success/25 bg-success-soft text-success-soft-foreground',
    red: 'border-destructive/25 bg-destructive-soft text-destructive-soft-foreground',
    rose: 'border-destructive/25 bg-destructive-soft text-destructive-soft-foreground',
    blue: 'border-info/25 bg-info-soft text-info-soft-foreground',
    sky: 'border-info/25 bg-info-soft text-info-soft-foreground',
    cyan: 'border-info/25 bg-info-soft text-info-soft-foreground',
    indigo: 'border-info/25 bg-info-soft text-info-soft-foreground',
    violet: 'border-info/25 bg-info-soft text-info-soft-foreground',
    zinc: 'border-border/70 bg-secondary text-muted-foreground',
    slate: 'border-border/70 bg-secondary text-muted-foreground',
    stone: 'border-border/70 bg-secondary text-muted-foreground',
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
            className={cn(
                'rounded-md',
                colorClasses[presenter.color] ?? colorClasses.zinc,
                className,
            )}
        >
            {showIcon && Icon ? (
                <Icon className={cn('size-3', shouldSpin && 'animate-spin')} />
            ) : null}
            {presenter.label}
        </Badge>
    );
}
