import { CreditCard } from 'lucide-react';
import { useState } from 'react';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    PaymentMethod,
    PaymentMethodTypeOption,
} from '@/types/app/payment-method';

interface Props {
    paymentMethod?: Partial<PaymentMethod>;
    typeOptions: PaymentMethodTypeOption[];
    errors: Record<string, string>;
}

export default function PaymentMethodFormFields({
    paymentMethod,
    typeOptions,
    errors,
}: Props) {
    const record = paymentMethod;
    const [requiresReference, setRequiresReference] = useState(
        record?.requires_reference ?? false,
    );
    const [isActive, setIsActive] = useState(record?.is_active ?? true);

    return (
        <div className="space-y-8">
            <FormSection
                title="Identificação"
                description="Cadastro mestre usado em baixas, filtros e conciliação futura."
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
                        id="field-code"
                        label="Código"
                        variant="stacked"
                        error={errors.code}
                    >
                        <Input
                            id="field-code"
                            name="code"
                            type="text"
                            defaultValue={record?.code ?? ''}
                            autoComplete="off"
                            aria-invalid={!!errors.code}
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
                            defaultValue={record?.type ?? 'other'}
                        >
                            <SelectTrigger
                                id="field-type"
                                className="w-full"
                                placeholder="Selecione o tipo"
                                icon={CreditCard}
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
                        id="field-sort-order"
                        label="Ordenação"
                        variant="stacked"
                        error={errors.sort_order}
                    >
                        <Input
                            id="field-sort-order"
                            name="sort_order"
                            type="number"
                            min={0}
                            defaultValue={record?.sort_order ?? 0}
                            aria-invalid={!!errors.sort_order}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                title="Uso operacional"
                description="Estas flags orientam novas baixas financeiras no v2."
            >
                <div className="space-y-4">
                    <input
                        name="requires_reference"
                        type="hidden"
                        value={requiresReference ? '1' : '0'}
                    />
                    <input
                        name="is_active"
                        type="hidden"
                        value={isActive ? '1' : '0'}
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-lg border border-border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Exige referência
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Solicita comprovante, código ou
                                    identificador na baixa.
                                </span>
                            </span>
                            <Switch
                                checked={requiresReference}
                                onCheckedChange={setRequiresReference}
                            />
                        </label>

                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-lg border border-border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Método ativo
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Permite uso em novas operações financeiras.
                                </span>
                            </span>
                            <Switch
                                checked={isActive}
                                onCheckedChange={setIsActive}
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
