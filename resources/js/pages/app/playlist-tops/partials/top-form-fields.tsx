import { useState } from 'react';

import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { Top } from '@/types/app/top';

interface Props {
    top?: Partial<Top>;
    errors: Record<string, string>;
}

export default function TopFormFields({ top, errors }: Props) {
    const [isActive, setIsActive] = useState(top?.is_active ?? true);

    return (
        <div className="space-y-8">
            <FormSection
                title="Dados comerciais"
                description="Catálogo global de posições comerciais reutilizáveis nas playlists."
            >
                <input
                    type="hidden"
                    name="is_active"
                    value={isActive ? '1' : '0'}
                />

                <div className="grid gap-4">
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
                            defaultValue={top?.name ?? ''}
                            required
                            minLength={3}
                            autoComplete="off"
                            aria-invalid={!!errors.name}
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="field-default-slots"
                            label="Vagas padrão"
                            variant="stacked"
                            required
                            error={errors.default_slots_quantity}
                        >
                            <Input
                                id="field-default-slots"
                                name="default_slots_quantity"
                                type="number"
                                min={1}
                                step={1}
                                defaultValue={top?.default_slots_quantity ?? 1}
                                required
                                inputMode="numeric"
                                aria-invalid={!!errors.default_slots_quantity}
                            />
                        </FormField>

                        <FormField
                            id="field-sort-order"
                            label="Ordem"
                            variant="stacked"
                            required
                            error={errors.sort_order}
                        >
                            <Input
                                id="field-sort-order"
                                name="sort_order"
                                type="number"
                                min={0}
                                step={1}
                                defaultValue={top?.sort_order ?? 0}
                                required
                                inputMode="numeric"
                                aria-invalid={!!errors.sort_order}
                            />
                        </FormField>
                    </div>

                    <FormField
                        id="field-description"
                        label="Descrição"
                        variant="stacked"
                        error={errors.description}
                    >
                        <Textarea
                            id="field-description"
                            name="description"
                            defaultValue={top?.description ?? ''}
                            rows={4}
                            aria-invalid={!!errors.description}
                        />
                    </FormField>

                    <div className="flex min-h-20 items-center justify-between gap-4 rounded-lg border border-border bg-muted px-4 py-3">
                        <div className="space-y-1">
                            <Label htmlFor="field-is-active">Ativo</Label>
                            <p className="text-xs text-muted-foreground">
                                TOP disponível para novos vínculos com
                                playlists.
                            </p>
                        </div>
                        <Switch
                            id="field-is-active"
                            checked={isActive}
                            onCheckedChange={setIsActive}
                        />
                    </div>
                </div>
            </FormSection>
        </div>
    );
}
