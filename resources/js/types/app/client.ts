import type { Address } from '@/types/address';

export interface ClientResponsibleUser {
    id: number;
    uuid: string;
    name: string;
    email: string;
}

export interface Client {
    id: number;
    uuid: string;
    company_id: number | null;
    responsible_user_id: number | null;
    responsible_user?: ClientResponsibleUser | null;
    name: string;
    company_name: string | null;
    email: string | null;
    phone: string | null;
    document: string | null;
    musical_genre: string | null;
    is_agent: boolean;
    notes: string | null;
    classification: string | null;
    source_id: string | null;
    status: 'active' | 'inactive' | 'archived';
    permissions: {
        can_update: boolean;
    };
    addresses?: Address[];
    created_at: string;
    updated_at: string;
}

export interface ClientResource {
    data: Client;
}

export interface ClientCollectionResource {
    data: Client[];
    links?: unknown;
    meta?: unknown;
}
