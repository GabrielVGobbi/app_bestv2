import { RefreshCw, Search, Trash2, Users } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { getErrorMessage, isApiError } from '@/lib/http';
import { cn, formatCpfCnpj, formatPhone } from '@/lib/utils';
import type { Client } from '@/types/app/client';

import { useClientCardList } from '../hooks/use-client-master-detail';
import { ClientStatusBadge } from './client-status-menu';

interface ClientCardListProps {
    selectedUuid: string | null;
    search: string;
    onSearchChange: (search: string) => void;
    onSelect: (client: Client) => void;
    onDelete: (client: Client) => void;
}

function cleanValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '-';
}

function errorMessage(error: Error | unknown): string {
    if (isApiError(error)) {
        return getErrorMessage(error);
    }

    return error instanceof Error
        ? error.message
        : 'Não foi possível carregar os clientes.';
}

function ClientCardSkeleton() {
    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="grid flex-1 gap-2">
                    <Skeleton className="h-4 w-36" />
                    <Skeleton className="h-3 w-44" />
                </div>
                <Skeleton className="h-5 w-16 rounded-full" />
            </div>
            <div className="mt-4 grid gap-2">
                <Skeleton className="h-3 w-full" />
                <Skeleton className="h-3 w-4/5" />
                <Skeleton className="h-3 w-2/3" />
            </div>
        </div>
    );
}

function ClientCard({
    client,
    selected,
    onSelect,
    onDelete,
}: {
    client: Client;
    selected: boolean;
    onSelect: (client: Client) => void;
    onDelete: (client: Client) => void;
}) {
    return (
        <article
            className={cn(
                'rounded-lg border border-border bg-card transition-colors hover:bg-accent',
                selected &&
                    'border-primary bg-primary-soft hover:bg-primary-soft',
            )}
        >
            <div className="flex items-start gap-2 p-4">
                <button
                    type="button"
                    onClick={() => onSelect(client)}
                    className="min-w-0 flex-1 rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    aria-pressed={selected}
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-foreground">
                                {client.name}
                            </p>
                            <p className="mt-1 truncate text-xs text-muted-foreground">
                                {cleanValue(
                                    client.company_name ?? client.email,
                                )}
                            </p>
                        </div>
                        <ClientStatusBadge
                            status={client.status}
                            className="shrink-0"
                        />
                    </div>

                    <div className="mt-4 grid gap-2 text-xs">
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Telefone
                            </span>
                            <span className="truncate font-medium text-foreground tabular-nums">
                                {cleanValue(formatPhone(client.phone))}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Documento
                            </span>
                            <span className="truncate font-medium text-foreground tabular-nums">
                                {cleanValue(formatCpfCnpj(client.document))}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Classificação
                            </span>
                            <span className="truncate font-medium text-foreground">
                                {cleanValue(client.classification)}
                            </span>
                        </div>
                    </div>
                </button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0 hover:bg-destructive-soft hover:text-destructive-soft-foreground"
                    aria-label="Excluir cliente"
                    onClick={() => onDelete(client)}
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                </Button>
            </div>
        </article>
    );
}

export function ClientCardList({
    selectedUuid,
    search,
    onSearchChange,
    onSelect,
    onDelete,
}: ClientCardListProps) {
    const {
        clients,
        meta,
        isLoading,
        isFetching,
        isFetchingNextPage,
        hasNextPage,
        error,
        fetchNextPage,
        refetch,
    } = useClientCardList(search);

    return (
        <aside className="flex h-full min-h-0 flex-col gap-3 overflow-hidden rounded-lg border border-border bg-card p-3">
            <div className="space-y-1">
                <h2 className="text-sm font-semibold text-foreground">
                    Clientes
                </h2>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {meta?.total ?? clients.length} registros encontrados
                </p>
            </div>

            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Buscar clientes"
                    aria-label="Buscar clientes"
                    className="pl-9"
                />
            </div>

            {error ? (
                <Alert variant="destructive">
                    <AlertDescription className="flex flex-col items-start gap-3">
                        {errorMessage(error)}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void refetch()}
                        >
                            <RefreshCw className="size-4" aria-hidden="true" />
                            Tentar novamente
                        </Button>
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                {isLoading ? (
                    Array.from({ length: 6 }).map((_, index) => (
                        <ClientCardSkeleton key={index} />
                    ))
                ) : clients.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border">
                        <EmptyState
                            icon={Users}
                            title="Nenhum cliente encontrado"
                            description="Ajuste a busca ou cadastre um novo cliente."
                        />
                    </div>
                ) : (
                    clients.map((client) => (
                        <ClientCard
                            key={client.uuid}
                            client={client}
                            selected={client.uuid === selectedUuid}
                            onSelect={onSelect}
                            onDelete={onDelete}
                        />
                    ))
                )}
            </div>

            {hasNextPage ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={isFetchingNextPage}
                    onClick={() => void fetchNextPage()}
                >
                    {isFetchingNextPage ? 'Carregando...' : 'Carregar mais'}
                </Button>
            ) : null}

            {isFetching && !isLoading && !isFetchingNextPage ? (
                <p className="text-center text-xs text-muted-foreground">
                    Atualizando...
                </p>
            ) : null}
        </aside>
    );
}
