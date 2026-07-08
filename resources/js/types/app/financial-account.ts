export type FinancialAccountType =
    | 'bank_account'
    | 'cash'
    | 'wallet'
    | 'payment_gateway';

export interface FinancialAccount {
    id: number;
    uuid: string;
    company_id: number;
    name: string;
    display_name: string;
    type: FinancialAccountType;
    type_label: string;
    institution_name: string | null;
    agency_number: string | null;
    account_number: string | null;
    account_digit: string | null;
    document_number: string | null;
    opening_balance_amount: number;
    current_balance_amount: number;
    balance_date: string | null;
    currency: string;
    active: boolean;
    allows_negative_balance: boolean;
    metadata: Record<string, unknown> | null;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    created_at: string;
    updated_at: string;
}

export interface FinancialAccountResource {
    data: FinancialAccount;
}

export interface FinancialAccountCollectionResource {
    data: FinancialAccount[];
    links?: unknown;
    meta?: unknown;
}

export interface FinancialAccountTypeOption {
    label: string;
    value: FinancialAccountType;
}
