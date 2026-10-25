import type { Permission } from './app/permission';
import type { Role } from './app/role';

export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    must_change_password: boolean;
    can?: { update: boolean; delete: boolean };
    roles?: Role[];
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
}

export interface UserResource {
    data: User;
}

export interface UserCollectionResource {
    data: User[];
    links?: unknown;
    meta?: unknown;
}
