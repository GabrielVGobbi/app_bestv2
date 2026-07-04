import type { StatusPresenter } from '../status';
import type { FinancialCategory } from './financial-category';

export type FinancialDirection = 'receivable' | 'payable';
export type FinancialEntryStatus =
    | 'open'
    | 'overdue'
    | 'partially_paid'
    | 'paid'
    | 'canceled'
    | 'reversed'
    | 'renegotiated';
export type FinancialAmountComponentCode =
    | 'principal'
    | 'discount'
    | 'tax'
    | 'interest'
    | 'penalty'
    | 'fee'
    | 'withholding'
    | 'adjustment'
    | 'other';
export type FinancialAmountOperation = 'add' | 'subtract';

export interface FinancialAmountComponent {
    id: number;
    uuid: string;
    company_id: number;
    code: FinancialAmountComponentCode;
    code_label: string;
    label: string | null;
    operation: FinancialAmountOperation;
    operation_label: string;
    amount: number;
    position: number;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface FinancialInstallment {
    id: number;
    uuid: string;
    company_id: number;
    financial_entry_id: number;
    status: FinancialEntryStatus;
    status_presenter: StatusPresenter;
    installment_number: number;
    due_date: string | null;
    competence_date: string | null;
    original_amount: number;
    settled_amount: number;
    balance_amount: number;
    currency: string;
    settled_at: string | null;
    is_overdue: boolean;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface FinancialEntry {
    id: number;
    uuid: string;
    company_id: number;
    created_by_id: number | null;
    direction: FinancialDirection;
    direction_label: string;
    status: FinancialEntryStatus;
    status_presenter: StatusPresenter;
    counterparty_type: string | null;
    counterparty_id: number | null;
    counterparty_label: string | null;
    originable_type: string | null;
    originable_id: number | null;
    origin_kind: string;
    origin_label: string;
    origin_display_name: string;
    origin_url: string | null;
    category_id: number | null;
    category?: { data?: FinancialCategory | null } | null;
    description: string;
    issue_date: string | null;
    competence_date: string | null;
    due_date: string | null;
    gross_amount: number;
    net_amount: number;
    settled_amount: number;
    balance_amount: number;
    currency: string;
    metadata: Record<string, unknown> | null;
    amount_components?: { data: FinancialAmountComponent[] };
    installments?: { data: FinancialInstallment[] };
    permissions: {
        can_update: boolean;
        can_cancel: boolean;
    };
    created_at: string;
    updated_at: string;
}

export interface FinancialEntryResource {
    data: FinancialEntry;
}

export interface FinancialEntryCollectionResource {
    data: FinancialEntry[];
    links?: unknown;
    meta?: unknown;
}

export interface FinancialEntryOption {
    label: string;
    value: string;
}

export type CounterpartyTypeByDirection = Record<FinancialDirection, string>;
