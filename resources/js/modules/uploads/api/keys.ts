import type { ModelId } from './types';
import type { UploadFilters } from './types';

export const uploadsKeys = {
    all: ['uploads'] as const,
    lists: (type: string, id: ModelId) =>
        [...uploadsKeys.all, 'list', type, id] as const,
    list: (type: string, id: ModelId, filters?: UploadFilters) =>
        [...uploadsKeys.lists(type, id), filters ?? {}] as const,
    tags: (type: string) => [...uploadsKeys.all, 'tags', type] as const,
};
