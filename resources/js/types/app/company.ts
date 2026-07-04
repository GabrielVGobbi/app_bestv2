export interface Company {
    id: number;
    uuid: string;
    name: string;
    nickname: string;
    cnpj: string;
    fiscal_metadata: Record<string, unknown> | null;
    logo_disk: string | null;
    logo_path: string | null;
    logo_url: string | null;
    logo_initials: string;
    logo_color: string;
    logo_updated_at: string | null;
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
