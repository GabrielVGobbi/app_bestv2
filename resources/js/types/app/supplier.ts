export interface Supplier {
    id: number;
    uuid: string;
    company_id: number | null;
    name: string;
    company_name: string | null;
    document: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    status: 'active' | 'inactive' | 'archived';
    metadata: Record<string, unknown> | null;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    deletion_blockers?: string[];
    created_at: string;
    updated_at: string;
}

export interface SupplierResource {
    data: Supplier;
}

export interface SupplierCollectionResource {
    data: Supplier[];
    links?: unknown;
    meta?: unknown;
}
