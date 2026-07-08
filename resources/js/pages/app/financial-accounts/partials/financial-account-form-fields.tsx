import { useState } from 'react';

import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    FinancialAccount,
    FinancialAccountType,
    FinancialAccountTypeOption,
} from '@/types/app/financial-account';

interface Props {
    financialAccount?: Partial<FinancialAccount>;
    typeOptions: FinancialAccountTypeOption[];
    errors: Record<string, string>;
}

export default function FinancialAccountFormFields({
    financialAccount,
    typeOptions,
    errors,
}: Props) {
    const record = financialAccount;
    const [type, setType] = useState<FinancialAccountType>(
        record?.type ?? 'bank_account',
    );
    const [active, setActive] = useState(record?.active ?? true);
    const [allowsNegativeBalance, setAllowsNegativeBalance] = useState(
        record?.allows_negative_balance ?? false,
    );

    return (
        <div className="space-y-8">
            <FormSection
                title="Identificação"
                description="Conta usada em baixas, movimentos e conciliação bancária."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-name"
                        label="Nome"
                        variant="stacked"
                        required
                        error={errors.name}
                    >
                        <Input
                            id="field-name"
                            name="name"
                            type="text"
                            defaultValue={record?.name ?? ''}
                            required
                            aria-invalid={!!errors.name}
                        />
                    </FormField>

                    <FormField
                        id="field-company-id"
                        label="Empresa"
                        variant="stacked"
                        required
                        error={errors.company_id}
                    >
                        <Input
                            id="field-company-id"
                            name="company_id"
                            type="number"
                            min={1}
                            defaultValue={record?.company_id ?? ''}
                            required
                            aria-invalid={!!errors.company_id}
                        />
                    </FormField>

                    <FormField
                        id="field-type"
                        label="Tipo"
                        variant="stacked"
                        required
                        error={errors.type}
                    >
                        <input name="type" type="hidden" value={type} />
                        <Select
                            value={type}
                            onValueChange={(value) =>
                                setType(value as FinancialAccountType)
                            }
                        >
                            <SelectTrigger
                                id="field-type"
                                className="w-full"
                                aria-invalid={!!errors.type}
                            >
                                <SelectValue placeholder="Selecione o tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                {typeOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        id="field-currency"
                        label="Moeda"
                        variant="stacked"
                        error={errors.currency}
                    >
                        <Input
                            id="field-currency"
                            name="currency"
                            type="text"
                            maxLength={3}
                            defaultValue={record?.currency ?? 'BRL'}
                            aria-invalid={!!errors.currency}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                title="Dados bancários"
                description="Campos opcionais para identificar banco, agência e conta."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-institution"
                        label="Banco ou instituição"
                        variant="stacked"
                        error={errors.institution_name}
                    >
                        <Input
                            id="field-institution"
                            name="institution_name"
                            type="text"
                            defaultValue={record?.institution_name ?? ''}
                            aria-invalid={!!errors.institution_name}
                        />
                    </FormField>

                    <FormField
                        id="field-document"
                        label="Documento"
                        variant="stacked"
                        error={errors.document_number}
                    >
                        <Input
                            id="field-document"
                            name="document_number"
                            type="text"
                            defaultValue={record?.document_number ?? ''}
                            aria-invalid={!!errors.document_number}
                        />
                    </FormField>

                    <FormField
                        id="field-agency"
                        label="Agência"
                        variant="stacked"
                        error={errors.agency_number}
                    >
                        <Input
                            id="field-agency"
                            name="agency_number"
                            type="text"
                            defaultValue={record?.agency_number ?? ''}
                            aria-invalid={!!errors.agency_number}
                        />
                    </FormField>

                    <div className="grid grid-cols-[1fr_5rem] gap-3">
                        <FormField
                            id="field-account"
                            label="Conta"
                            variant="stacked"
                            error={errors.account_number}
                        >
                            <Input
                                id="field-account"
                                name="account_number"
                                type="text"
                                defaultValue={record?.account_number ?? ''}
                                aria-invalid={!!errors.account_number}
                            />
                        </FormField>

                        <FormField
                            id="field-account-digit"
                            label="Dígito"
                            variant="stacked"
                            error={errors.account_digit}
                        >
                            <Input
                                id="field-account-digit"
                                name="account_digit"
                                type="text"
                                defaultValue={record?.account_digit ?? ''}
                                aria-invalid={!!errors.account_digit}
                            />
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection
                title="Saldo e operação"
                description="O saldo atual é atualizado automaticamente por baixas e estornos."
            >
                <div className="space-y-4">
                    <input name="active" type="hidden" value={active ? '1' : '0'} />
                    <input
                        name="allows_negative_balance"
                        type="hidden"
                        value={allowsNegativeBalance ? '1' : '0'}
                    />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            id="field-opening-balance"
                            label="Saldo inicial"
                            variant="stacked"
                            error={errors.opening_balance_amount}
                        >
                            <Input
                                id="field-opening-balance"
                                name="opening_balance_amount"
                                type="number"
                                step={1}
                                defaultValue={record?.opening_balance_amount ?? 0}
                                aria-invalid={!!errors.opening_balance_amount}
                            />
                        </FormField>

                        <FormField
                            id="field-current-balance"
                            label="Saldo atual"
                            variant="stacked"
                            error={errors.current_balance_amount}
                        >
                            <Input
                                id="field-current-balance"
                                name="current_balance_amount"
                                type="number"
                                step={1}
                                defaultValue={record?.current_balance_amount ?? 0}
                                aria-invalid={!!errors.current_balance_amount}
                            />
                        </FormField>

                        <FormField
                            id="field-balance-date"
                            label="Data-base"
                            variant="stacked"
                            error={errors.balance_date}
                        >
                            <Input
                                id="field-balance-date"
                                name="balance_date"
                                type="date"
                                defaultValue={record?.balance_date ?? ''}
                                aria-invalid={!!errors.balance_date}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-md border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Conta ativa
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Permite uso em novas baixas e importações.
                                </span>
                            </span>
                            <Switch checked={active} onCheckedChange={setActive} />
                        </label>

                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-md border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Permite saldo negativo
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Autoriza saídas acima do saldo realizado.
                                </span>
                            </span>
                            <Switch
                                checked={allowsNegativeBalance}
                                onCheckedChange={setAllowsNegativeBalance}
                            />
                        </label>
                    </div>

                    <FormField
                        id="field-notes"
                        label="Observações internas"
                        variant="stacked"
                        error={errors['metadata.notes']}
                    >
                        <Textarea
                            id="field-notes"
                            name="metadata[notes]"
                            defaultValue={
                                typeof record?.metadata?.notes === 'string'
                                    ? record.metadata.notes
                                    : ''
                            }
                            rows={4}
                            aria-invalid={!!errors['metadata.notes']}
                        />
                    </FormField>
                </div>
            </FormSection>
        </div>
    );
}
