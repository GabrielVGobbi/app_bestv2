import type { ColumnDef } from '@tanstack/react-table';

import type { DataTableDateColumn, DataTableExportConfig, DataTableFilterConfig } from '@/components/data-table';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Client } from '@/types/app/client';

export const columns: ColumnDef<Client>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Name',
        meta: { label: 'Name' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'Email',
        meta: { label: 'Email' },
    },
];

export const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Name',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'email',
        label: 'Email',
        type: 'text',
        operator: 'contains',
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Clients\\Models\\Client',
    columnsEndpoint: apiRoutes.exports.columns,
};
