import { Head, setLayoutProps } from '@inertiajs/react';
import { Bell, CheckCheck, RefreshCw } from 'lucide-react';
import { useState } from 'react';

import * as NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/heading';
import { NotificationItem } from '@/components/notification-item';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotifications } from '@/hooks/use-notifications';
import type { NotificationFilters, NotificationType } from '@/types/notification';

const TYPE_OPTIONS: { value: NotificationType | 'all'; label: string }[] = [
    { value: 'all', label: 'Todos os tipos' },
    { value: 'system', label: 'Sistema' },
    { value: 'user', label: 'Usuário' },
    { value: 'mention', label: 'Menção' },
    { value: 'social', label: 'Social' },
    { value: 'action', label: 'Ação' },
    { value: 'warning', label: 'Aviso' },
    { value: 'error', label: 'Erro' },
    { value: 'success', label: 'Sucesso' },
    { value: 'info', label: 'Informação' },
];

const READ_OPTIONS = [
    { value: 'all', label: 'Todas' },
    { value: 'false', label: 'Não lidas' },
    { value: 'true', label: 'Lidas' },
];

export default function NotificationsPage() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Notificações', href: NotificationController.index.url() }],
    });

    const [filters, setFilters] = useState<NotificationFilters>({});
    const {
        notifications,
        pagination,
        unreadCount,
        loading,
        error,
        refresh,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        executeAction,
    } = useNotifications(filters);

    const handleTypeChange = (value: string) => {
        setFilters((prev) => ({
            ...prev,
            type: value === 'all' ? undefined : (value as NotificationType),
        }));
    };

    const handleReadChange = (value: string) => {
        setFilters((prev) => ({
            ...prev,
            read: value === 'all' ? undefined : (value as 'true' | 'false'),
        }));
    };

    return (
        <>
            <Head title="Notificações" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    {/* Header */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <Heading
                            title="Notificações"
                            description={
                                unreadCount > 0
                                    ? `Você tem ${unreadCount} notificação${unreadCount > 1 ? 'ões' : ''} não lida${unreadCount > 1 ? 's' : ''}.`
                                    : 'Todas as notificações estão em dia.'
                            }
                        />
                        <div className="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={refresh}
                                disabled={loading}
                            >
                                <RefreshCw className={`mr-1.5 size-3.5 ${loading ? 'animate-spin' : ''}`} />
                                Atualizar
                            </Button>
                            {unreadCount > 0 && (
                                <Button
                                    size="sm"
                                    onClick={() => void markAllAsRead()}
                                    disabled={loading}
                                >
                                    <CheckCheck className="mr-1.5 size-3.5" />
                                    Marcar todas como lidas
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="flex flex-wrap gap-3">
                        <Select
                            value={filters.type ?? 'all'}
                            onValueChange={handleTypeChange}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.read ?? 'all'}
                            onValueChange={handleReadChange}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {READ_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                            {error}
                        </div>
                    )}

                    {/* List */}
                    <div className="flex flex-col gap-3">
                        {loading ? (
                            Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex gap-3 rounded-lg border p-3">
                                    <Skeleton className="size-8 shrink-0 rounded-full" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-3.5 w-1/3" />
                                        <Skeleton className="h-3 w-2/3" />
                                        <Skeleton className="h-3 w-1/4" />
                                    </div>
                                </div>
                            ))
                        ) : notifications.length === 0 ? (
                            <div className="flex flex-col items-center gap-4 rounded-lg border border-dashed py-16 text-center">
                                <div className="flex size-14 items-center justify-center rounded-full border bg-muted/30">
                                    <Bell className="size-6 text-muted-foreground/50" />
                                </div>
                                <div>
                                    <p className="font-medium text-muted-foreground">Nenhuma notificação encontrada</p>
                                    <p className="mt-1 text-sm text-muted-foreground/70">
                                        {Object.values(filters).some(Boolean)
                                            ? 'Tente remover os filtros para ver mais resultados.'
                                            : 'Você está em dia com tudo.'}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <NotificationItem
                                    key={notification.uuid}
                                    notification={notification}
                                    onMarkAsRead={(uuid) => void markAsRead(uuid)}
                                    onDelete={(uuid) => void deleteNotification(uuid)}
                                    onExecuteAction={executeAction}
                                />
                            ))
                        )}
                    </div>

                    {/* Pagination info */}
                    {pagination && pagination.total > 0 && (
                        <div className="text-center text-sm text-muted-foreground">
                            Exibindo {pagination.from ?? 0}–{pagination.to ?? 0} de {pagination.total} notificações
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
