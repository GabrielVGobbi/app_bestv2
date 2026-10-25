import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Bell,
    CheckCheck,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { useCallback, useState } from 'react';

import * as NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { NotificationItem } from '@/components/notification-item';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useNotifications } from '@/hooks/use-notifications';
import type {
    NotificationFilters,
    NotificationType,
} from '@/types/notification';

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
        breadcrumbs: [
            { title: 'Notificações', href: NotificationController.index.url() },
        ],
    });

    const [filters, setFilters] = useState<NotificationFilters>({ page: 1 });
    const handlePageChange = useCallback((page: number) => {
        setFilters((current) => ({ ...current, page }));
    }, []);
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
    } = useNotifications(filters, handlePageChange);

    const handleTypeChange = (value: string) => {
        setFilters((prev) => ({
            ...prev,
            page: 1,
            type: value === 'all' ? undefined : (value as NotificationType),
        }));
    };

    const handleReadChange = (value: string) => {
        setFilters((prev) => ({
            ...prev,
            page: 1,
            read: value === 'all' ? undefined : (value as 'true' | 'false'),
        }));
    };

    return (
        <>
            <Head title="Notificações" />

            <Header
                title="Notificações"
                subtitle={
                    unreadCount > 0
                        ? `Você tem ${unreadCount} ${unreadCount > 1 ? 'notificações não lidas' : 'notificação não lida'}.`
                        : 'Todas as notificações estão em dia.'
                }
            >
                <div className="flex shrink-0 gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={refresh}
                        disabled={loading}
                    >
                        <RefreshCw
                            className={`mr-1.5 size-3.5 ${loading ? 'animate-spin' : ''}`}
                        />
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
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    {/* Filters */}
                    <div className="flex flex-wrap gap-3">
                        <Select
                            value={filters.type ?? 'all'}
                            onValueChange={handleTypeChange}
                        >
                            <SelectTrigger
                                className="w-40"
                                placeholder="Tipo"
                            />
                            <SelectContent>
                                {TYPE_OPTIONS.map((opt, optIndex) => (
                                    <SelectItem
                                        key={opt.value}
                                        index={optIndex}
                                        value={opt.value}
                                    >
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.read ?? 'all'}
                            onValueChange={handleReadChange}
                        >
                            <SelectTrigger
                                className="w-36 min-w-0"
                                placeholder="Status"
                            />
                            <SelectContent>
                                {READ_OPTIONS.map((opt, optIndex) => (
                                    <SelectItem
                                        key={opt.value}
                                        index={optIndex}
                                        value={opt.value}
                                    >
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="rounded-lg border border-destructive-soft-foreground/20 bg-destructive-soft px-4 py-3 text-sm text-destructive-soft-foreground">
                            {error}
                        </div>
                    )}

                    {/* List */}
                    <div className="flex flex-col gap-3">
                        {loading ? (
                            Array.from({ length: 5 }).map((_, i) => (
                                <div
                                    key={i}
                                    className="flex gap-3 rounded-lg border p-3"
                                >
                                    <Skeleton className="size-8 shrink-0 rounded-full" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-3.5 w-1/3" />
                                        <Skeleton className="h-3 w-2/3" />
                                        <Skeleton className="h-3 w-1/4" />
                                    </div>
                                </div>
                            ))
                        ) : notifications.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-border">
                                <EmptyState
                                    icon={Bell}
                                    title="Nenhuma notificação"
                                    description={
                                        filters.type || filters.read
                                            ? 'Tente remover os filtros para ver mais resultados.'
                                            : 'Você está em dia com tudo.'
                                    }
                                />
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <NotificationItem
                                    key={notification.uuid}
                                    notification={notification}
                                    onMarkAsRead={(uuid) =>
                                        void markAsRead(uuid)
                                    }
                                    onDelete={(uuid) =>
                                        void deleteNotification(uuid)
                                    }
                                    onExecuteAction={executeAction}
                                />
                            ))
                        )}
                    </div>

                    {/* Pagination info */}
                    {pagination && pagination.total > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                            <span>
                                Exibindo {pagination.from ?? 0}–
                                {pagination.to ?? 0} de {pagination.total}{' '}
                                notificações
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        loading || pagination.current_page <= 1
                                    }
                                    onClick={() =>
                                        handlePageChange(
                                            pagination.current_page - 1,
                                        )
                                    }
                                >
                                    <ChevronLeft
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Anterior
                                </Button>
                                <span aria-live="polite">
                                    Página {pagination.current_page} de{' '}
                                    {pagination.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        loading ||
                                        pagination.current_page >=
                                            pagination.last_page
                                    }
                                    onClick={() =>
                                        handlePageChange(
                                            pagination.current_page + 1,
                                        )
                                    }
                                >
                                    <ChevronRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Próxima
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
