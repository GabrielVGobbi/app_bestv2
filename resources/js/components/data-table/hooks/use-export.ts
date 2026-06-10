import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { httpGet, httpPost } from '@/lib/http';
import type { DataTableExportConfig, ExportFormat, ExportRecord, StoredExport } from '@/types/export';

import { apiRoutes } from '../../../lib/api/api-routes';

const STORAGE_KEY = 'data-table-exports';
const POLL_INTERVAL_MS = 3000;
/** Prune pending/processing exports that are older than this (ms) — matches server expiry */
const MAX_EXPORT_AGE_MS = 60 * 60 * 1000; // 1 hour

function isExportStale(e: StoredExport): boolean {
    const now = Date.now();

    // Completed/failed: prune once server-side expiry has passed
    if (e.expires_at) {
        return new Date(e.expires_at).getTime() < now;
    }

    // Pending/processing: prune if older than MAX_EXPORT_AGE_MS (queue likely not running)
    return now - e.createdAt > MAX_EXPORT_AGE_MS;
}

function readStoredExports(): StoredExport[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw) as StoredExport[];
        const fresh = parsed.filter((e) => !isExportStale(e));

        // Persist pruned list if anything was removed
        if (fresh.length !== parsed.length) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(fresh));
        }

        return fresh;
    } catch {
        return [];
    }
}

function writeStoredExports(exports: StoredExport[]): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(exports));
}

function mergeExportRecord(stored: StoredExport[], record: ExportRecord): StoredExport[] {
    return stored.map((e) => {
        if (e.uuid !== record.uuid) {
            return e;
        }

        return {
            ...e,
            status: record.status,
            file_name: record.file_name,
            download_url: record.download_url ?? null,
            total_records: record.total_records,
            processed_records: record.processed_records,
            expires_at: record.expires_at,
        };
    });
}

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
    markAsDownloaded: (uuid: string) => void;
}

export function useExport(_config?: DataTableExportConfig): UseExportReturn {
    const [storedExports, setStoredExports] = useState<StoredExport[]>(() => readStoredExports());
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const updateStored = useCallback((updater: (prev: StoredExport[]) => StoredExport[]) => {
        setStoredExports((prev) => {
            const next = updater(prev);

            writeStoredExports(next);

            return next;
        });
    }, []);

    const markAsDownloaded = useCallback(
        (uuid: string) => {
            updateStored((prev) =>
                prev.map((e) => (e.uuid === uuid ? { ...e, downloadedAt: Date.now() } : e)),
            );
        },
        [updateStored],
    );

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
            const toPoll = readStoredExports().filter((e) => e.status === 'pending' || e.status === 'processing');

            if (toPoll.length === 0) {
                if (intervalRef.current) {
                    clearInterval(intervalRef.current);
                    intervalRef.current = null;
                }

                return;
            }

            for (const stored of toPoll) {
                try {
                    const result = await httpGet<{ data: ExportRecord }>(apiRoutes.exports.show(stored.uuid));
                    const record = result.data;

                    // Use functional update so each merge sees the latest state,
                    // preventing a stale snapshot from overwriting a prior export's update
                    updateStored((prev) => mergeExportRecord(prev, record));

                    if (record.status === 'completed') {
                        if (record.download_url) {
                            toast.success('Exportação concluída!', {
                                description: record.file_name ?? 'Arquivo pronto para download.',
                                action: {
                                    label: 'Baixar',
                                    onClick: () => {
                                        window.open(record.download_url!, '_blank');
                                        markAsDownloaded(record.uuid);
                                    },
                                },
                                duration: 10000,
                            });
                        } else {
                            toast.warning('Exportação expirada', {
                                description: 'O arquivo expirou antes de ser baixado. Gere uma nova exportação.',
                            });
                        }
                    } else if (record.status === 'failed') {
                        toast.error('Exportação falhou', {
                            description: record.error_message ?? 'Tente novamente.',
                        });
                    }
                } catch (err: unknown) {
                    // Remove from localStorage if the server no longer knows about this export (404)
                    const status = (err as { status?: number })?.status;

                    if (status === 404) {
                        updateStored((prev) => prev.filter((e) => e.uuid !== stored.uuid));
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
    }, [storedExports, markAsDownloaded]);

    const startExport = useCallback(
        async (payload: CreateExportPayload): Promise<void> => {
            const response = await httpPost<{ data: ExportRecord }>(apiRoutes.exports.store, {
                model_class: payload.modelClass,
                format: payload.format,
                columns: payload.columns,
                filters: payload.filters ?? {},
                search: payload.search ?? '',
                sort: payload.sort ?? '',
                sort_direction: payload.sort_direction ?? 'desc',
            });

            const record = response.data;

            const stored: StoredExport = {
                uuid: record.uuid,
                status: record.status,
                modelClass: payload.modelClass,
                format: record.format,
                file_name: record.file_name,
                download_url: record.download_url ?? null,
                total_records: record.total_records,
                processed_records: record.processed_records,
                expires_at: record.expires_at,
                createdAt: Date.now(),
            };

            updateStored((prev) => {
                const pruned = readStoredExports();

                return [...pruned.filter((e) => e.uuid !== stored.uuid), stored];
            });

            toast.info('Exportação iniciada', {
                description: 'Você será notificado quando o arquivo estiver pronto.',
            });
        },
        [updateStored],
    );

    // Only show exports that haven't been downloaded yet (hides from "recentes" indicator)
    const activeExports = useMemo(
        () => storedExports.filter((e) => !e.downloadedAt),
        [storedExports],
    );

    const hasActiveExports = activeExports.some(
        (e) => e.status === 'pending' || e.status === 'processing',
    );

    return { activeExports, hasActiveExports, startExport, markAsDownloaded };
}
