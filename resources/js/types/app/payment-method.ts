export type PaymentMethodType =
    | 'cash'
    | 'pix'
    | 'bank_transfer'
    | 'boleto'
    | 'credit_card'
    | 'debit_card'
    | 'other';

export interface PaymentMethod {
    id: number;
    uuid: string;
    company_id: number | null;
    code: string;
    name: string;
    type: PaymentMethodType;
    type_label: string;
    requires_reference: boolean;
    is_active: boolean;
    sort_order: number;
    metadata: Record<string, unknown> | null;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
    created_at: string;
    updated_at: string;
}

export interface PaymentMethodResource {
    data: PaymentMethod;
}

export interface PaymentMethodCollectionResource {
    data: PaymentMethod[];
    links?: unknown;
    meta?: unknown;
}

export interface PaymentMethodTypeOption {
    label: string;
    value: PaymentMethodType;
}
