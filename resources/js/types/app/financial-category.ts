export type FinancialCategoryType = 'income' | 'expense' | 'transfer';

export interface FinancialCategoryBreadcrumbItem {
    id: number;
    uuid: string;
    name: string;
    color: string | null;
}

export interface FinancialCategory {
    id: number;
    uuid: string;
    company_id: number | null;
    parent_id: number | null;
    code: string | null;
    name: string;
    slug: string;
    description: string | null;
    type: FinancialCategoryType;
    color: string | null;
    sort_order: number;
    is_active: boolean;
    breadcrumb: FinancialCategoryBreadcrumbItem[];
    full_path: string;
    depth: number;
    has_children: boolean;
    children?: FinancialCategory[];
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    deletion_blockers?: string[];
    created_at: string;
    updated_at: string;
}

export interface FinancialCategoryResource {
    data: FinancialCategory;
}

export interface FinancialCategoryCollectionResource {
    data: FinancialCategory[];
    links?: unknown;
    meta?: unknown;
}

export interface FinancialCategoryTypeOption {
    label: string;
    value: FinancialCategoryType;
}
