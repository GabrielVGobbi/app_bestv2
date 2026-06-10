import type { Permission } from './permission';

export interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    permissions_count?: number;
    users_count?: number;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
}

export interface RoleResource {
    data: Role;
}

export interface RoleCollectionResource {
    data: Role[];
}
