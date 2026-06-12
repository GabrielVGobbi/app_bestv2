export type ServiceDeliveryMode =
    | 'manual'
    | 'digital'
    | 'operational'
    | 'external';

export interface ServiceMetadata {
    requires_period?: boolean;
    requires_asset_reference?: boolean;
    delivery_mode?: ServiceDeliveryMode;
    default_notes?: string | null;
}

export interface Service {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    base_price: number;
    currency: string;
    is_active: boolean;
    metadata: ServiceMetadata | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface ServiceResource {
    data: Service;
}

export interface ServiceCollectionResource {
    data: Service[];
    links?: unknown;
    meta?: unknown;
}
