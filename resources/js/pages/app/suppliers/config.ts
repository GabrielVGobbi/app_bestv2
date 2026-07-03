import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Supplier } from '@/types/app/supplier';

export const columns: ColumnDef<Supplier>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        meta: { label: 'Nome' },
    },
    {
        id: 'document',
        accessorKey: 'document',
        header: 'Documento',
        meta: { label: 'Documento' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'Email',
        meta: { label: 'Email' },
    },
    {
        id: 'phone',
        accessorKey: 'phone',
        header: 'Telefone',
        meta: { label: 'Telefone' },
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
        meta: { label: 'Status' },
    },
];

export const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Nome',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'company_name',
        label: 'Razão social',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'document',
        label: 'Documento',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'email',
        label: 'Email',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'status',
        label: 'Status',
        type: 'select',
        options: [
            { label: 'Ativo', value: 'active' },
            { label: 'Inativo', value: 'inactive' },
            { label: 'Arquivado', value: 'archived' },
        ],
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Suppliers\\Models\\Supplier',
    columnsEndpoint: apiRoutes.exports.columns,
};
