import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';

import * as NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationItem } from '@/components/notification-item';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotificationBell } from '@/hooks/use-notifications';

export function NotificationBell() {
    const [open, setOpen] = useState(false);
    const {
        unreadCount,
        recentNotifications,
        loading,
        refresh,
        markAsRead,
        markAllAsRead,
    } = useNotificationBell();

    const handleOpen = (value: boolean) => {
        setOpen(value);

        if (value) {
            refresh();
        }
    };

    return (
        <Popover open={open} onOpenChange={handleOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-9 bg-transparent text-muted-foreground hover:bg-secondary hover:text-foreground"
                >
                    <Bell className="size-4" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-runey-orange text-[10px] font-bold text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                    <span className="sr-only">Notificações</span>
                </Button>
            </PopoverTrigger>

            <PopoverContent align="end" sideOffset={8} className="w-80 p-0">
                {/* Header */}
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold">
                            Notificações
                        </span>
                        {unreadCount > 0 && (
                            <Badge
                                variant="secondary"
                                className="h-5 px-1.5 text-xs"
                            >
                                {unreadCount}
                            </Badge>
                        )}
                    </div>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            className="text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => void markAllAsRead()}
                        >
                            Marcar todas como lidas
                        </button>
                    )}
                </div>

                {/* List */}
                <div className="max-h-80 overflow-y-auto">
                    {loading ? (
                        <div className="space-y-2 p-3">
                            {[1, 2, 3].map((i) => (
                                <div key={i} className="flex gap-3">
                                    <Skeleton className="size-8 rounded-full" />
                                    <div className="flex-1 space-y-1.5">
                                        <Skeleton className="h-3 w-3/4" />
                                        <Skeleton className="h-3 w-1/2" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : recentNotifications.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                            <Bell className="size-8 text-muted-foreground/40" />
                            <p className="text-sm text-muted-foreground">
                                Nenhuma notificação não lida
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y">
                            {recentNotifications.map((notification) => (
                                <div
                                    key={notification.uuid}
                                    className="px-2 py-1"
                                >
                                    <NotificationItem
                                        notification={notification}
                                        onMarkAsRead={(uuid) =>
                                            void markAsRead(uuid)
                                        }
                                        compact
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="border-t p-2">
                    <Link
                        href={NotificationController.index.url()}
                        className="flex w-full items-center justify-center rounded-md py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        onClick={() => setOpen(false)}
                    >
                        Ver todas as notificações
                    </Link>
                </div>
            </PopoverContent>
        </Popover>
    );
}
