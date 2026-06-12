import { router } from '@inertiajs/react';

import { quickUpdate } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { AsyncSelect } from '@/components/async-select';
import { InlineCellEditor } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn } from '@/lib/utils';
import type { Client } from '@/types/app/client';

type ClientQuickUpdateField = 'name' | 'responsible_user_id';

export type ClientQuickUpdateColumn = {
    field: ClientQuickUpdateField;
    label: string;
    description?: string;
};

interface ClientQuickUpdatePanelProps {
    client: Client;
    columns: ClientQuickUpdateColumn[];
    className?: string;
    onSaved?: () => void;
    reloadProps?: string[] | null;
}

function errorMessage(errors: Record<string, string>): string {
    return Object.values(errors)[0] ?? 'Não foi possível atualizar o cliente.';
}

function fieldValue(client: Client, field: ClientQuickUpdateField): string {
    if (field === 'responsible_user_id') {
        return client.responsible_user_id ? String(client.responsible_user_id) : '';
    }

    return client.name ?? '';
}

function displayValue(client: Client, field: ClientQuickUpdateField): string {
    if (field === 'responsible_user_id') {
        return client.responsible_user?.name ?? 'Sem responsável';
    }

    return client.name ?? '-';
}

function persistClientField(
    clientUuid: string,
    field: ClientQuickUpdateField,
    value: string,
    onSaved?: () => void,
    reloadProps: string[] | null = ['clientData'],
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.patch(
            quickUpdate.url(clientUuid),
            {
                field,
                value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                ...(reloadProps ? { only: reloadProps } : {}),
                onSuccess: () => {
                    onSaved?.();
                    resolve();
                },
                onError: (errors) => reject(new Error(errorMessage(errors))),
            },
        );
    });
}

export function ClientQuickUpdatePanel({
    client,
    columns,
    className,
    onSaved,
    reloadProps = ['clientData'],
}: ClientQuickUpdatePanelProps) {
    function renderControl(column: ClientQuickUpdateColumn) {
        if (column.field === 'responsible_user_id') {
            return (
                <InlineCellEditor
                    mode="modal"
                    label="Alterar responsável"
                    modalTitle="Alterar responsável"
                    modalDescription="Selecione um usuário comercial para atender este cliente."
                    value={fieldValue(client, column.field)}
                    displayValue={displayValue(client, column.field)}
                    onSave={(value) =>
                        persistClientField(
                            client.uuid,
                            column.field,
                            value,
                            onSaved,
                            reloadProps,
                        )
                    }
                >
                    {({ value, onChange, isLoading }) => (
                        <AsyncSelect
                            value={value || null}
                            initialLabel={client.responsible_user?.name}
                            endpoint={apiRoutes.users.index}
                            params={{ role: 'commercial' }}
                            placeholder="Selecione o responsável"
                            searchPlaceholder="Buscar por nome ou email..."
                            emptyMessage="Nenhum usuário comercial encontrado."
                            clearable
                            disabled={isLoading}
                            onChange={(nextValue) => onChange(nextValue ?? '')}
                        />
                    )}
                </InlineCellEditor>
            );
        }

        return (
            <InlineCellEditor
                mode="popover"
                label="Alterar nome"
                popoverWidth={320}
                value={fieldValue(client, column.field)}
                displayValue={displayValue(client, column.field)}
                validate={(value) =>
                    value.trim().length === 0 ? 'Informe um nome.' : null
                }
                onSave={(value) =>
                    persistClientField(
                        client.uuid,
                        column.field,
                        value,
                        onSaved,
                        reloadProps,
                    )
                }
            >
                {({ value, onChange, save, cancel, isLoading }) => (
                    <Input
                        value={value}
                        disabled={isLoading}
                        autoFocus
                        className="h-8"
                        onChange={(event) => onChange(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                void save();
                            }

                            if (event.key === 'Escape') {
                                cancel();
                            }
                        }}
                    />
                )}
            </InlineCellEditor>
        );
    }

    return (
        <dl className={cn('grid gap-x-8 gap-y-4 sm:grid-cols-2', className)}>
            {columns.map((column) => (
                <div key={column.field} className="min-w-0">
                    <dt className="text-xs font-medium text-muted-foreground">
                        {column.label}
                    </dt>
                    <dd className="mt-1 min-w-0 text-sm font-medium text-foreground">
                        {renderControl(column)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
