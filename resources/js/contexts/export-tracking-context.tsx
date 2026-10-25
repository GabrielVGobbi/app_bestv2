import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { useStore } from 'zustand';
import { createStore } from 'zustand/vanilla';

import type { CreateExportPayload } from '@/components/data-table/hooks/use-export';
import type { CreateDatabaseExportPayload } from '@/components/database-export/use-database-export';
import { usePrivateState } from '@/contexts/private-state-context';
import type { PrivateStateScope } from '@/contexts/private-state-context';
import { apiRoutes } from '@/lib/api/api-routes';
import {
    canceledPrivateRequest,
    httpGet,
    httpPost,
    toApiError,
} from '@/lib/http';
import type {
    DatabaseExportRecord,
    StoredDatabaseExport,
} from '@/types/database-export';
import type { ExportRecord, StoredExport } from '@/types/export';

type Kind = 'exports' | 'dumps';
type Stored = StoredExport | StoredDatabaseExport;
type RecordResponse = ExportRecord | DatabaseExportRecord;
type EndSession = (options?: { reason?: 'expired' }) => void;
const INTERVAL = 3000;
const MAX_TIMEOUT = 2147483647;
const keys = { exports: 'data-table-exports', dumps: 'database-exports' };
const maxAge = { exports: 3600000, dumps: 86400000 };
const pending = (entry: Stored) =>
    entry.status === 'pending' || entry.status === 'processing';
const deadline = (entry: Stored, kind: Kind) =>
    entry.expires_at
        ? Date.parse(entry.expires_at)
        : entry.createdAt + maxAge[kind];
const isExport = (entry: Stored): entry is StoredExport => 'format' in entry;
const isDump = (entry: Stored): entry is StoredDatabaseExport =>
    'export_type' in entry;

export interface ExportTrackingOwner {
    signal: AbortSignal;
    isCurrent: () => boolean;
    release: () => void;
}

function validStored(value: unknown, kind: Kind): value is Stored {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const row = value as Record<string, unknown>;
    const now = Date.now();

    return (
        Number.isFinite(now) &&
        typeof row.uuid === 'string' &&
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
            row.uuid,
        ) &&
        typeof row.status === 'string' &&
        ['pending', 'processing', 'completed', 'failed'].includes(row.status) &&
        typeof row.createdAt === 'number' &&
        Number.isFinite(row.createdAt) &&
        row.createdAt >= 0 &&
        row.createdAt <= now &&
        (row.expires_at === null ||
            (typeof row.expires_at === 'string' &&
                Number.isFinite(Date.parse(row.expires_at)))) &&
        (row.file_name === null || typeof row.file_name === 'string') &&
        (row.download_url === null || typeof row.download_url === 'string') &&
        (row.downloadedAt === undefined ||
            (typeof row.downloadedAt === 'number' &&
                Number.isFinite(row.downloadedAt) &&
                row.downloadedAt >= row.createdAt &&
                row.downloadedAt <= now)) &&
        (kind === 'exports'
            ? typeof row.modelClass === 'string' &&
              typeof row.format === 'string' &&
              ['csv', 'xlsx', 'tsv'].includes(row.format) &&
              (row.total_records === null ||
                  (typeof row.total_records === 'number' &&
                      Number.isFinite(row.total_records) &&
                      row.total_records >= 0)) &&
              typeof row.processed_records === 'number' &&
              Number.isFinite(row.processed_records) &&
              row.processed_records >= 0
            : typeof row.export_type === 'string' &&
              ['schema', 'data', 'full'].includes(row.export_type))
    );
}

function createTracking(scope: PrivateStateScope, endSession: EndSession) {
    const store = createStore<{
        exports: StoredExport[];
        dumps: StoredDatabaseExport[];
    }>(() => ({ exports: [], dumps: [] }));
    const flows = {
        exports: makeFlow(),
        dumps: makeFlow(),
    };
    function makeFlow() {
        return {
            generation: 0,
            consumers: 0,
            controller: null as AbortController | null,
            timer: undefined as ReturnType<typeof setTimeout> | undefined,
            expiryTimer: undefined as ReturnType<typeof setTimeout> | undefined,
            running: false,
            historyNeeded: false,
            suspended: false,
            initialized: false,
            versions: new Map<string, number>(),
            tombstones: new Set<string>(),
        };
    }
    const entries = (kind: Kind): Stored[] => store.getState()[kind];
    const current = (kind: Kind, generation: number) =>
        scope.isCurrent() &&
        flows[kind].consumers > 0 &&
        flows[kind].generation === generation &&
        !flows[kind].controller?.signal.aborted;

    function publish(kind: Kind, rows: Stored[]) {
        if (!scope.isCurrent()) {
            return;
        }

        const key = scope.storageKey(keys[kind]);

        if (key) {
            try {
                localStorage.setItem(key, JSON.stringify(rows));
            } catch {
                /* Persistence is optional. */
            }
        }

        if (kind === 'exports') {
            store.setState({ exports: rows.filter(isExport) });
        } else {
            store.setState({ dumps: rows.filter(isDump) });
        }

        scheduleExpiry(kind);
    }
    function change(
        kind: Kind,
        uuid: string,
        updater: (entry: Stored) => Stored | null,
    ) {
        const flow = flows[kind];
        flow.versions.set(uuid, (flow.versions.get(uuid) ?? 0) + 1);
        publish(
            kind,
            entries(kind).flatMap((entry) => {
                if (entry.uuid !== uuid) {
                    return [entry];
                }

                const next = updater(entry);

                return next ? [next] : [];
            }),
        );
    }
    function prune(kind: Kind) {
        const rows = entries(kind);
        const fresh = rows.filter(
            (entry) => deadline(entry, kind) > Date.now(),
        );

        if (fresh.length !== rows.length) {
            publish(kind, fresh);
        }

        if (!flows[kind].historyNeeded && !fresh.some(pending)) {
            clearTimeout(flows[kind].timer);
            flows[kind].timer = undefined;
        }
    }
    function scheduleExpiry(kind: Kind) {
        const flow = flows[kind];
        clearTimeout(flow.expiryTimer);
        flow.expiryTimer = undefined;

        if (!flow.consumers || !scope.isCurrent() || !entries(kind).length) {
            return;
        }

        const generation = flow.generation;
        const nextDeadline = Math.min(
            ...entries(kind).map((entry) => deadline(entry, kind)),
        );
        flow.expiryTimer = setTimeout(
            () => {
                if (!current(kind, generation)) {
                    return;
                }

                prune(kind);
                scheduleExpiry(kind);
            },
            Math.min(MAX_TIMEOUT, Math.max(0, nextDeadline - Date.now())),
        );
    }
    function restore(kind: Kind) {
        if (flows[kind].initialized) {
            prune(kind);
            scheduleExpiry(kind);

            return;
        }

        flows[kind].initialized = true;
        let rows: Stored[] = [];
        const key = scope.storageKey(keys[kind]);

        if (key) {
            try {
                const value: unknown = JSON.parse(
                    localStorage.getItem(key) ?? '[]',
                );

                if (Array.isArray(value)) {
                    const seen = new Set<string>();
                    rows = value.flatMap((entry): Stored[] => {
                        if (
                            !validStored(entry, kind) ||
                            seen.has(entry.uuid.toLowerCase()) ||
                            deadline(entry, kind) <= Date.now()
                        ) {
                            return [];
                        }

                        const uuid = entry.uuid.toLowerCase();
                        seen.add(uuid);

                        return [{ ...entry, uuid }];
                    });
                }
            } catch {
                /* Malformed storage is not private history. */
            }
        }

        publish(kind, rows);
    }
    function stop(kind: Kind) {
        const flow = flows[kind];
        ++flow.generation;
        clearTimeout(flow.timer);
        clearTimeout(flow.expiryTimer);
        flow.timer = undefined;
        flow.expiryTimer = undefined;
        flow.controller?.abort();
        flow.controller = null;
        flow.running = false;
    }
    function suspendSession() {
        if (!scope.isCurrent()) {
            return;
        }

        stop('exports');
        stop('dumps');
        endSession({ reason: 'expired' });
    }
    function markDownloaded(
        kind: Kind,
        uuid: string,
        expectedUrl?: string,
    ): boolean {
        const entry = entries(kind).find((item) => item.uuid === uuid);

        if (
            !scope.isCurrent() ||
            !flows[kind].consumers ||
            !entry ||
            entry.status !== 'completed' ||
            !entry.download_url ||
            deadline(entry, kind) <= Date.now() ||
            (expectedUrl !== undefined && expectedUrl !== entry.download_url)
        ) {
            return false;
        }

        change(kind, uuid, (item) => ({ ...item, downloadedAt: Date.now() }));

        return true;
    }
    function notify(
        kind: Kind,
        before: Stored | undefined,
        record: RecordResponse,
        generation: number,
    ) {
        if (!before || !pending(before) || !current(kind, generation)) {
            return;
        }

        if (record.status === 'completed') {
            const url = record.download_url;
            const entry = entries(kind).find(
                (item) => item.uuid === record.uuid,
            );

            if (!url || !entry || deadline(entry, kind) <= Date.now()) {
                toast.warning(
                    kind === 'exports'
                        ? 'Exportação expirada'
                        : 'Dump expirado',
                );

                return;
            }

            toast.success(
                kind === 'exports'
                    ? 'Exportação concluída!'
                    : 'Dump de banco pronto!',
                {
                    description:
                        record.file_name ?? 'Arquivo pronto para download.',
                    action: {
                        label: 'Baixar',
                        onClick: () => {
                            if (
                                current(kind, generation) &&
                                markDownloaded(kind, record.uuid, url)
                            ) {
                                window.open(url, '_blank');
                            }
                        },
                    },
                    duration: 10000,
                },
            );
        } else if (record.status === 'failed') {
            toast.error(
                kind === 'exports' ? 'Exportação falhou' : 'Dump falhou',
                { description: record.error_message ?? 'Tente novamente.' },
            );
        }
    }
    function merge(kind: Kind, before: Stored, record: RecordResponse): Stored {
        return kind === 'exports' &&
            isExport(before) &&
            'processed_records' in record
            ? {
                  ...before,
                  status: record.status,
                  file_name: record.file_name,
                  download_url: record.download_url ?? null,
                  total_records: record.total_records,
                  processed_records: record.processed_records,
                  expires_at: record.expires_at,
              }
            : {
                  ...before,
                  status: record.status,
                  file_name: record.file_name,
                  download_url: record.download_url ?? null,
                  expires_at: record.expires_at,
              };
    }
    function forbidden(kind: Kind, uuid?: string) {
        const flow = flows[kind];

        if (uuid) {
            change(kind, uuid, (entry) => ({
                ...entry,
                status: 'failed',
                download_url: null,
            }));
        } else {
            flow.suspended = true;
            publish(
                kind,
                entries(kind).map((entry) => ({
                    ...entry,
                    status: 'failed',
                    download_url: null,
                })),
            );
        }

        toast.error(
            kind === 'exports'
                ? 'Sem acesso à exportação'
                : 'Sem acesso ao dump',
        );
    }
    function schedule(kind: Kind) {
        const flow = flows[kind];

        if (
            !current(kind, flow.generation) ||
            flow.running ||
            flow.timer ||
            flow.suspended ||
            (!flow.historyNeeded && !entries(kind).some(pending))
        ) {
            return;
        }

        const generation = flow.generation;
        flow.timer = setTimeout(() => {
            if (!current(kind, generation)) {
                return;
            }

            flow.timer = undefined;
            void round(kind, generation);
        }, INTERVAL);
    }
    async function round(kind: Kind, generation: number) {
        const flow = flows[kind];

        if (!current(kind, generation) || flow.running || flow.suspended) {
            return;
        }

        flow.running = true;
        const signal = flow.controller!.signal;

        try {
            prune(kind);

            if (kind === 'dumps' && flow.historyNeeded) {
                try {
                    const versions = new Map(flow.versions);
                    const response = await httpGet<{
                        data: DatabaseExportRecord[];
                    }>(apiRoutes.databaseExports.index, { signal });

                    if (!current(kind, generation)) {
                        return;
                    }

                    const next = [...entries(kind)];

                    for (const record of response.data) {
                        if (
                            flow.tombstones.has(record.uuid) ||
                            flow.versions.get(record.uuid) !==
                                versions.get(record.uuid)
                        ) {
                            continue;
                        }

                        const index = next.findIndex(
                            (entry) => entry.uuid === record.uuid,
                        );
                        const before = next[index];
                        const entry: StoredDatabaseExport = {
                            uuid: record.uuid,
                            status: record.status,
                            export_type: record.export_type,
                            file_name: record.file_name,
                            download_url: record.download_url ?? null,
                            expires_at: record.expires_at,
                            createdAt:
                                before?.createdAt ??
                                (Number.isFinite(Date.parse(record.created_at))
                                    ? Date.parse(record.created_at)
                                    : Date.now()),
                            ...(before?.downloadedAt !== undefined
                                ? { downloadedAt: before.downloadedAt }
                                : {}),
                        };

                        if (deadline(entry, kind) <= Date.now()) {
                            if (index >= 0) {
                                next.splice(index, 1);
                            }

                            continue;
                        }

                        if (index < 0) {
                            next.push(entry);
                        } else {
                            next[index] = entry;
                        }

                        notify(kind, before, record, generation);
                    }

                    flow.historyNeeded = false;
                    publish(kind, next);
                } catch (error) {
                    if (!current(kind, generation)) {
                        return;
                    }

                    const failure = toApiError(error);

                    if (failure.status === 401 || failure.status === 419) {
                        suspendSession();

                        return;
                    }

                    if (failure.status === 403) {
                        forbidden(kind);

                        return;
                    }
                    // Keep known jobs usable while the history endpoint recovers.
                }
            }

            const ids = entries(kind)
                .filter(pending)
                .map((entry) => entry.uuid);

            for (const uuid of ids) {
                if (!current(kind, generation)) {
                    return;
                }

                const before = entries(kind).find(
                    (entry) => entry.uuid === uuid,
                );

                if (!before || !pending(before)) {
                    continue;
                }

                try {
                    const response = await httpGet<{ data: RecordResponse }>(
                        kind === 'exports'
                            ? apiRoutes.exports.show(uuid)
                            : apiRoutes.databaseExports.show(uuid),
                        { signal },
                    );

                    if (!current(kind, generation)) {
                        return;
                    }

                    if (response.data.uuid !== uuid) {
                        throw new Error(
                            'The export status did not match its requested identifier.',
                        );
                    }

                    if (!entries(kind).some((entry) => entry.uuid === uuid)) {
                        continue;
                    }

                    change(kind, uuid, (entry) =>
                        merge(kind, entry, response.data),
                    );
                    notify(kind, before, response.data, generation);
                } catch (error) {
                    if (!current(kind, generation)) {
                        return;
                    }

                    const failure = toApiError(error);

                    if (failure.status === 401 || failure.status === 419) {
                        suspendSession();

                        return;
                    }

                    if (failure.status === 403) {
                        forbidden(kind, uuid);
                    } else if (failure.status === 404) {
                        flow.tombstones.add(uuid);
                        change(kind, uuid, () => null);
                    }
                }
            }
        } catch (error) {
            if (!current(kind, generation)) {
                return;
            }

            const failure = toApiError(error);

            if (failure.status === 401 || failure.status === 419) {
                suspendSession();
            } else if (failure.status === 403) {
                forbidden(kind);
            }
        } finally {
            if (current(kind, generation)) {
                flow.running = false;
                schedule(kind);
            }
        }
    }
    function register(kind: Kind): ExportTrackingOwner {
        const flow = flows[kind];
        const owner = new AbortController();
        const registered = scope.isCurrent();

        if (registered && ++flow.consumers === 1) {
            ++flow.generation;
            flow.controller = new AbortController();
            flow.suspended = false;
            flow.historyNeeded = kind === 'dumps';
            restore(kind);

            if (flow.historyNeeded) {
                const generation = flow.generation;
                queueMicrotask(() => {
                    if (current(kind, generation)) {
                        void round(kind, generation);
                    }
                });
            } else {
                schedule(kind);
            }
        }

        let released = false;

        return {
            signal: owner.signal,
            isCurrent: () => !released && scope.isCurrent(),
            release: () => {
                if (released) {
                    return;
                }

                released = true;
                owner.abort();

                if (
                    registered &&
                    flow.consumers > 0 &&
                    --flow.consumers === 0
                ) {
                    stop(kind);
                }
            },
        };
    }
    async function start(
        kind: Kind,
        payload: CreateExportPayload | CreateDatabaseExportPayload,
        owner: ExportTrackingOwner | null,
    ) {
        if (!owner?.isCurrent()) {
            throw canceledPrivateRequest();
        }

        try {
            const response =
                kind === 'exports' && 'modelClass' in payload
                    ? await httpPost<{ data: ExportRecord }>(
                          apiRoutes.exports.store,
                          {
                              model_class: payload.modelClass,
                              format: payload.format,
                              columns: payload.columns,
                              filters: payload.filters ?? {},
                              search: payload.search ?? '',
                              sort: payload.sort ?? '',
                              sort_direction: payload.sort_direction ?? 'desc',
                          },
                          { signal: owner.signal },
                      )
                    : await httpPost<{ data: DatabaseExportRecord }>(
                          apiRoutes.databaseExports.store,
                          payload,
                          { signal: owner.signal },
                      );

            if (!owner.isCurrent()) {
                throw canceledPrivateRequest();
            }

            const record = response.data;

            if (
                (kind === 'exports' && !('format' in record)) ||
                (kind === 'dumps' && !('export_type' in record))
            ) {
                throw new Error(
                    'A resposta não corresponde à exportação solicitada.',
                );
            }

            let stored: Stored;

            if ('format' in record && 'modelClass' in payload) {
                stored = {
                    uuid: record.uuid,
                    status: record.status,
                    format: record.format,
                    modelClass: payload.modelClass,
                    file_name: record.file_name,
                    download_url: record.download_url ?? null,
                    total_records: record.total_records,
                    processed_records: record.processed_records,
                    expires_at: record.expires_at,
                    createdAt: Date.now(),
                };
            } else if ('export_type' in record) {
                stored = {
                    uuid: record.uuid,
                    status: record.status,
                    export_type: record.export_type,
                    file_name: record.file_name,
                    download_url: record.download_url ?? null,
                    expires_at: record.expires_at,
                    createdAt: Date.now(),
                };
            } else {
                throw new Error(
                    'A resposta não corresponde à exportação solicitada.',
                );
            }

            const flow = flows[kind];
            flow.suspended = false;
            flow.versions.set(
                stored.uuid,
                (flow.versions.get(stored.uuid) ?? 0) + 1,
            );
            publish(kind, [
                ...entries(kind).filter((entry) => entry.uuid !== stored.uuid),
                stored,
            ]);
            schedule(kind);
            toast.info(
                kind === 'exports' ? 'Exportação iniciada' : 'Dump iniciado',
                {
                    description:
                        'Você será notificado quando o arquivo estiver pronto.',
                },
            );
        } catch (error) {
            if (!owner.isCurrent()) {
                throw canceledPrivateRequest();
            }

            const failure = toApiError(error);

            if (failure.status === 401 || failure.status === 419) {
                suspendSession();

                throw canceledPrivateRequest();
            }

            throw error;
        }
    }

    return {
        store,
        register,
        markDownloaded,
        startExport: (
            payload: CreateExportPayload,
            owner: ExportTrackingOwner | null,
        ) => start('exports', payload, owner),
        startDatabaseExport: (
            payload: CreateDatabaseExportPayload,
            owner: ExportTrackingOwner | null,
        ) =>
            start(
                'dumps',
                { ...payload, tables: payload.tables ?? null },
                owner,
            ),
        dispose: () => {
            stop('exports');
            stop('dumps');

            if (!scope.isCurrent()) {
                store.setState({ exports: [], dumps: [] });
            }
        },
    };
}

const ExportTrackingContext = createContext<ReturnType<
    typeof createTracking
> | null>(null);

export function ExportTrackingProvider({ children }: { children: ReactNode }) {
    const { scope, endSession } = usePrivateState();
    const [tracking] = useState(() => createTracking(scope, endSession));
    useEffect(() => {
        const dispose = () => tracking.dispose();
        scope.signal.addEventListener('abort', dispose, { once: true });

        return () => {
            scope.signal.removeEventListener('abort', dispose);
            tracking.dispose();
        };
    }, [scope, tracking]);

    return (
        <ExportTrackingContext.Provider value={tracking}>
            {children}
        </ExportTrackingContext.Provider>
    );
}

export function useExportTracking() {
    const tracking = useContext(ExportTrackingContext);

    if (!tracking) {
        throw new Error('Export tracking requires ExportTrackingProvider.');
    }

    return tracking;
}

export function useTrackedExports() {
    return useStore(useExportTracking().store, (state) => state.exports);
}

export function useTrackedDatabaseExports() {
    return useStore(useExportTracking().store, (state) => state.dumps);
}
