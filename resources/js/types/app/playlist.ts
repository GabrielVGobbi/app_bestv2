export type PlaylistStatus = 'active' | 'inactive' | 'archived';

export interface MusicSubgenre {
    id: number;
    uuid: string;
    genre_ids?: number[];
    name: string;
    slug: string;
    is_active: boolean;
}

export interface MusicGenre {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    is_active: boolean;
    subgenres?: MusicSubgenre[];
}

export interface PlaylistExternalMetadata {
    legacy_spotify_id?: string | null;
    legacy_spotify_key?: string | null;
    source?: string | null;
}

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

export interface PlaylistTopMetrics {
    estimated_streams?: number | null;
    estimated_reach?: number | null;
    average_retention_days?: number | null;
    commercial_score?: number | null;
}

export interface PlaylistTop {
    id: number;
    uuid: string;
    playlist_id: number;
    top_id: number;
    top: Top | null;
    slots_quantity: number;
    occupied_slots_count: number;
    available_slots_count: number;
    price: number;
    currency: 'BRL';
    metrics: PlaylistTopMetrics | null;
    notes: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface Playlist {
    id: number;
    uuid: string;
    music_genre_id: number;
    name: string;
    slug: string;
    status: PlaylistStatus;
    status_label: string;
    partner_name: string | null;
    spotify_playlist_id: string | null;
    spotify_url: string | null;
    spotify_snapshot_id: string | null;
    spotify_owner_id: string | null;
    spotify_owner_name: string | null;
    spotify_tracks_total: number | null;
    spotify_last_synced_at: string | null;
    youtube_url: string | null;
    followers_count: number | null;
    estimated_reach: number | null;
    external_metadata: PlaylistExternalMetadata | null;
    notes: string | null;
    genre: MusicGenre | null;
    subgenres: MusicSubgenre[];
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface PlaylistResource {
    data: Playlist;
}

export interface PlaylistCollectionResource {
    data: Playlist[];
    links?: unknown;
    meta?: unknown;
}

export interface PlaylistTopCollectionResource {
    data: PlaylistTop[];
}

export interface TopCollectionResource {
    data: Top[];
}

export interface PlaylistStatusOption {
    value: PlaylistStatus;
    label: string;
}

export interface PlaylistFormOptions {
    statuses: PlaylistStatusOption[];
    genres: MusicGenre[];
    subgenres: MusicSubgenre[];
    can_create_music_catalog?: boolean;
    can_update_music_catalog?: boolean;
}

export interface SpotifyPlaylistPreview {
    spotify_playlist_id: string;
    spotify_url: string;
    name: string | null;
    followers_count: number | null;
    spotify_owner_id: string | null;
    spotify_owner_name: string | null;
    spotify_tracks_total: number | null;
    spotify_snapshot_id: string | null;
    public: boolean | null;
    source: 'web_api' | 'oembed';
    is_partial: boolean;
}
