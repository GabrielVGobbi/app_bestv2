import type { Paginated } from '@/types/pagination';
import type { StatusPresenter } from '@/types/status';

export interface SalePerson {
    id: number;
    uuid?: string;
    name: string;
    email?: string | null;
    phone?: string | null;
}

export interface SaleCompany {
    id: number;
    uuid: string;
    name: string;
    nickname: string;
    cnpj: string;
}

export interface StatusTransition extends StatusPresenter {
    action_label: string;
    permission: string;
    requires_reason: boolean;
    requires_confirmation: boolean;
}

export interface PlaylistSlot {
    id: number;
    uuid: string;
    status: string;
    starts_on: string | null;
    ends_on: string | null;
    quantity: number;
    track_name: string | null;
    cancellation_reason?: string | null;
    status_presenter: StatusPresenter;
}

export interface SaleItem {
    id: number;
    uuid: string;
    sale_id: number;
    type: 'service' | 'playlist_slot';
    description: string;
    quantity: number;
    unit_amount: number;
    discount_amount: number;
    total_amount: number;
    currency: string;
    starts_on: string | null;
    ends_on: string | null;
    status: string;
    status_presenter: StatusPresenter;
    service_id: number | null;
    playlist_top_id: number | null;
    sellable_type?: string | null;
    sellable_id?: number | null;
    track_spotify_id?: string | null;
    track_spotify_url: string | null;
    track_name: string | null;
    track_artists?: string[] | null;
    track_album_name?: string | null;
    track_duration_ms?: number | null;
    track_isrc?: string | null;
    track_image_url?: string | null;
    spotify_playlist_presence: {
        status:
            | 'unchecked'
            | 'present'
            | 'missing'
            | 'spotify_unavailable'
            | 'playlist_missing_id';
        status_presenter: StatusPresenter;
        checked_at: string | null;
        track_position: number | null;
        match_type: 'track_id' | 'isrc' | null;
        playlist_id_checked: string | null;
        snapshot_id_checked: string | null;
        error: string | null;
    };
    service?: { id: number; uuid: string; name: string } | null;
    playlist_top?: {
        id: number;
        uuid: string;
        name: string | null;
        playlist_name: string | null;
        cover_image_url?: string | null;
    } | null;
    sellable?: PlaylistSlot | null;
    created_at?: string;
    updated_at?: string;
}

export interface Sale {
    id: number;
    uuid: string;
    company_id: number;
    client_id: number;
    seller_id: number | null;
    company?: SaleCompany | null;
    client?: SalePerson | null;
    seller?: SalePerson | null;
    status: string;
    status_presenter: StatusPresenter;
    available_transitions: StatusTransition[];
    order_number: string;
    title: string | null;
    notes: string | null;
    sale_items?: SaleItem[];
    subtotal_amount?: number;
    discount_amount?: number;
    total_amount?: number;
    currency: string;
    confirmed_at: string | null;
    finished_at: string | null;
    canceled_at: string | null;
    cancellation_reason: string | null;
    created_at: string;
    updated_at: string;
}

export interface SaleResource {
    data: Sale;
}

export type SaleCollectionResource = Paginated<Sale>;

export interface SaleFormOption {
    id: number;
    uuid: string;
    name: string;
    nickname?: string | null;
    cnpj?: string | null;
    email?: string | null;
    phone?: string | null;
    playlist_id?: number;
    playlist_name?: string | null;
    top_name?: string | null;
    spotify_image_url?: string | null;
    cover_image_url?: string | null;
    base_price?: number;
    price?: number;
    currency?: string;
    slots_quantity?: number;
    occupied_slots_count?: number;
}

export interface SaleFormOptions {
    companies: SaleFormOption[];
    clients: SaleFormOption[];
    services: SaleFormOption[];
    playlists: SaleFormOption[];
    playlist_tops: SaleFormOption[];
}

export interface SaleFilterOption {
    value: string | number;
    label: string;
}

export interface SaleIndexFilters {
    statuses: SaleFilterOption[];
    companies: SaleFilterOption[];
    clients: SaleFilterOption[];
    sellers: SaleFilterOption[];
}

export interface SalePagePermissions {
    can_create: boolean;
    can_update: boolean;
    can_delete: boolean;
    can_quote: boolean;
    can_confirm: boolean;
    can_finish: boolean;
    can_cancel: boolean;
    can_verify_spotify_playlist: boolean;
}
