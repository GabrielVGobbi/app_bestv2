import type { ColumnDef } from '@tanstack/react-table';
import { BadgeCheck, Music2, UserRoundCheck } from 'lucide-react';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
    DataTableFilterOption,
    DataTableQuickFilter,
} from '@/components/data-table';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Client } from '@/types/app/client';

export interface ClientQuickFilterOptions {
    classifications: DataTableFilterOption[];
    musicalGenres: DataTableFilterOption[];
}

export const columns: ColumnDef<Client>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        meta: { label: 'Nome' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'E-mail',
        meta: { label: 'E-mail', truncate: true },
    },
    {
        id: 'phone',
        accessorKey: 'phone',
        header: 'Telefone',
        meta: { label: 'Telefone' },
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
        label: 'Empresa',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'email',
        label: 'E-mail',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'phone',
        label: 'Telefone',
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

/**
 * Filtros rápidos da toolbar. A DataTable só aceita opções prontas, então a
 * lista de atendentes chega resolvida pela tela (ver `clients/index.tsx`).
 */
export function buildQuickFilters(
    { classifications, musicalGenres }: ClientQuickFilterOptions,
    responsibleUsers: DataTableFilterOption[],
): DataTableQuickFilter[] {
    return [
        {
            id: 'classification',
            label: 'Classificação',
            placeholder: 'Classificação',
            icon: BadgeCheck,
            options: classifications,
            multiple: true,
            operator: 'in',
            clearable: true,
        },
        {
            id: 'musical_genre',
            label: 'Gênero',
            placeholder: 'Gênero',
            icon: Music2,
            options: musicalGenres,
            multiple: true,
            operator: 'in',
            clearable: true,
        },
        {
            id: 'responsible_user_id',
            label: 'Atendente',
            placeholder: 'Atendente',
            icon: UserRoundCheck,
            options: responsibleUsers,
            clearable: true,
        },
    ];
}

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Clients\\Models\\Client',
    columnsEndpoint: apiRoutes.exports.columns,
};
