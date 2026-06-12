export const apiRoutes = {
    users: {
        index: '/api/v1/users',
        show: (id: number | string) => `/api/v1/users/${id}`,
        store: '/api/v1/users',
        update: (id: number | string) => `/api/v1/users/${id}`,
        destroy: (id: number | string) => `/api/v1/users/${id}`,
        syncRolesPermissions: (id: number | string) => `/api/v1/users/${id}/sync-roles-permissions`,
    },
    roles: {
        index: '/api/v1/roles',
        store: '/api/v1/roles',
        update: (id: number | string) => `/api/v1/roles/${id}`,
        destroy: (id: number | string) => `/api/v1/roles/${id}`,
        syncPermissions: (id: number | string) => `/api/v1/roles/${id}/sync-permissions`,
    },
    activityLog: {
        index: '/api/v1/activity-log',
    },
    permissions: {
        index: '/api/v1/permissions',
        store: '/api/v1/permissions',
        update: (id: number | string) => `/api/v1/permissions/${id}`,
        destroy: (id: number | string) => `/api/v1/permissions/${id}`,
    },
    crudGenerator: {
        preview: '/admin/dev/crud-generator/preview',
        apply: '/admin/dev/crud-generator/apply',
    },
    seederGenerator: {
        tables: '/admin/dev/seeder-generator/tables',
        preview: '/admin/dev/seeder-generator/preview',
        execute: '/admin/dev/seeder-generator/execute',
        generate: '/admin/dev/seeder-generator/generate',
        run: '/admin/dev/seeder-generator/run',
    },
    clients: {
        index: '/api/v1/clients',
        show: (id: number | string) => `/api/v1/clients/${id}`,
        store: '/api/v1/clients',
        update: (id: number | string) => `/api/v1/clients/${id}`,
        destroy: (id: number | string) => `/api/v1/clients/${id}`,
    },
    addresses: {
        index: '/api/v1/addresses',
        store: '/api/v1/addresses',
        update: (uuid: string) => `/api/v1/addresses/${uuid}`,
        destroy: (uuid: string) => `/api/v1/addresses/${uuid}`,
        setDefault: (uuid: string) => `/api/v1/addresses/${uuid}/default`,
    },
    exports: {
        index: '/api/v1/exports',
        store: '/api/v1/exports',
        show: (uuid: string) => `/api/v1/exports/${uuid}`,
        columns: '/api/v1/exports/columns',
    },
    databaseExports: {
        index: '/api/v1/database-exports',
        store: '/api/v1/database-exports',
        show: (uuid: string) => `/api/v1/database-exports/${uuid}`,
        download: (uuid: string) => `/api/v1/database-exports/${uuid}/download`,
        tables: '/api/v1/database-exports/tables',
    },
};
