import type { TimelineItem } from '../api/types';

export interface TimelineDayGroup {
    key: string;
    label: string;
    items: TimelineItem[];
}

export function groupTimelineItems(items: TimelineItem[]): TimelineDayGroup[] {
    const formatter = new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'medium',
    });

    const groups = new Map<string, TimelineDayGroup>();

    items.forEach((item) => {
        const date = item.created_at ? new Date(item.created_at) : new Date();
        const key = date.toISOString().slice(0, 10);
        const existing = groups.get(key);

        if (existing) {
            existing.items.push(item);

            return;
        }

        groups.set(key, {
            key,
            label: formatter.format(date),
            items: [item],
        });
    });

    return Array.from(groups.values());
}
