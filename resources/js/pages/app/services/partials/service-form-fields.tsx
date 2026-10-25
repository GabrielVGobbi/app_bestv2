import { ChevronDown, Settings2, Truck } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
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
    const shouldOpenConfig =
        hasMetadata(service?.metadata) || hasMetadataErrors(errors);
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
    const [basePrice, setBasePrice] = useState(
        centsToDecimal(service?.base_price),
    );

    const basePriceCents = useMemo(
        () => centsFromDecimal(basePrice),
        [basePrice],
    );
    const shouldSubmitMetadata = isConfigOpen || hasMetadata(service?.metadata);

    return (
        <div className="space-y-8">
            <FormSection
                title="Dados comerciais"
                description="Nome, preço e descrição do item do catálogo."
            >
                <input type="hidden" name="base_price" value={basePriceCents} />
                <input type="hidden" name="currency" value="BRL" />
                <input
                    type="hidden"
                    name="is_active"
                    value={(service?.is_active ?? true) ? '1' : '0'}
                />

                <div className="grid gap-4">
                    <FormField label="Nome" required error={errors.name}>
                        <Input
                            id="field-name"
                            name="name"
                            type="text"
                            defaultValue={service?.name ?? ''}
                            required
                            minLength={3}
                            autoComplete="off"
                        />
                    </FormField>

                    <FormField
                        label="Preço base"
                        description="Opcional"
                        error={errors.base_price}
                    >
                        <InputMoney
                            id="field-base-price"
                            value={basePrice}
                            onChange={setBasePrice}
                        />
                    </FormField>

                    <FormField label="Descrição" error={errors.description}>
                        <Textarea
                            id="field-description"
                            name="description"
                            defaultValue={service?.description ?? ''}
                            rows={4}
                        />
                    </FormField>
                </div>
            </FormSection>

            <Collapsible open={isConfigOpen} onOpenChange={setIsConfigOpen}>
                <section className="rounded-lg border border-border bg-muted">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-3 rounded-lg px-4 py-3 text-left transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            aria-expanded={isConfigOpen}
                        >
                            <span className="flex min-w-0 items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary-soft text-primary-soft-foreground">
                                    <Settings2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
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
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                        <div className="rounded-b-lg border-t border-border bg-card px-4 py-4">
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
                                <div className="flex min-h-20 items-center justify-between gap-4 rounded-lg border border-border px-4 py-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="field-requires-period">
                                            Exige período
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Item deve pedir início e fim.
                                        </p>
                                    </div>
                                    <Switch
                                        id="field-requires-period"
                                        checked={requiresPeriod}
                                        onCheckedChange={setRequiresPeriod}
                                    />
                                </div>

                                <div className="flex min-h-20 items-center justify-between gap-4 rounded-lg border border-border px-4 py-3">
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
                                        onCheckedChange={
                                            setRequiresAssetReference
                                        }
                                    />
                                </div>

                                <FormField
                                    id="field-delivery-mode"
                                    label="Modo de entrega"
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
                                            icon={Truck}
                                            placeholder="Selecione"
                                        />
                                        <SelectContent>
                                            {deliveryModes.map(
                                                (option, index) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        index={index}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label="Observação padrão"
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
                                        defaultValue={
                                            metadata.default_notes ?? ''
                                        }
                                        autoComplete="off"
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
