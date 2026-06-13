export interface Top {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    default_slots_quantity: number;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    playlist_tops_count?: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface TopResource {
    data: Top;
}

export interface TopCollectionResource {
    data: Top[];
}
