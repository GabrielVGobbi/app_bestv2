import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { InputDocument } from '@/components/ui/input-document';
import { InputPhone } from '@/components/ui/input-phone';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Supplier } from '@/types/app/supplier';
import { useState } from 'react';

interface Props {
    supplier?: Partial<Supplier>;
    errors: Record<string, string>;
}

const statusOptions: Array<{ label: string; value: Supplier['status'] }> = [
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
    { label: 'Arquivado', value: 'archived' },
];

export default function SupplierFormFields({ supplier, errors }: Props) {
    const record = supplier;
    const [status, setStatus] = useState<Supplier['status']>(
        record?.status ?? 'active',
    );

    return (
        <div className="space-y-8">
            <FormSection
                title="Identificação"
                description="Dados principais do fornecedor."
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
                            autoComplete="organization"
                            aria-invalid={!!errors.name}
                        />
                    </FormField>

                    <FormField
                        id="field-company-name"
                        label="Razão social"
                        variant="stacked"
                        error={errors.company_name}
                    >
                        <Input
                            id="field-company-name"
                            name="company_name"
                            type="text"
                            defaultValue={record?.company_name ?? ''}
                            autoComplete="organization"
                            aria-invalid={!!errors.company_name}
                        />
                    </FormField>

                    <FormField
                        id="field-document"
                        label="CPF/CNPJ"
                        variant="stacked"
                        error={errors.document}
                    >
                        <InputDocument
                            id="field-document"
                            name="document"
                            defaultValue={record?.document ?? ''}
                            aria-invalid={!!errors.document}
                        />
                    </FormField>

                    <FormField
                        id="field-status"
                        label="Status"
                        variant="stacked"
                        error={errors.status}
                    >
                        <input name="status" type="hidden" value={status} />
                        <Select
                            value={status}
                            onValueChange={(value) =>
                                setStatus(value as Supplier['status'])
                            }
                        >
                            <SelectTrigger
                                id="field-status"
                                className="w-full"
                                aria-invalid={!!errors.status}
                            >
                                <SelectValue placeholder="Selecione o status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
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
                </div>
            </FormSection>

            <FormSection
                title="Contato"
                description="Canais financeiros e administrativos."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="field-email"
                        label="Email"
                        variant="stacked"
                        error={errors.email}
                    >
                        <Input
                            id="field-email"
                            name="email"
                            type="email"
                            defaultValue={record?.email ?? ''}
                            autoComplete="email"
                            aria-invalid={!!errors.email}
                        />
                    </FormField>

                    <FormField
                        id="field-phone"
                        label="Telefone"
                        variant="stacked"
                        error={errors.phone}
                    >
                        <InputPhone
                            id="field-phone"
                            name="phone"
                            defaultValue={record?.phone ?? ''}
                            aria-invalid={!!errors.phone}
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormField
                id="field-notes"
                label="Observações"
                variant="stacked"
                error={errors.notes}
            >
                <textarea
                    id="field-notes"
                    name="notes"
                    defaultValue={record?.notes ?? ''}
                    rows={4}
                    className="border-input bg-background/60 placeholder:text-muted-foreground flex min-h-24 w-full rounded-md border px-3 py-2 text-base shadow-xs outline-none transition-[border-color,box-shadow,color] focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm"
                    aria-invalid={!!errors.notes}
                />
            </FormField>
        </div>
    );
}
