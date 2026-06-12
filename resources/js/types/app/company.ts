export interface Company {
    id: number;
    uuid: string;
    name: string;
    nickname: string;
    cnpj: string;
    fiscal_metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface CompanyResource {
    data: Company;
}

export interface CompanyCollectionResource {
    data: Company[];
    links?: unknown;
    meta?: unknown;
}
