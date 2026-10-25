import type { StatusPresenter } from '../status';
import type { FinancialAccount } from './financial-account';
import type { FinancialCategory } from './financial-category';
import type { PaymentMethod } from './payment-method';

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
export type FinancialSettlementStatus = 'posted' | 'reversed';
export type FinancialMovementDirection = 'inflow' | 'outflow';

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
    settlement_allocations?: FinancialSettlementAllocation[];
    permissions: {
        can_settle: boolean;
    };
    created_at: string;
    updated_at: string;
}

export interface FinancialSettlementAllocation {
    id: number;
    settlement_id: number;
    installment_id: number;
    amount: number;
    balance_before: number;
    balance_after: number;
    created_at: string;
    updated_at: string;
}

export interface FinancialMovement {
    id: number;
    uuid: string;
    company_id: number;
    financial_account_id: number | null;
    financial_account?: FinancialAccount | null;
    direction: FinancialMovementDirection;
    amount: number;
    occurred_at: string;
    source_type: string | null;
    source_id: number | null;
    reconciled_at: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface FinancialSettlement {
    id: number;
    uuid: string;
    company_id: number;
    financial_entry_id: number;
    direction: FinancialDirection;
    settled_at: string;
    financial_account_id: number | null;
    financial_account?: FinancialAccount | null;
    payment_method_id: number | null;
    gross_amount: number;
    discount_amount: number;
    interest_amount: number;
    penalty_amount: number;
    net_amount: number;
    origin: string;
    status: FinancialSettlementStatus;
    status_presenter: StatusPresenter;
    created_by_id: number | null;
    reversed_at: string | null;
    reversed_by_id: number | null;
    reverse_reason: string | null;
    metadata: Record<string, unknown> | null;
    allocations?: FinancialSettlementAllocation[];
    movements?: FinancialMovement[];
    payment_method?: PaymentMethod | null;
    permissions: {
        can_reverse: boolean;
    };
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
    category?: FinancialCategory | null;
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
    amount_components?: FinancialAmountComponent[];
    installments?: FinancialInstallment[];
    settlements?: FinancialSettlement[];
    permissions: {
        can_update: boolean;
        can_cancel: boolean;
        can_manage_attachments: boolean;
        can_settle: boolean;
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
