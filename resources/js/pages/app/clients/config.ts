import type { ColumnDef } from '@tanstack/react-table';
import { BadgeCheck, Music2, UserRoundCheck } from 'lucide-react';
import { createElement } from 'react';
import { show } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
    DataTableFilterOption,
    DataTableQuickFilter,
} from '@/components/data-table';
import { RecordIdentity } from '@/components/record-identity';

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
        size: 300,
        cell: ({ row }) =>
            createElement(RecordIdentity, {
                name: row.original.name,
                description: row.original.company_name,
                href: show.url(row.original.uuid),
            }),
        meta: { label: 'Nome' },
    },
    {
        id: 'email',
        accessorKey: 'email',
        header: 'Email',
        size: 260,
        meta: { label: 'Email', truncate: true },
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
        label: 'Email',
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

export function buildQuickFilters({
    classifications,
    musicalGenres,
}: ClientQuickFilterOptions): DataTableQuickFilter[] {
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
            type: 'async-select',
            asyncSelect: {
                endpoint: apiRoutes.users.index,
                params: { role: 'commercial' },
                searchPlaceholder: 'Buscar por nome ou email...',
                emptyMessage: 'Nenhum usuário comercial encontrado.',
            },
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
