export interface Upload {
    id: number;
    name: string;
    original_name: string | null;
    file_name: string;
    extension: string | null;
    mime_type: string;
    size: number | null;
    size_human: string;
    url: string;
    download_url: string;
    preview_url: string;
    icon: string;
    is_image: boolean;
    is_cover: boolean;
    is_favorite: boolean;
    tag: string | null;
    collection: string | null;
    source: string;
    created_at: string | null;
    updated_at: string | null;
    uploader?: { id: number | null; name: string | null };
    permissions: {
        can_update: boolean;
        can_delete: boolean;
        can_set_cover: boolean;
    };
}

export interface TagSuggestion {
    value: string;
    label: string;
}

export interface UploadFilters {
    tag?: string | null;
    collection?: string | null;
    search?: string;
    per_page?: number;
    page?: number;
}

export interface UploadUpdateInput {
    name?: string;
    tag?: string | null;
    collection?: string | null;
}

export type ModelId = string | number;

export interface ZipRequestStatus {
    uuid: string;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    file_count: number;
    label: string;
    download_url?: string;
    error_message?: string;
}
