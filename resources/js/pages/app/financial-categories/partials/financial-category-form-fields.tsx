import { Tag } from 'lucide-react';
import { useState } from 'react';

import FinancialCategoryTreeSelect from '@/components/financial/financial-category-tree-select';
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
    FinancialCategory,
    FinancialCategoryType,
    FinancialCategoryTypeOption,
} from '@/types/app/financial-category';

interface Props {
    category?: Partial<FinancialCategory>;
    typeOptions: FinancialCategoryTypeOption[];
    errors: Record<string, string>;
}

export default function FinancialCategoryFormFields({
    category,
    typeOptions,
    errors,
}: Props) {
    const record = category;
    const [type, setType] = useState<FinancialCategoryType>(
        record?.type ?? 'expense',
    );
    const [parentId, setParentId] = useState<string | null>(
        record?.parent_id ? String(record.parent_id) : null,
    );
    const [isActive, setIsActive] = useState(record?.is_active ?? true);
    const [color, setColor] = useState(record?.color ?? '#64748b');

    return (
        <div className="space-y-8">
            <FormSection
                title="Classificação"
                description="Estrutura gerencial usada em lançamentos, filtros e relatórios."
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
                            value={type}
                            onValueChange={(value) => {
                                setType(value as FinancialCategoryType);
                                setParentId(null);
                            }}
                        >
                            <SelectTrigger
                                id="field-type"
                                className="w-full"
                                placeholder="Selecione o tipo"
                                icon={Tag}
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
                        id="field-parent"
                        label="Categoria pai"
                        variant="stacked"
                        error={errors.parent_id}
                    >
                        <FinancialCategoryTreeSelect
                            name="parent_id"
                            value={parentId}
                            onChange={setParentId}
                            type={type}
                            mode="filter"
                            placeholder="Categoria raiz"
                            initialLabel={
                                record?.breadcrumb
                                    ?.slice(0, -1)
                                    .map((item) => item.name)
                                    .join(' > ') || undefined
                            }
                        />
                    </FormField>

                    <FormField
                        id="field-color"
                        label="Cor"
                        variant="stacked"
                        error={errors.color}
                    >
                        <input name="color" type="hidden" value={color} />
                        <div className="flex items-center gap-2">
                            <Input
                                id="field-color"
                                type="color"
                                value={color}
                                onChange={(event) =>
                                    setColor(event.target.value)
                                }
                                className="w-14 px-1 py-1"
                                aria-invalid={!!errors.color}
                            />
                            <Input
                                type="text"
                                value={color}
                                onChange={(event) =>
                                    setColor(event.target.value)
                                }
                                aria-label="Cor em hexadecimal"
                                aria-invalid={!!errors.color}
                            />
                        </div>
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
                title="Uso"
                description="Categorias inativas ficam no histórico, mas não devem entrar em novos lançamentos."
            >
                <div className="space-y-4">
                    <input
                        name="is_active"
                        type="hidden"
                        value={isActive ? '1' : '0'}
                    />
                    <label className="flex items-center justify-between gap-4 rounded-lg border border-border p-4">
                        <span>
                            <span className="block text-sm font-medium">
                                Categoria ativa
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

                    <FormField
                        id="field-description"
                        label="Descrição"
                        variant="stacked"
                        error={errors.description}
                    >
                        <Textarea
                            id="field-description"
                            name="description"
                            defaultValue={record?.description ?? ''}
                            rows={4}
                            aria-invalid={!!errors.description}
                        />
                    </FormField>
                </div>
            </FormSection>
        </div>
    );
}
