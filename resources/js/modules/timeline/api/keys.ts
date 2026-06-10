import type { ModelId } from './types';

export const timelineKeys = {
    all: ['timeline'] as const,
    subject: (type: string, id: ModelId) =>
        [...timelineKeys.all, type, String(id)] as const,
    show: (type: string, id: ModelId, pageSize?: number) =>
        [...timelineKeys.subject(type, id), { pageSize }] as const,
};
