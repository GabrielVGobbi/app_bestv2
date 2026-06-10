import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { httpGet, httpPost } from '@/lib/http';
import type {
    DatabaseExportRecord,
    DatabaseExportType,
    StoredDatabaseExport,
} from '@/types/database-export';

import { apiRoutes } from '../../lib/api/api-routes';

const STORAGE_KEY = 'database-exports';
const POLL_INTERVAL_MS = 3000;
const MAX_EXPORT_AGE_MS = 24 * 60 * 60 * 1000; // 24 hours (matches server expiry)

function isExportStale(e: StoredDatabaseExport): boolean {
    const now = Date.now();

    if (e.expires_at) {
        return new Date(e.expires_at).getTime() < now;
    }

    return now - e.createdAt > MAX_EXPORT_AGE_MS;
}

function readStoredExports(): StoredDatabaseExport[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw) as StoredDatabaseExport[];
        const fresh = parsed.filter((e) => !isExportStale(e));

        if (fresh.length !== parsed.length) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(fresh));
        }

        return fresh;
    } catch {
        return [];
    }
}

function writeStoredExports(exports: StoredDatabaseExport[]): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(exports));
}

function mergeExportRecord(
    stored: StoredDatabaseExport[],
    record: DatabaseExportRecord,
): StoredDatabaseExport[] {
    return stored.map((e) => {
        if (e.uuid !== record.uuid) {
            return e;
        }

        return {
            ...e,
            status: record.status,
            file_name: record.file_name,
            download_url: record.download_url ?? null,
            expires_at: record.expires_at,
        };
    });
}

export interface CreateDatabaseExportPayload {
    export_type: DatabaseExportType;
    tables?: string[] | null;
}

export interface UseDatabaseExportReturn {
    activeExports: StoredDatabaseExport[];
    hasActiveExports: boolean;
    startExport: (payload: CreateDatabaseExportPayload) => Promise<void>;
    markAsDownloaded: (uuid: string) => void;
}

export function useDatabaseExport(): UseDatabaseExportReturn {
    const [storedExports, setStoredExports] = useState<StoredDatabaseExport[]>(
        () => readStoredExports(),
    );
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const updateStored = useCallback(
        (updater: (prev: StoredDatabaseExport[]) => StoredDatabaseExport[]) => {
            setStoredExports((prev) => {
                const next = updater(prev);

                writeStoredExports(next);

                return next;
            });
        },
        [],
    );

    const markAsDownloaded = useCallback(
        (uuid: string) => {
            updateStored((prev) =>
                prev.map((e) =>
                    e.uuid === uuid ? { ...e, downloadedAt: Date.now() } : e,
                ),
            );
        },
        [updateStored],
    );

    // Merge server-side history once on mount (restores exports across page refreshes)
    useEffect(() => {
        const controller = new AbortController();

        httpGet<{ data: DatabaseExportRecord[] }>(
            apiRoutes.databaseExports.index,
        )
            .then((response) => {
                if (controller.signal.aborted) {
                    return;
                }

                const serverRecords = response.data;

                updateStored((prev) => {
                    const existingUuids = new Set(prev.map((e) => e.uuid));
                    const newEntries: StoredDatabaseExport[] = serverRecords
                        .filter((r) => !existingUuids.has(r.uuid))
                        .map((r) => ({
                            uuid: r.uuid,
                            status: r.status,
                            export_type: r.export_type,
                            file_name: r.file_name,
                            download_url: r.download_url ?? null,
                            expires_at: r.expires_at,
                            createdAt: r.expires_at
                                ? new Date(r.expires_at).getTime() -
                                  MAX_EXPORT_AGE_MS
                                : Date.now(),
                        }));

                    return newEntries.length > 0
                        ? [...newEntries, ...prev]
                        : prev;
                });
            })
            .catch(() => {
                // Silently ignore — localStorage state is sufficient fallback
            });

        return () => controller.abort();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Polling
    useEffect(() => {
        const pendingExports = storedExports.filter(
            (e) => e.status === 'pending' || e.status === 'processing',
        );

        if (pendingExports.length === 0) {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }

            return;
        }

        if (intervalRef.current) {
            return;
        }

        intervalRef.current = setInterval(async () => {
            const toPoll = readStoredExports().filter(
                (e) => e.status === 'pending' || e.status === 'processing',
            );

            if (toPoll.length === 0) {
                if (intervalRef.current) {
                    clearInterval(intervalRef.current);
                    intervalRef.current = null;
                }

                return;
            }

            for (const stored of toPoll) {
                try {
                    const result = await httpGet<{
                        data: DatabaseExportRecord;
                    }>(apiRoutes.databaseExports.show(stored.uuid));
                    const record = result.data;

                    updateStored((prev) => mergeExportRecord(prev, record));

                    if (record.status === 'completed') {
                        if (record.download_url) {
                            toast.success('Dump de banco pronto!', {
                                description:
                                    record.file_name ??
                                    'Arquivo SQL pronto para download.',
                                action: {
                                    label: 'Baixar',
                                    onClick: () => {
                                        window.open(
                                            record.download_url!,
                                            '_blank',
                                        );
                                        markAsDownloaded(record.uuid);
                                    },
                                },
                                duration: 10000,
                            });
                        } else {
                            toast.warning('Dump expirado', {
                                description:
                                    'O arquivo expirou antes de ser baixado. Gere um novo dump.',
                            });
                        }
                    } else if (record.status === 'failed') {
                        toast.error('Dump falhou', {
                            description:
                                record.error_message ?? 'Tente novamente.',
                        });
                    }
                } catch (err: unknown) {
                    const status = (err as { status?: number })?.status;

                    if (status === 404) {
                        updateStored((prev) =>
                            prev.filter((e) => e.uuid !== stored.uuid),
                        );
                    }
                }
            }
        }, POLL_INTERVAL_MS);

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [storedExports, markAsDownloaded, updateStored]);

    const startExport = useCallback(
        async (payload: CreateDatabaseExportPayload): Promise<void> => {
            const response = await httpPost<{ data: DatabaseExportRecord }>(
                apiRoutes.databaseExports.store,
                {
                    export_type: payload.export_type,
                    tables: payload.tables ?? null,
                },
            );

            const record = response.data;

            const stored: StoredDatabaseExport = {
                uuid: record.uuid,
                status: record.status,
                export_type: record.export_type,
                file_name: record.file_name,
                download_url: record.download_url ?? null,
                expires_at: record.expires_at,
                createdAt: Date.now(),
            };

            updateStored(() => {
                const pruned = readStoredExports();

                return [
                    ...pruned.filter((e) => e.uuid !== stored.uuid),
                    stored,
                ];
            });

            toast.info('Dump iniciado', {
                description:
                    'Você será notificado quando o arquivo estiver pronto.',
            });
        },
        [updateStored],
    );

    const activeExports = useMemo(
        () => storedExports.filter((e) => !e.downloadedAt),
        [storedExports],
    );

    const hasActiveExports = activeExports.some(
        (e) => e.status === 'pending' || e.status === 'processing',
    );

    return { activeExports, hasActiveExports, startExport, markAsDownloaded };
}
