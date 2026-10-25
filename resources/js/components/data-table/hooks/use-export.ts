import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    useExportTracking,
    useTrackedExports,
} from '@/contexts/export-tracking-context';
import type { ExportTrackingOwner } from '@/contexts/export-tracking-context';
import { usePrivateState } from '@/contexts/private-state-context';
import type {
    DataTableExportConfig,
    ExportFormat,
    StoredExport,
} from '@/types/export';

export interface CreateExportPayload {
    modelClass: string;
    format: ExportFormat;
    columns: string[];
    filters?: Record<string, unknown>;
    search?: string;
    sort?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface UseExportReturn {
    activeExports: StoredExport[];
    hasActiveExports: boolean;
    startExport: (payload: CreateExportPayload) => Promise<void>;
    markAsDownloaded: (uuid: string, expectedDownloadUrl?: string) => boolean;
}

export function useExport(
    config?: DataTableExportConfig,
    enabled = config?.enabled !== false,
): UseExportReturn {
    const { scope } = usePrivateState();
    const tracking = useExportTracking();
    const stored = useTrackedExports();
    const [owner, setOwner] = useState<ExportTrackingOwner | null>(null);
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const registration = tracking.register('exports');
        queueMicrotask(() => {
            if (registration.isCurrent()) {
                setOwner(registration);
            }
        });

        return () => {
            registration.release();
        };
    }, [enabled, tracking]);
    const startExport = useCallback(
        (payload: CreateExportPayload) =>
            tracking.startExport(payload, enabled ? owner : null),
        [enabled, owner, tracking],
    );
    const markAsDownloaded = useCallback(
        (uuid: string, expectedDownloadUrl?: string) =>
            enabled &&
            Boolean(owner?.isCurrent()) &&
            tracking.markDownloaded('exports', uuid, expectedDownloadUrl),
        [enabled, owner, tracking],
    );
    const activeExports = useMemo(
        () =>
            enabled && scope.isCurrent()
                ? stored.filter((entry) => entry.downloadedAt === undefined)
                : [],
        [enabled, scope, stored],
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
