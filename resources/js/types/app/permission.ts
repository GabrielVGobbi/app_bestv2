export interface Permission {
    id: number;
    name: string;
    slug: string;
    group: string | null;
    description: string | null;
    created_at: string;
    updated_at: string;
}

export interface PermissionResource {
    data: Permission;
}

export interface PermissionCollectionResource {
    data: Permission[];
}
