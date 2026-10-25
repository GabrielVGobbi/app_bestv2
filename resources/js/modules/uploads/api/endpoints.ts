import type { ModelId } from './types';

const BASE = '/api/v1/uploads';
const FILE_BASE = '/uploads';

export const uploadsEndpoints = {
    list: () => BASE,
    store: () => BASE,
    tags: () => `${BASE}/tags`,
    show: (id: ModelId) => `${BASE}/${id}`,
    update: (id: ModelId) => `${BASE}/${id}`,
    destroy: (id: ModelId) => `${BASE}/${id}`,
    setCover: (id: ModelId) => `${BASE}/${id}/set-cover`,
    toggleFavorite: (id: ModelId) => `${BASE}/${id}/toggle-favorite`,
    preview: (id: ModelId) => `${FILE_BASE}/${id}/preview`,
    download: (id: ModelId) => `${FILE_BASE}/${id}/download`,
    bulkDestroy: () => `${BASE}/bulk`,
    zipRequests: () => `${BASE}/zip-requests`,
    zipRequestStatus: (uuid: string) => `${BASE}/zip-requests/${uuid}`,
    zipRequestDownload: (uuid: string) =>
        `${BASE}/zip-requests/${uuid}/download`,
} as const;
