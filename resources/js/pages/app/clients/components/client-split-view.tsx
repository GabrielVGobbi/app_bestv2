import type { Client } from '@/types/app/client';

import { ClientCardList } from './client-card-list';
import { ClientDetailsPanel } from './client-details-panel';

interface ClientSplitViewProps {
    selectedUuid: string | null;
    selectedClient: Client | null;
    search: string;
    isLoading: boolean;
    isFetching: boolean;
    error: Error | unknown;
    onSearchChange: (search: string) => void;
    onSelectClient: (client: Client) => void;
    onDeleteClient: (client: Client) => void;
    onRetryDetails: () => void;
}

export function ClientSplitView({
    selectedUuid,
    selectedClient,
    search,
    isLoading,
    isFetching,
    error,
    onSearchChange,
    onSelectClient,
    onDeleteClient,
    onRetryDetails,
}: ClientSplitViewProps) {
    return (
        <div className="grid h-full min-h-0 flex-1 grid-rows-[minmax(22rem,0.8fr)_minmax(28rem,1.2fr)] gap-4 overflow-hidden lg:grid-cols-[280px_minmax(0,1fr)] lg:grid-rows-none">
            <ClientCardList
                selectedUuid={selectedUuid}
                search={search}
                onSearchChange={onSearchChange}
                onSelect={onSelectClient}
                onDelete={onDeleteClient}
            />

            <ClientDetailsPanel
                selectedUuid={selectedUuid}
                client={selectedClient}
                isLoading={isLoading}
                isFetching={isFetching}
                error={error}
                onDelete={onDeleteClient}
                onRetry={onRetryDetails}
            />
        </div>
    );
}
