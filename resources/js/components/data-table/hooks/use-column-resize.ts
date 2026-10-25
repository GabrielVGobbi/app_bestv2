import { useCallback, useEffect, useRef } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';

import { usePrivateState } from '@/contexts/private-state-context';

import { clampColumnWidth } from '../core/table-layout';

export function useColumnResize() {
    const { scope } = usePrivateState();
    const cleanupRef = useRef<(() => void) | null>(null);
    useEffect(() => () => cleanupRef.current?.(), [scope]);

    return useCallback(
        (
            event: ReactPointerEvent<HTMLDivElement>,
            options: {
                size: number;
                minSize?: number;
                maxSize?: number;
                fillColumnId?: string;
                onComplete: (sizes: Record<string, number>) => void;
            },
        ) => {
            if (event.button !== 0 || !scope.isCurrent()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            cleanupRef.current?.();
            const handle = event.currentTarget;
            const header = handle.parentElement!;
            const table = header.closest('table')!;
            const columns = [
                ...table.querySelectorAll<HTMLElement>(
                    'thead [data-column-id]',
                ),
            ];
            const sizes = Object.fromEntries(
                columns.map((cell) => [
                    cell.dataset.columnId!,
                    cell.getBoundingClientRect().width,
                ]),
            );
            const column = table.querySelector<HTMLElement>(
                `colgroup [data-column-id="${header.dataset.columnId}"]`,
            )!;
            const previousColumnWidth = column.style.width;
            const fill = options.fillColumnId
                ? table.querySelector<HTMLElement>(
                      `colgroup [data-column-id="${options.fillColumnId}"]`,
                  )
                : null;
            const previousFillWidth = fill?.style.width ?? '';
            const fillIndex = columns.findIndex(
                (column) => column.dataset.columnId === options.fillColumnId,
            );
            const viewport = table.parentElement!.clientWidth;
            const previousTableWidth = table.style.width;
            const tableWidth = table.getBoundingClientRect().width;
            const startSize =
                header.getBoundingClientRect().width || options.size;
            const startX = event.clientX;
            let nextSize = startSize;
            let frame = 0;
            const root = document.documentElement;
            const previousCursor = root.style.cursor;
            const previousSelection = root.style.userSelect;
            const index = columns.indexOf(header);
            const pinned = [
                ...table.querySelectorAll<HTMLElement>(
                    'th[data-column-id], td[data-column-id]',
                ),
            ]
                .filter((cell) => cell.style.left || cell.style.right)
                .map((cell) => ({
                    cell,
                    index: columns.findIndex(
                        (column) =>
                            column.dataset.columnId === cell.dataset.columnId,
                    ),
                    left: cell.style.left,
                    right: cell.style.right,
                }));
            root.style.cursor = 'col-resize';
            root.style.userSelect = 'none';
            handle.dataset.resizing = 'true';
            const preview = () => {
                frame = 0;
                const delta = nextSize - startSize;
                // Same rule as resolveColumnWidths: the fill column absorbs the gap, so pinned actions stay at the edge.
                const extra = fill
                    ? Math.max(0, viewport - tableWidth - delta)
                    : 0;
                column.style.width = `${nextSize}px`;

                if (fill) {
                    fill.style.width = `${(fill === column ? nextSize : sizes[options.fillColumnId!]) + extra}px`;
                }

                table.style.width = `${tableWidth + delta + extra}px`;

                for (const pin of pinned) {
                    const shift = (after: boolean, fillAfter: boolean) =>
                        (after ? delta : 0) + (fillAfter ? extra : 0);

                    if (pin.left) {
                        pin.cell.style.left = `${parseFloat(pin.left) + shift(pin.index > index, pin.index > fillIndex)}px`;
                    }

                    if (pin.right) {
                        pin.cell.style.right = `${parseFloat(pin.right) + shift(pin.index < index, fillIndex >= 0 && pin.index < fillIndex)}px`;
                    }
                }
            };
            const onMove = (move: PointerEvent) => {
                if (move.pointerId !== event.pointerId) {
                    return;
                }

                nextSize = clampColumnWidth(
                    startSize + move.clientX - startX,
                    options.minSize,
                    options.maxSize,
                );

                if (!frame) {
                    frame = window.requestAnimationFrame(preview);
                }
            };
            const cleanup = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onEnd);
                document.removeEventListener('pointercancel', cleanup);
                document.removeEventListener('keydown', onKey);
                window.removeEventListener('blur', cleanup);
                window.cancelAnimationFrame(frame);
                column.style.width = previousColumnWidth;

                if (fill) {
                    fill.style.width = previousFillWidth;
                }

                table.style.width = previousTableWidth;

                for (const pin of pinned) {
                    pin.cell.style.left = pin.left;
                    pin.cell.style.right = pin.right;
                }

                delete handle.dataset.resizing;
                root.style.cursor = previousCursor;
                root.style.userSelect = previousSelection;
                cleanupRef.current = null;
            };
            const onKey = (key: KeyboardEvent) => {
                if (key.key === 'Escape') {
                    key.preventDefault();
                    cleanup();
                }
            };
            const onEnd = (move: PointerEvent) => {
                if (move.pointerId !== event.pointerId) {
                    return;
                }

                onMove(move);
                cleanup();

                if (scope.isCurrent() && nextSize !== startSize) {
                    options.onComplete({
                        ...sizes,
                        [header.dataset.columnId!]: nextSize,
                    });
                }
            };
            cleanupRef.current = cleanup;
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onEnd);
            document.addEventListener('pointercancel', cleanup);
            document.addEventListener('keydown', onKey);
            window.addEventListener('blur', cleanup);
        },
        [scope],
    );
}
