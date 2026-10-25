import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    useExportTracking,
    useTrackedDatabaseExports,
} from '@/contexts/export-tracking-context';
import type { ExportTrackingOwner } from '@/contexts/export-tracking-context';
import { usePrivateState } from '@/contexts/private-state-context';
import type {
    DatabaseExportType,
    StoredDatabaseExport,
} from '@/types/database-export';

export interface CreateDatabaseExportPayload {
    export_type: DatabaseExportType;
    tables?: string[] | null;
}

export interface UseDatabaseExportReturn {
    activeExports: StoredDatabaseExport[];
    hasActiveExports: boolean;
    startExport: (payload: CreateDatabaseExportPayload) => Promise<void>;
    markAsDownloaded: (uuid: string, expectedDownloadUrl?: string) => boolean;
}

export function useDatabaseExport(): UseDatabaseExportReturn {
    const { scope } = usePrivateState();
    const tracking = useExportTracking();
    const stored = useTrackedDatabaseExports();
    const [owner, setOwner] = useState<ExportTrackingOwner | null>(null);
    useEffect(() => {
        const registration = tracking.register('dumps');
        queueMicrotask(() => {
            if (registration.isCurrent()) {
                setOwner(registration);
            }
        });

        return () => {
            registration.release();
        };
    }, [tracking]);
    const startExport = useCallback(
        (payload: CreateDatabaseExportPayload) =>
            tracking.startDatabaseExport(payload, owner),
        [owner, tracking],
    );
    const markAsDownloaded = useCallback(
        (uuid: string, expectedDownloadUrl?: string) =>
            Boolean(owner?.isCurrent()) &&
            tracking.markDownloaded('dumps', uuid, expectedDownloadUrl),
        [owner, tracking],
    );
    const activeExports = useMemo(
        () =>
            scope.isCurrent()
                ? stored.filter((entry) => entry.downloadedAt === undefined)
                : [],
        [scope, stored],
    );

    return {
        activeExports,
        hasActiveExports: activeExports.some(
            (entry) =>
                entry.status === 'pending' || entry.status === 'processing',
        ),
        startExport,
        markAsDownloaded,
    };
}
