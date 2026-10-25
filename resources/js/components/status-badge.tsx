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

/** Cores dos presenters do backend mapeadas para as tintas suaves do DESIGN.md. */
const variants: Record<
    string,
    'default' | 'secondary' | 'success' | 'warning' | 'destructive' | 'info'
> = {
    amber: 'warning',
    orange: 'warning',
    yellow: 'warning',
    blue: 'info',
    sky: 'info',
    cyan: 'info',
    indigo: 'info',
    emerald: 'success',
    green: 'success',
    red: 'destructive',
    rose: 'destructive',
    violet: 'default',
    zinc: 'secondary',
    slate: 'secondary',
    stone: 'secondary',
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
            variant={variants[presenter.color] ?? 'secondary'}
            className={cn('rounded-md', className)}
        >
            {showIcon && Icon ? (
                <Icon className={cn('size-3', shouldSpin && 'animate-spin')} />
            ) : null}
            {presenter.label}
        </Badge>
    );
}
