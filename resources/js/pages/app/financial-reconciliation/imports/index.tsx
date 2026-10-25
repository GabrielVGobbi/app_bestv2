import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { FileText, FileUp, Inbox, RefreshCw } from 'lucide-react';
import type { FormEvent } from 'react';

import { AsyncSelect } from '@/components/async-select';
import type { AsyncSelectOption } from '@/components/async-select';
import Header from '@/components/dashboard/header';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import { Input } from '@/components/ui/input';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialAccount } from '@/types/app/financial-account';
import type { StatusPresenter } from '@/types/status';

interface BankStatementImport {
    id: number;
    uuid: string;
    company_id: number | null;
    financial_account_id: number | null;
    financial_account: FinancialAccount | null;
    source: 'csv' | 'ofx' | string;
    status: string;
    status_presenter: StatusPresenter | null;
    original_name: string;
    file_hash: string;
    line_count: number;
    duplicate_count: number;
    created_at: string;
}

interface PaginatedImports {
    data: BankStatementImport[];
    links?: unknown;
    meta?: unknown;
}

interface Props {
    imports: PaginatedImports;
}

interface ImportFormData {
    source: 'csv' | 'ofx';
    financial_account_id: string;
    file: File | null;
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function sourceLabel(source: string): string {
    return source.toUpperCase();
}

function financialAccountOption(item: unknown): AsyncSelectOption {
    const financialAccount = item as Partial<FinancialAccount>;
    const description = [
        financialAccount.type_label,
        financialAccount.institution_name,
        financialAccount.account_number,
    ]
        .filter(Boolean)
        .join(' - ');

    return {
        value: String(financialAccount.id ?? ''),
        label: String(
            financialAccount.display_name ?? financialAccount.name ?? '',
        ),
        description,
    };
}

export default function FinancialReconciliationImportsIndex({
    imports,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Conciliação financeira',
                href: '/financial-reconciliation/imports',
            },
        ],
    });

    const form = useForm<ImportFormData>({
        source: 'csv',
        financial_account_id: '',
        file: null,
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post('/financial-reconciliation/imports', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file'),
        });
    }

    return (
        <>
            <Head title="Conciliação financeira" />

            <Header
                title="Conciliação financeira"
                subtitle="Importe extratos para gerar sugestões de match com movimentos financeiros"
            />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nova importação</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                className="grid gap-3 md:grid-cols-[10rem_minmax(14rem,1fr)_minmax(16rem,1fr)_auto]"
                                onSubmit={submit}
                            >
                                <Select
                                    value={form.data.source}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'source',
                                            value as ImportFormData['source'],
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        icon={FileText}
                                        aria-label="Formato do extrato"
                                    />
                                    <SelectContent>
                                        <SelectItem index={0} value="csv">
                                            CSV
                                        </SelectItem>
                                        <SelectItem index={1} value="ofx">
                                            OFX
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <AsyncSelect
                                    value={
                                        form.data.financial_account_id || null
                                    }
                                    endpoint={
                                        apiRoutes.financialAccounts.options
                                    }
                                    params={{ active: '1' }}
                                    transformOption={financialAccountOption}
                                    placeholder="Conta financeira"
                                    searchPlaceholder="Buscar conta..."
                                    emptyMessage="Nenhuma conta ativa encontrada."
                                    onChange={(value) =>
                                        form.setData(
                                            'financial_account_id',
                                            value ?? '',
                                        )
                                    }
                                />

                                <Input
                                    type="file"
                                    aria-label="Arquivo do extrato"
                                    accept=".csv,.ofx,text/csv,application/x-ofx"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        )
                                    }
                                />

                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !form.data.file ||
                                        !form.data.financial_account_id
                                    }
                                >
                                    {form.processing ? (
                                        <RefreshCw className="size-4 animate-spin" />
                                    ) : (
                                        <FileUp className="size-4" />
                                    )}
                                    Importar
                                </Button>
                            </form>

                            {(form.errors.source ||
                                form.errors.file ||
                                form.errors.financial_account_id) && (
                                <Alert variant="destructive" className="mt-3">
                                    <AlertDescription>
                                        {form.errors.source ||
                                            form.errors.file ||
                                            form.errors.financial_account_id}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Importações recentes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {imports.data.length === 0 ? (
                                <EmptyState
                                    icon={Inbox}
                                    title="Nenhum extrato importado"
                                    description="Envie um arquivo CSV ou OFX para iniciar a conciliação."
                                />
                            ) : (
                                <div className="overflow-hidden rounded-md border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted text-left text-xs font-semibold text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-3">
                                                    Arquivo
                                                </th>
                                                <th className="px-3 py-3">
                                                    Fonte
                                                </th>
                                                <th className="px-3 py-3">
                                                    Conta
                                                </th>
                                                <th className="px-3 py-3">
                                                    Status
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Linhas
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Duplicadas
                                                </th>
                                                <th className="px-3 py-3">
                                                    Criado em
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {imports.data.map((item) => (
                                                <tr key={item.uuid}>
                                                    <td className="px-3 py-3 font-medium">
                                                        {item.original_name}
                                                    </td>
                                                    <td className="px-3 py-3 text-muted-foreground">
                                                        {sourceLabel(
                                                            item.source,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 text-muted-foreground">
                                                        {item.financial_account
                                                            ?.display_name ??
                                                            'Conta não informada'}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {item.status_presenter ? (
                                                            <StatusBadge
                                                                presenter={
                                                                    item.status_presenter
                                                                }
                                                            />
                                                        ) : (
                                                            item.status
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 text-right tabular-nums">
                                                        {item.line_count}
                                                    </td>
                                                    <td className="px-3 py-3 text-right tabular-nums">
                                                        {item.duplicate_count}
                                                    </td>
                                                    <td className="px-3 py-3 text-muted-foreground">
                                                        {formatDateTime(
                                                            item.created_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
