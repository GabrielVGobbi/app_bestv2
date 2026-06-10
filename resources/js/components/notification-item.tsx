import {
    AlertCircle,
    AlertTriangle,
    Bell,
    CheckCircle,
    Heart,
    Info,
    MessageSquare,
    Settings,
    User,
    UserPlus,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Notification, NotificationAction } from '@/types/notification';
import { cn } from '@/lib/utils';

interface NotificationItemProps {
    notification: Notification;
    onMarkAsRead?: (uuid: string) => void;
    onDelete?: (uuid: string) => void;
    onExecuteAction?: (uuid: string, actionType: string, payload?: Record<string, unknown>) => Promise<unknown>;
    compact?: boolean;
}

const TYPE_ICONS: Record<string, React.ElementType> = {
    system: Settings,
    user: User,
    mention: MessageSquare,
    social: UserPlus,
    action: Zap,
    warning: AlertTriangle,
    error: AlertCircle,
    success: CheckCircle,
    info: Info,
};

const CATEGORY_ICONS: Record<string, React.ElementType> = {
    like: Heart,
    follow: UserPlus,
    comment: MessageSquare,
    mention: MessageSquare,
};

const TYPE_COLORS: Record<string, string> = {
    system: 'text-muted-foreground',
    user: 'text-blue-500',
    mention: 'text-purple-500',
    social: 'text-pink-500',
    action: 'text-orange-500',
    warning: 'text-yellow-500',
    error: 'text-destructive',
    success: 'text-green-500',
    info: 'text-sky-500',
};

const VARIANT_MAP: Record<string, 'default' | 'destructive' | 'outline' | 'secondary'> = {
    primary: 'default',
    destructive: 'destructive',
    secondary: 'secondary',
    outline: 'outline',
    ghost: 'outline',
    success: 'default',
    warning: 'secondary',
};

function formatRelativeTime(dateString: string): string {
    const diff = Date.now() - new Date(dateString).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'agora';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    return `${days}d`;
}

interface ActionButtonProps {
    action: NotificationAction;
    disabled: boolean;
    onConfirm: () => void;
}

function ActionButton({ action, disabled, onConfirm }: ActionButtonProps) {
    const [confirming, setConfirming] = useState(false);

    const handleClick = () => {
        if (action.requires_confirmation && !confirming) {
            setConfirming(true);
            return;
        }
        setConfirming(false);
        onConfirm();
    };

    return (
        <div className="flex flex-col gap-1">
            {confirming && (
                <p className="text-xs text-muted-foreground">{action.confirmation_message ?? 'Confirmar ação?'}</p>
            )}
            <div className="flex gap-1.5">
                <Button
                    size="sm"
                    variant={VARIANT_MAP[action.variant] ?? 'outline'}
                    disabled={disabled}
                    onClick={handleClick}
                    className="h-7 text-xs"
                >
                    {confirming ? 'Confirmar' : action.label}
                </Button>
                {confirming && (
                    <Button
                        size="sm"
                        variant="ghost"
                        className="h-7 text-xs"
                        onClick={() => setConfirming(false)}
                    >
                        Cancelar
                    </Button>
                )}
            </div>
        </div>
    );
}

export function NotificationItem({
    notification,
    onMarkAsRead,
    onDelete,
    onExecuteAction,
    compact = false,
}: NotificationItemProps) {
    const [executingAction, setExecutingAction] = useState<string | null>(null);

    const Icon = CATEGORY_ICONS[notification.category] ?? TYPE_ICONS[notification.type] ?? Bell;
    const iconColor = TYPE_COLORS[notification.type] ?? 'text-muted-foreground';
    const isDone = notification.action_status === 'executed' || notification.action_status === 'rejected';

    const handleExecute = async (actionType: string) => {
        if (!onExecuteAction) return;
        setExecutingAction(actionType);
        try {
            await onExecuteAction(notification.uuid, actionType);
        } finally {
            setExecutingAction(null);
        }
    };

    const handleClick = () => {
        if (!notification.is_read) {
            onMarkAsRead?.(notification.uuid);
        }
        if (notification.link) {
            window.location.href = notification.link;
        }
    };

    return (
        <div
            className={cn(
                'group relative flex gap-3 rounded-lg border p-3 transition-colors',
                notification.is_read
                    ? 'border-transparent bg-transparent'
                    : 'border-primary/10 bg-primary/5',
                compact && 'p-2.5',
            )}
        >
            {/* Unread indicator */}
            {!notification.is_read && (
                <span className="absolute right-3 top-3 size-1.5 rounded-full bg-primary" />
            )}

            {/* Icon */}
            <div
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-full border bg-background',
                    compact ? 'size-7' : 'size-8',
                )}
            >
                <Icon className={cn('size-3.5', iconColor)} />
            </div>

            {/* Content */}
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <button
                        type="button"
                        className={cn(
                            'text-left',
                            notification.link && 'cursor-pointer hover:underline',
                        )}
                        onClick={handleClick}
                    >
                        <p className={cn('text-sm font-medium leading-tight', !notification.is_read && 'text-foreground')}>
                            {notification.title}
                        </p>
                    </button>
                    <span className="shrink-0 text-[11px] text-muted-foreground">
                        {formatRelativeTime(notification.created_at)}
                    </span>
                </div>

                <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{notification.body}</p>

                {/* Actions */}
                {!compact && notification.actions && notification.actions.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-2">
                        {isDone ? (
                            <Badge variant="secondary" className="text-xs">
                                {notification.action_status === 'executed' ? 'Executado' : 'Recusado'}
                            </Badge>
                        ) : (
                            notification.actions.map((action) => (
                                <ActionButton
                                    key={action.type}
                                    action={action}
                                    disabled={executingAction !== null}
                                    onConfirm={() => void handleExecute(action.type)}
                                />
                            ))
                        )}
                    </div>
                )}

                {/* Footer row */}
                {!compact && (
                    <div className="mt-1.5 flex items-center gap-2">
                        <Badge variant="outline" className="h-4 px-1 text-[10px] font-normal">
                            {notification.category_label}
                        </Badge>
                        {!notification.is_read && onMarkAsRead && (
                            <button
                                type="button"
                                className="text-[11px] text-muted-foreground hover:text-foreground"
                                onClick={() => onMarkAsRead(notification.uuid)}
                            >
                                Marcar como lida
                            </button>
                        )}
                        {onDelete && (
                            <button
                                type="button"
                                className="ml-auto text-[11px] text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100"
                                onClick={() => onDelete(notification.uuid)}
                            >
                                Excluir
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
