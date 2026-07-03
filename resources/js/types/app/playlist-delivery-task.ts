import type { StatusPresenter } from '@/types/status';

export interface PlaylistDeliveryTaskTrack {
    spotify_id: string | null;
    spotify_url: string | null;
    name: string | null;
    artists: Array<string | { name?: string | null }> | null;
    album_name: string | null;
    duration_ms: number | null;
    isrc: string | null;
    image_url: string | null;
}

export interface PlaylistDeliveryTask {
    id: number;
    uuid: string;
    playlist_slot_id: number | null;
    sale_item_id: number | null;
    sale_id: number | null;
    playlist_top_id: number | null;
    playlist_id: number | null;
    type: 'add_track' | 'remove_track' | 'replace_track';
    type_presenter: StatusPresenter;
    status: 'pending' | 'completed' | 'canceled';
    status_presenter: StatusPresenter;
    sale_order_number: string | null;
    client_name: string | null;
    playlist_name: string | null;
    top_name: string | null;
    from_track_spotify_id?: string | null;
    from_track_spotify_url?: string | null;
    from_track_name?: string | null;
    from_track_artists?: Array<string | { name?: string | null }> | null;
    from_track_album_name?: string | null;
    from_track_duration_ms?: number | null;
    from_track_isrc?: string | null;
    from_track_image_url?: string | null;
    to_track_spotify_id?: string | null;
    to_track_spotify_url?: string | null;
    to_track_name?: string | null;
    to_track_artists?: Array<string | { name?: string | null }> | null;
    to_track_album_name?: string | null;
    to_track_duration_ms?: number | null;
    to_track_isrc?: string | null;
    to_track_image_url?: string | null;
    track: PlaylistDeliveryTaskTrack;
    from_track: PlaylistDeliveryTaskTrack;
    to_track: PlaylistDeliveryTaskTrack;
    sale?: {
        id: number;
        uuid: string;
        order_number: string | null;
        title: string | null;
    } | null;
    playlist?: {
        id: number;
        uuid: string;
        name: string;
        spotify_url: string | null;
    } | null;
    completed_at: string | null;
    completed_by_id: number | null;
    canceled_at: string | null;
    canceled_by_id: number | null;
    cancellation_reason: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface PlaylistDeliveryTaskFilters {
    statuses: Array<{ value: string; label: string }>;
    types: Array<{ value: string; label: string }>;
}

export interface PlaylistDeliveryTaskPermissions {
    can_complete: boolean;
    can_change_track: boolean;
}
