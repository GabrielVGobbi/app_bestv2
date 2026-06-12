import { ChevronDown, Settings2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import FormField from '@/components/ui/forms/form-field';
import FormSection from '@/components/ui/forms/form-section';
import { Input } from '@/components/ui/input';
import { InputMoney } from '@/components/ui/input-money';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type {
    Service,
    ServiceDeliveryMode,
    ServiceMetadata,
} from '@/types/app/service';

interface Props {
    service?: Partial<Service>;
    errors: Record<string, string>;
}

const deliveryModes: { value: ServiceDeliveryMode; label: string }[] = [
    { value: 'manual', label: 'Manual' },
    { value: 'digital', label: 'Digital' },
    { value: 'operational', label: 'Operacional' },
    { value: 'external', label: 'Externo' },
];

function centsToDecimal(value: number | undefined): string {
    return ((value ?? 0) / 100).toFixed(2);
}

function centsFromDecimal(value: string): string {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '0';
    }

    return String(Math.round(amount * 100));
}

function hasMetadata(metadata: ServiceMetadata | null | undefined): boolean {
    if (!metadata) {
        return false;
    }

    return Boolean(
        metadata.requires_period ||
            metadata.requires_asset_reference ||
            metadata.delivery_mode ||
            metadata.default_notes,
    );
}

function hasMetadataErrors(errors: Record<string, string>): boolean {
    return Object.keys(errors).some((key) => key.startsWith('metadata.'));
}

export default function ServiceFormFields({ service, errors }: Props) {
    const metadata = service?.metadata ?? {};
    const shouldOpenConfig = hasMetadata(service?.metadata) || hasMetadataErrors(errors);
    const [isConfigOpen, setIsConfigOpen] = useState(shouldOpenConfig);
    const [requiresPeriod, setRequiresPeriod] = useState(
        metadata.requires_period ?? false,
    );
    const [requiresAssetReference, setRequiresAssetReference] = useState(
        metadata.requires_asset_reference ?? false,
    );
    const [deliveryMode, setDeliveryMode] = useState<ServiceDeliveryMode>(
        metadata.delivery_mode ?? 'manual',
    );
    const [basePrice, setBasePrice] = useState(centsToDecimal(service?.base_price));

    const basePriceCents = useMemo(() => centsFromDecimal(basePrice), [basePrice]);
    const shouldSubmitMetadata = isConfigOpen || hasMetadata(service?.metadata);

    return (
        <div className="space-y-8">
            <FormSection
                title="Dados comerciais"
                description="Cadastro base do catálogo. O Preço e apenas uma sugestao para vendas futuras."
            >
                <input type="hidden" name="base_price" value={basePriceCents} />
                <input type="hidden" name="currency" value="BRL" />
                <input
                    type="hidden"
                    name="is_active"
                    value={(service?.is_active ?? true) ? '1' : '0'}
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
                            defaultValue={service?.name ?? ''}
                            required
                            minLength={3}
                            autoComplete="off"
                            aria-invalid={!!errors.name}
                        />
                    </FormField>

                    <FormField
                        id="field-base-price"
                        label="Preço Base (opcional)"
                        variant="stacked"
                        error={errors.base_price}
                    >
                        <InputMoney
                            id="field-base-price"
                            value={basePrice}
                            onChange={setBasePrice}
                            aria-invalid={!!errors.base_price}
                        />
                    </FormField>

                    <FormField
                        id="field-description"
                        label="Descricao"
                        variant="stacked"
                        error={errors.description}
                    >
                        <textarea
                            id="field-description"
                            name="description"
                            defaultValue={service?.description ?? ''}
                            rows={4}
                            className="border-input bg-background placeholder:text-muted-foreground flex min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[border-color,box-shadow,color] focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15"
                            aria-invalid={!!errors.description}
                        />
                    </FormField>
                </div>
            </FormSection>

            <Collapsible open={isConfigOpen} onOpenChange={setIsConfigOpen}>
                <section className="rounded-md border bg-muted/20">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <span className="flex min-w-0 items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-background">
                                    <Settings2 className="size-4" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold">
                                        Configurações do item de venda
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        Opcional para orientar a venda futura
                                    </span>
                                </span>
                            </span>
                            <ChevronDown
                                className={cn(
                                    'size-4 shrink-0 text-muted-foreground transition-transform',
                                    isConfigOpen && 'rotate-180',
                                )}
                            />
                        </button>
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                        <div className="border-t bg-background px-4 py-4">
                            {shouldSubmitMetadata ? (
                                <>
                                    <input
                                        type="hidden"
                                        name="metadata[requires_period]"
                                        value={requiresPeriod ? '1' : '0'}
                                    />
                                    <input
                                        type="hidden"
                                        name="metadata[requires_asset_reference]"
                                        value={
                                            requiresAssetReference ? '1' : '0'
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="metadata[delivery_mode]"
                                        value={deliveryMode}
                                    />
                                </>
                            ) : null}

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="flex min-h-20 items-center justify-between gap-4 rounded-md border px-4 py-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="field-requires-period">
                                            Exige periodo
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Item deve pedir inicio e fim.
                                        </p>
                                    </div>
                                    <Switch
                                        id="field-requires-period"
                                        checked={requiresPeriod}
                                        onCheckedChange={setRequiresPeriod}
                                    />
                                </div>

                                <div className="flex min-h-20 items-center justify-between gap-4 rounded-md border px-4 py-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="field-requires-asset-reference">
                                            Exige ativo
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Venda aponta o ativo negociado.
                                        </p>
                                    </div>
                                    <Switch
                                        id="field-requires-asset-reference"
                                        checked={requiresAssetReference}
                                        onCheckedChange={setRequiresAssetReference}
                                    />
                                </div>

                                <FormField
                                    id="field-delivery-mode"
                                    label="Modo de entrega"
                                    variant="stacked"
                                    error={errors['metadata.delivery_mode']}
                                >
                                    <Select
                                        value={deliveryMode}
                                        onValueChange={(value) =>
                                            setDeliveryMode(
                                                value as ServiceDeliveryMode,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="field-delivery-mode"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Selecione" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {deliveryModes.map((option) => (
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
                                    id="field-default-notes"
                                    label="Observação padrao"
                                    variant="stacked"
                                    error={errors['metadata.default_notes']}
                                >
                                    <Input
                                        id="field-default-notes"
                                        name={
                                            shouldSubmitMetadata
                                                ? 'metadata[default_notes]'
                                                : undefined
                                        }
                                        type="text"
                                        defaultValue={metadata.default_notes ?? ''}
                                        autoComplete="off"
                                        aria-invalid={
                                            !!errors['metadata.default_notes']
                                        }
                                    />
                                </FormField>
                            </div>
                        </div>
                    </CollapsibleContent>
                </section>
            </Collapsible>
        </div>
    );
}
