import type { RowSelectionState } from '@tanstack/react-table';
import type { Dispatch, SetStateAction } from 'react';
import { useEffect, useRef, useState } from 'react';
import { usePrivateState } from '@/contexts/private-state-context';
import { getErrorMessage, toApiError } from '@/lib/http';
import type {
    DataTableBulkAction,
    DataTableSelectionResolver,
    DataTableSelectionStatus,
} from '../core/types';

interface UseDataTableSelectionArgs<TData> {
    storageKey: string;
    keyExtractor: (row: TData) => string | number;
    currentRows: TData[];
    rowSelection: RowSelectionState;
    setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
    enabled: boolean;
    selectionResolver?: DataTableSelectionResolver<TData>;
}

interface SelectionState<TData> {
    storageKey: string | null;
    epoch: number;
    ready: boolean;
    nextGeneration: number;
    members: Record<string, number>;
    rows: Record<string, TData>;
    pageRows: Record<string, TData>;
}

function emptyRecord<T>(): Record<string, T> {
    return Object.create(null);
}

export function useDataTableSelection<TData>({
    storageKey: unscopedStorageKey,
    keyExtractor,
    currentRows,
    rowSelection,
    setRowSelection,
    enabled,
    selectionResolver,
}: UseDataTableSelectionArgs<TData>) {
    const { scope } = usePrivateState();
    const storageKey = scope.storageKey(unscopedStorageKey);
    const [selection, setSelection] = useState<SelectionState<TData>>({
        storageKey,
        epoch: 0,
        ready: false,
        nextGeneration: 0,
        members: {},
        rows: {},
        pageRows: {},
    });
    const [resolution, setResolution] = useState<{
        fingerprint: string;
        pending: boolean;
        error: string | null;
        unavailableIds: string[];
    }>({ fingerprint: '', pending: false, error: null, unavailableIds: [] });
    const initial = useRef({ storageKey, rowSelection, used: false });
    const committed = useRef<SelectionState<TData> | null>(null);
    const inFlight = useRef<AbortController | null>(null);
    const persistence = useRef<{ key: string; ids: string[] } | null>(null);

    const sameDataset = selection.storageKey === storageKey;
    const active = enabled && scope.isCurrent();
    const effectiveSelection =
        active && sameDataset && (selection.ready || selection.epoch === 0)
            ? rowSelection
            : {};
    const ids = active
        ? Object.keys(effectiveSelection).filter((id) => effectiveSelection[id])
        : [];
    const next: SelectionState<TData> = {
        ...selection,
        storageKey,
        epoch: sameDataset ? selection.epoch : selection.epoch + 1,
        ready: sameDataset && selection.ready,
        members: emptyRecord(),
        rows: emptyRecord(),
        pageRows: emptyRecord(),
    };

    for (const id of ids) {
        next.members[id] =
            sameDataset && Object.hasOwn(selection.members, id)
                ? selection.members[id]
                : ++next.nextGeneration;

        if (sameDataset && Object.hasOwn(selection.rows, id)) {
            next.rows[id] = selection.rows[id];
        }

        if (sameDataset && Object.hasOwn(selection.pageRows, id)) {
            next.pageRows[id] = selection.pageRows[id];
        }
    }

    for (const row of currentRows) {
        const id = String(keyExtractor(row));

        if (Object.hasOwn(next.members, id) && next.pageRows[id] !== row) {
            next.rows[id] = row;
            next.pageRows[id] = row;
        }
    }

    // Interrupted renders must not publish rows or inclusion generations.
    if (
        !sameDataset ||
        Object.keys(selection.members).length !== ids.length ||
        ids.some(
            (id) =>
                selection.members[id] !== next.members[id] ||
                selection.rows[id] !== next.rows[id] ||
                selection.pageRows[id] !== next.pageRows[id],
        )
    ) {
        setSelection(next);
    }

    const fingerprint = JSON.stringify([
        next.epoch,
        ids.map((id) => [id, next.members[id]]),
    ]);
    const currentResolution =
        resolution.fingerprint === fingerprint ? resolution : null;

    useEffect(() => {
        committed.current = next;
    });

    useEffect(() => {
        if (!enabled || !storageKey || !scope.isCurrent()) {
            return;
        }

        const timer = setTimeout(() => {
            if (!scope.isCurrent()) {
                return;
            }

            let restored: RowSelectionState = {};

            try {
                const raw = localStorage.getItem(storageKey);

                if (
                    raw === null &&
                    !initial.current.used &&
                    initial.current.storageKey === storageKey
                ) {
                    restored = initial.current.rowSelection;
                } else if (raw !== null) {
                    const stored: unknown = JSON.parse(raw);

                    if (
                        Array.isArray(stored) &&
                        stored.every(
                            (id) => typeof id === 'string' && id.length > 0,
                        )
                    ) {
                        restored = Object.fromEntries(
                            stored.map((id) => [id, true]),
                        );
                    }
                }
            } catch {
                /* Malformed or unavailable storage does not select rows. */
            }

            initial.current.used = true;
            setRowSelection(restored);
            setSelection((current) =>
                current.storageKey === storageKey
                    ? { ...current, ready: true }
                    : current,
            );
        }, 0);

        return () => clearTimeout(timer);
    }, [enabled, storageKey, scope, setRowSelection]);

    useEffect(() => {
        if (!enabled || !storageKey || !next.ready || !scope.isCurrent()) {
            return;
        }

        const record = { key: storageKey, ids };
        persistence.current = record;
        const persist = () => {
            if (persistence.current !== record || !scope.isCurrent()) {
                return;
            }

            try {
                if (record.ids.length) {
                    localStorage.setItem(
                        record.key,
                        JSON.stringify(record.ids),
                    );
                } else {
                    localStorage.removeItem(record.key);
                }
            } catch {
                /* Storage may be unavailable or full. */
            }
        };
        const timer = setTimeout(persist, 300);

        return () => {
            clearTimeout(timer);
            persist();
        };
    });

    useEffect(
        () => () => {
            committed.current = null;
            inFlight.current?.abort();
            inFlight.current = null;
        },
        [storageKey, scope],
    );

    useEffect(
        () => () => {
            inFlight.current?.abort();
            inFlight.current = null;
        },
        [fingerprint],
    );

    function clearSnapshot(snapshot: SelectionState<TData>) {
        const current = committed.current;

        if (
            !scope.isCurrent() ||
            !current ||
            current.storageKey !== snapshot.storageKey ||
            current.epoch !== snapshot.epoch
        ) {
            return;
        }

        persistence.current = null;
        const removable = new Set(
            Object.keys(snapshot.members).filter(
                (id) => current.members[id] === snapshot.members[id],
            ),
        );
        setRowSelection((previous) => {
            const remaining = { ...previous };

            for (const id of removable) {
                delete remaining[id];
            }

            return remaining;
        });

        if (storageKey) {
            try {
                const remainingIds = Object.keys(current.members).filter(
                    (id) => !removable.has(id),
                );

                if (remainingIds.length) {
                    localStorage.setItem(
                        storageKey,
                        JSON.stringify(remainingIds),
                    );
                } else {
                    localStorage.removeItem(storageKey);
                }
            } catch {
                /* Storage is optional. */
            }
        }
    }

    async function resolveSelection(action?: DataTableBulkAction<TData>) {
        if (!active || !ids.length || inFlight.current) {
            return;
        }

        const snapshot = next;
        const controller = new AbortController();
        inFlight.current = controller;
        const abort = () => controller.abort();
        scope.signal.addEventListener('abort', abort, { once: true });
        const isCurrent = () => {
            const current = committed.current;

            return (
                scope.isCurrent() &&
                !controller.signal.aborted &&
                inFlight.current === controller &&
                current !== null &&
                current.storageKey === snapshot.storageKey &&
                current.epoch === snapshot.epoch &&
                Object.keys(current.members).length === ids.length &&
                ids.every((id) => current.members[id] === snapshot.members[id])
            );
        };
        setResolution({
            fingerprint,
            pending: true,
            error: null,
            unavailableIds: [],
        });

        try {
            let rows = ids.flatMap((id) =>
                Object.hasOwn(snapshot.rows, id) ? [snapshot.rows[id]] : [],
            );
            let unavailableIds: string[] = [];

            if (selectionResolver) {
                const result = await selectionResolver(ids, {
                    signal: controller.signal,
                });
                rows = result.rows;
                unavailableIds = result.unavailableIds;
                const partition = [
                    ...rows.map((row) => String(keyExtractor(row))),
                    ...unavailableIds,
                ];

                if (
                    partition.length !== ids.length ||
                    new Set(partition).size !== ids.length ||
                    partition.some((id) => !Object.hasOwn(snapshot.members, id))
                ) {
                    throw new Error(
                        'A resposta não corresponde à seleção solicitada.',
                    );
                }
            }

            if (!isCurrent()) {
                return;
            }

            setResolution({
                fingerprint,
                pending: false,
                error: null,
                unavailableIds,
            });
            setSelection((current) =>
                current.storageKey === snapshot.storageKey &&
                current.epoch === snapshot.epoch &&
                Object.keys(current.members).length === ids.length &&
                ids.every((id) => current.members[id] === snapshot.members[id])
                    ? {
                          ...current,
                          rows: Object.fromEntries(
                              rows.map((row) => [
                                  String(keyExtractor(row)),
                                  row,
                              ]),
                          ),
                      }
                    : current,
            );

            if (unavailableIds.length || rows.length !== ids.length) {
                return;
            }

            if (action?.disabled?.(rows)) {
                setResolution({
                    fingerprint,
                    pending: false,
                    error: 'A ação não está disponível para todos os registros selecionados.',
                    unavailableIds: [],
                });
            } else if (action) {
                action.onClick(rows, () => clearSnapshot(snapshot));
            }
        } catch (error) {
            if (isCurrent()) {
                setResolution({
                    fingerprint,
                    pending: false,
                    error:
                        error instanceof Error && !('type' in error)
                            ? error.message
                            : getErrorMessage(toApiError(error)),
                    unavailableIds: [],
                });
            }
        } finally {
            scope.signal.removeEventListener('abort', abort);

            if (inFlight.current === controller) {
                inFlight.current = null;
            }
        }
    }

    const selectedRows = ids.flatMap((id) =>
        Object.hasOwn(next.rows, id) ? [next.rows[id]] : [],
    );
    const status: DataTableSelectionStatus<TData> = {
        complete:
            selectedRows.length === ids.length &&
            !currentResolution?.unavailableIds.length,
        pending: currentResolution?.pending ?? false,
        reviewed: Boolean(
            currentResolution &&
            !currentResolution.pending &&
            !currentResolution.error &&
            !currentResolution.unavailableIds.length,
        ),
        error: currentResolution?.error ?? null,
        unavailableIds: currentResolution?.unavailableIds ?? [],
        canResolve: Boolean(selectionResolver),
        onResolve: () => void resolveSelection(),
        onAction: (action) => void resolveSelection(action),
    };

    return {
        selectedRows,
        selectedCount: ids.length,
        clearSelection: () => clearSnapshot(next),
        rowSelection: effectiveSelection,
        status,
    };
}
