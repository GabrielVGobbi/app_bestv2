export interface Client {
    id: number;
    uuid: string;
    name: string;
    email: string | null;
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
