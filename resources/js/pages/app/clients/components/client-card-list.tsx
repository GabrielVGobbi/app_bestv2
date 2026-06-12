import { RefreshCw, Search, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { getErrorMessage, isApiError } from '@/lib/http';
import { cn, formatCpfCnpj, formatPhone } from '@/lib/utils';
import type { Client } from '@/types/app/client';

import { useClientCardList } from '../hooks/use-client-master-detail';

interface ClientCardListProps {
    selectedUuid: string | null;
    search: string;
    onSearchChange: (search: string) => void;
    onSelect: (client: Client) => void;
    onDelete: (client: Client) => void;
}

const statusLabels: Record<Client['status'], string> = {
    active: 'Ativo',
    inactive: 'Inativo',
    archived: 'Arquivado',
};

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
        <div className="rounded-lg border bg-card p-4">
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
                'rounded-lg border bg-card transition-colors hover:border-primary/50 hover:bg-muted/40',
                selected && 'border-primary bg-primary/5 shadow-sm',
            )}
        >
            <div className="flex items-start gap-2 p-4">
                <button
                    type="button"
                    onClick={() => onSelect(client)}
                    className="min-w-0 flex-1 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-pressed={selected}
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {client.name}
                            </p>
                            <p className="mt-1 truncate text-xs text-muted-foreground">
                                {cleanValue(
                                    client.company_name ?? client.email,
                                )}
                            </p>
                        </div>
                        <Badge
                            variant={
                                client.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                            className="shrink-0 rounded-full"
                        >
                            {statusLabels[client.status]}
                        </Badge>
                    </div>

                    <div className="mt-4 grid gap-2 text-xs">
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Telefone
                            </span>
                            <span className="truncate font-medium">
                                {cleanValue(formatPhone(client.phone))}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Documento
                            </span>
                            <span className="truncate font-medium">
                                {cleanValue(formatCpfCnpj(client.document))}
                            </span>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">
                                Classificação
                            </span>
                            <span className="truncate font-medium">
                                {cleanValue(client.classification)}
                            </span>
                        </div>
                    </div>
                </button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                    aria-label="Excluir cliente"
                    onClick={() => onDelete(client)}
                >
                    <Trash2 className="size-4" />
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
        <aside className="flex h-full min-h-0 flex-col gap-3 overflow-hidden rounded-lg border bg-card p-3">
            <div className="space-y-1">
                <h2 className="text-sm font-semibold">Clientes</h2>
                <p className="text-xs text-muted-foreground">
                    {meta?.total ?? clients.length} registros encontrados
                </p>
            </div>

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Buscar clientes..."
                    className="pl-9"
                />
            </div>

            {error ? (
                <div className="grid gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm">
                    <p className="text-destructive">{errorMessage(error)}</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => void refetch()}
                    >
                        <RefreshCw className="size-4" />
                        Tentar novamente
                    </Button>
                </div>
            ) : null}

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                {isLoading ? (
                    Array.from({ length: 6 }).map((_, index) => (
                        <ClientCardSkeleton key={index} />
                    ))
                ) : clients.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Nenhum cliente encontrado.
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
