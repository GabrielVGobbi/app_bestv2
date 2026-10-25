import type { ColumnPinningState } from '@tanstack/react-table';

export type StickyGeometry = Record<string, { left?: number; right?: number }>;

export function clampColumnWidth(size: number, min = 60, max = 1200): number {
    return Math.round(Math.max(min, Math.min(max, size)));
}

export function resolveColumnWidths(
    columns: {
        id: string;
        size: number;
        resizable: boolean;
        minSize?: number;
        maxSize?: number;
    }[],
    sizing: Record<string, number>,
    viewport: number,
): Record<string, number> {
    const widths = Object.fromEntries(
        columns.map(({ id, size, resizable, minSize = 0, maxSize = 1200 }) => [
            id,
            Math.max(
                minSize,
                Math.min(maxSize, (resizable ? sizing[id] : undefined) ?? size),
            ),
        ]),
    );
    let remaining =
        viewport - Object.values(widths).reduce((sum, size) => sum + size, 0);
    let flexible = columns.filter(
        ({ id, resizable }) => resizable && sizing[id] === undefined,
    );

    // Keep actions at the viewport edge after every data column has been resized.
    if (flexible.length === 0) {
        flexible = columns.filter(({ resizable }) => resizable).slice(-1);
    }

    while (remaining > 0 && flexible.length > 0) {
        const share = remaining / flexible.length;

        for (const column of flexible) {
            const growth = Math.min(
                share,
                (column.maxSize ?? 1200) - widths[column.id],
            );
            widths[column.id] += growth;
            remaining -= growth;
        }

        flexible = flexible.filter(
            ({ id, maxSize }) => widths[id] < (maxSize ?? 1200),
        );

        if (remaining < 0.01) {
            break;
        }
    }

    return widths;
}

export function moveColumn(
    order: string[],
    source: string,
    target: string,
): string[] {
    if (
        source === target ||
        ['select', 'actions'].includes(source) ||
        ['select', 'actions'].includes(target) ||
        !order.includes(source) ||
        !order.includes(target)
    ) {
        return order;
    }

    const next = order.filter((id) => id !== source);
    next.splice(next.indexOf(target), 0, source);

    return next;
}

export function getStickyGeometry(
    columns: { id: string; size: number }[],
    pinning: ColumnPinningState,
    viewport = Infinity,
): StickyGeometry {
    const sizes = new Map(columns.map((column) => [column.id, column.size]));
    let left = (pinning.left ?? []).filter((id) => sizes.has(id));
    let right = (pinning.right ?? []).filter(
        (id) => sizes.has(id) && !left.includes(id),
    );
    const pinnedWidth = () =>
        [...left, ...right].reduce((sum, id) => sum + sizes.get(id)!, 0);

    if (pinnedWidth() + 160 > viewport) {
        left = left.filter((id) => id === 'select');
        right = right.filter((id) => id === 'actions');
    }

    if (pinnedWidth() > viewport) {
        return {};
    }

    const geometry: StickyGeometry = {};
    let offset = 0;

    for (const id of left) {
        geometry[id] = { left: offset };
        offset += sizes.get(id)!;
    }

    offset = 0;

    for (const id of [...right].reverse()) {
        geometry[id] = { right: offset };
        offset += sizes.get(id)!;
    }

    return geometry;
}
