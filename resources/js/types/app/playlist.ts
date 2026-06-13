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
