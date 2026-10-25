import { Landmark } from 'lucide-react';
import { useState } from 'react';

import { DatePicker } from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    FinancialAccount,
    FinancialAccountTypeOption,
} from '@/types/app/financial-account';

interface Props {
    financialAccount?: Partial<FinancialAccount>;
    typeOptions: FinancialAccountTypeOption[];
    errors: Record<string, string>;
}

/** InputMoney devolve reais em decimal; o backend recebe centavos inteiros. */
function toCents(value: string): number {
    return Math.round(Number(value) * 100);
}

function fromCents(cents: number): string {
    return (cents / 100).toFixed(2);
}

export default function FinancialAccountFormFields({
    financialAccount,
    typeOptions,
    errors,
}: Props) {
    const record = financialAccount;
    const [active, setActive] = useState(record?.active ?? true);
    const [allowsNegativeBalance, setAllowsNegativeBalance] = useState(
        record?.allows_negative_balance ?? false,
    );
    const [openingBalance, setOpeningBalance] = useState(
        record?.opening_balance_amount ?? 0,
    );
    const [currentBalance, setCurrentBalance] = useState(
        record?.current_balance_amount ?? 0,
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
                        <Select
                            name="type"
                            defaultValue={record?.type ?? 'bank_account'}
                        >
                            <SelectTrigger
                                id="field-type"
                                className="w-full"
                                placeholder="Selecione o tipo"
                                icon={Landmark}
                                error={errors.type}
                            />
                            <SelectContent>
                                {typeOptions.map((option, optionIndex) => (
                                    <SelectItem
                                        key={option.value}
                                        index={optionIndex}
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
                    <input
                        name="active"
                        type="hidden"
                        value={active ? '1' : '0'}
                    />
                    <input
                        name="allows_negative_balance"
                        type="hidden"
                        value={allowsNegativeBalance ? '1' : '0'}
                    />
                    <input
                        name="opening_balance_amount"
                        type="hidden"
                        value={openingBalance}
                    />
                    <input
                        name="current_balance_amount"
                        type="hidden"
                        value={currentBalance}
                    />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            id="field-opening-balance"
                            label="Saldo inicial"
                            variant="stacked"
                            error={errors.opening_balance_amount}
                        >
                            <InputMoney
                                id="field-opening-balance"
                                value={fromCents(openingBalance)}
                                onChange={(value) =>
                                    setOpeningBalance(toCents(value))
                                }
                                allowNegative
                            />
                        </FormField>

                        <FormField
                            id="field-current-balance"
                            label="Saldo atual"
                            variant="stacked"
                            error={errors.current_balance_amount}
                        >
                            <InputMoney
                                id="field-current-balance"
                                value={fromCents(currentBalance)}
                                onChange={(value) =>
                                    setCurrentBalance(toCents(value))
                                }
                                allowNegative
                            />
                        </FormField>

                        <FormField
                            id="field-balance-date"
                            label="Data-base"
                            variant="stacked"
                            error={errors.balance_date}
                        >
                            <DatePicker
                                id="field-balance-date"
                                name="balance_date"
                                defaultValue={record?.balance_date ?? undefined}
                                placeholder="Selecione a data"
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-lg border border-border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Conta ativa
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Permite uso em novas baixas e importações.
                                </span>
                            </span>
                            <Switch
                                checked={active}
                                onCheckedChange={setActive}
                            />
                        </label>

                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-lg border border-border p-4">
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
