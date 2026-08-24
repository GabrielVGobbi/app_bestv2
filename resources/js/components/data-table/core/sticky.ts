type StickyColumnLike = {
    id: string;
    size: number;
    sticky: boolean;
};

export function getStickyRightLayout(
    columns: StickyColumnLike[],
): {
    offsets: Map<string, number>;
    leadingId: string | null;
} {
    const offsets = new Map<string, number>();
    let accumulated = 0;
    let leadingId: string | null = null;

    for (let index = columns.length - 1; index >= 0; index -= 1) {
        const column = columns[index];

        if (!column.sticky) {
            continue;
        }

        offsets.set(column.id, accumulated);
        accumulated += column.size;
        leadingId = column.id;
    }

    return { offsets, leadingId };
}
