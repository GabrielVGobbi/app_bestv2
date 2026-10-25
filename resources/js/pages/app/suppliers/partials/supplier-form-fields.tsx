import { Tag } from 'lucide-react';
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
import { InputDocument } from '@/components/ui/input-document';
import { InputPhone } from '@/components/ui/input-phone';
import { Textarea } from '@/components/ui/textarea';
import type { Supplier } from '@/types/app/supplier';

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
                        />
                    </FormField>

                    <FormField
                        id="field-status"
                        label="Status"
                        variant="stacked"
                        error={errors.status}
                    >
                        <Select
                            value={status}
                            onValueChange={(value) =>
                                setStatus(value as Supplier['status'])
                            }
                        >
                            <SelectTrigger
                                id="field-status"
                                className="w-full"
                                placeholder="Selecione o status"
                                icon={Tag}
                                error={errors.status}
                            />
                            <SelectContent>
                                {statusOptions.map((option, index) => (
                                    <SelectItem
                                        key={option.value}
                                        index={index}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input name="status" type="hidden" value={status} />
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
                        label="E-mail"
                        variant="stacked"
                        error={errors.email}
                    >
                        <Input
                            id="field-email"
                            name="email"
                            type="email"
                            defaultValue={record?.email ?? ''}
                            autoComplete="email"
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
                <Textarea
                    id="field-notes"
                    name="notes"
                    defaultValue={record?.notes ?? ''}
                    rows={4}
                />
            </FormField>
        </div>
    );
}
