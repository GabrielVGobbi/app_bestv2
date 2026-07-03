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
    PaymentMethod,
    PaymentMethodType,
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
    const [type, setType] = useState<PaymentMethodType>(
        record?.type ?? 'other',
    );
    const [requiresReference, setRequiresReference] = useState(
        record?.requires_reference ?? false,
    );
    const [isActive, setIsActive] = useState(record?.is_active ?? true);

    return (
        <div className="space-y-8">
            <FormSection
                title="Identificacao"
                description="Cadastro mestre usado em baixas, filtros e conciliacao futura."
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
                        label="Codigo"
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
                        <input name="type" type="hidden" value={type} />
                        <Select
                            value={type}
                            onValueChange={(value) =>
                                setType(value as PaymentMethodType)
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
                        id="field-sort-order"
                        label="Ordenacao"
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
                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-md border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Exige referencia
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Solicita comprovante, codigo ou identificador na baixa.
                                </span>
                            </span>
                            <Switch
                                checked={requiresReference}
                                onCheckedChange={setRequiresReference}
                            />
                        </label>

                        <label className="flex min-h-24 items-center justify-between gap-4 rounded-md border p-4">
                            <span>
                                <span className="block text-sm font-medium">
                                    Metodo ativo
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Permite uso em novas operacoes financeiras.
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
                        label="Observacoes internas"
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
