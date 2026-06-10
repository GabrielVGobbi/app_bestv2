import type { ModelId } from './types';

const base = '/api/v1/timeline';

export const timelineEndpoints = {
    show: (type: string, id: ModelId) =>
        `${base}/${encodeURIComponent(type)}/${encodeURIComponent(String(id))}`,
    comments: (type: string, id: ModelId) =>
        `${base}/${encodeURIComponent(type)}/${encodeURIComponent(String(id))}/comments`,
    comment: (comment: number | string) =>
        `${base}/comments/${encodeURIComponent(String(comment))}`,
    replies: (comment: number | string) =>
        `${base}/comments/${encodeURIComponent(String(comment))}/replies`,
};
