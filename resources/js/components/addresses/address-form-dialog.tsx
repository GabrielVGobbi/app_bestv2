import { Loader2, Save, Search, Star } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { ActionInput } from '@/components/ui/action-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { SwitchOption } from '@/components/ui/switch-option';
import type { ValidationErrors } from '@/lib/http';
import type { Address } from '@/types/address';

export type AddressFormFields =
    | 'name'
    | 'street'
    | 'number'
    | 'district'
    | 'neighborhood'
    | 'city'
    | 'city_code'
    | 'state'
    | 'zipcode'
    | 'complement'
    | 'country'
    | 'state_registration'
    | 'is_default';

export interface AddressFormData {
    name: string;
    street: string;
    number: string;
    district: string;
    neighborhood: string;
    city: string;
    city_code: string;
    state: string;
    zipcode: string;
    complement: string;
    country: string;
    state_registration: string;
    is_default: boolean;
}

interface AddressFormDialogProps {
    open: boolean;
    address?: Address | null;
    processing?: boolean;
    errors?: ValidationErrors<AddressFormFields>;
    onOpenChange: (open: boolean) => void;
    onSubmit: (data: AddressFormData) => void;
}

type CepLookupStatus = 'idle' | 'loading' | 'found' | 'invalid' | 'not-found' | 'error';

type ViaCepResponse = {
    cep?: string;
    logradouro?: string;
    complemento?: string;
    bairro?: string;
    localidade?: string;
    uf?: string;
    ibge?: string;
    erro?: boolean;
};

const emptyAddressForm: AddressFormData = {
    name: '',
    street: '',
    number: '',
    district: '',
    neighborhood: '',
    city: '',
    city_code: '',
    state: '',
    zipcode: '',
    complement: '',
    country: 'Brasil',
    state_registration: '',
    is_default: false,
};

function formDataFromAddress(address?: Address | null): AddressFormData {
    if (!address) {
        return emptyAddressForm;
    }

    return {
        name: address.name ?? '',
        street: address.street ?? '',
        number: address.number ?? '',
        district: address.district ?? '',
        neighborhood: address.neighborhood ?? '',
        city: address.city ?? '',
        city_code: address.city_code ?? '',
        state: address.state ?? '',
        zipcode: address.zipcode ?? '',
        complement: address.complement ?? '',
        country: address.country ?? 'Brasil',
        state_registration: address.state_registration ?? '',
        is_default: address.is_default,
    };
}

function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

function formatCep(value: string): string {
    const digits = onlyDigits(value).slice(0, 8);

    if (digits.length <= 5) {
        return digits;
    }

    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

export function AddressFormDialog({
    open,
    address,
    processing = false,
    errors = {},
    onOpenChange,
    onSubmit,
}: AddressFormDialogProps) {
    const [form, setForm] = useState<AddressFormData>(() => formDataFromAddress(address));
    const [cepStatus, setCepStatus] = useState<CepLookupStatus>('idle');
    const title = address ? 'Editar Endereço' : 'Novo Endereço';

    function setField<TField extends keyof AddressFormData>(
        field: TField,
        value: AddressFormData[TField],
    ): void {
        setForm((current) => ({ ...current, [field]: value }));
    }

    async function lookupCep(): Promise<void> {
        const cep = onlyDigits(form.zipcode);

        if (cep.length !== 8) {
            setCepStatus('invalid');

            return;
        }

        setCepStatus('loading');

        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setCepStatus('error');

                return;
            }

            const data = (await response.json()) as ViaCepResponse;

            if (data.erro) {
                setCepStatus('not-found');

                return;
            }

            setForm((current) => ({
                ...current,
                zipcode: formatCep(data.cep ?? cep),
                street: data.logradouro ?? current.street,
                complement: data.complemento || current.complement,
                district: data.bairro ?? current.district,
                neighborhood: data.bairro ?? current.neighborhood,
                city: data.localidade ?? current.city,
                state: data.uf ?? current.state,
                city_code: data.ibge ?? current.city_code,
                country: current.country || 'Brasil',
            }));
            setCepStatus('found');
        } catch {
            setCepStatus('error');
        }
    }

    function handleCepBlur(): void {
        if (onlyDigits(form.zipcode).length === 8 && cepStatus !== 'found') {
            void lookupCep();
        }
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        onSubmit(form);
    }

    const cepMessage = {
        idle: 'Digite o CEP e busque para preencher rua, bairro, cidade e UF.',
        loading: 'Consultando CEP...',
        found: 'Endereço preenchido pelo CEP. Confira numero e complemento.',
        invalid: 'Informe um CEP com 8 digitos.',
        'not-found': 'CEP nao encontrado. Preencha o Endereço manualmente.',
        error: 'Nao foi possivel consultar o CEP agora.',
    }[cepStatus];

    const showDetails = Boolean(address) || ['found', 'not-found', 'error'].includes(cepStatus);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto p-0 sm:max-w-2xl">
                <DialogHeader>
                    <div className="border-b px-6 pt-6 pb-4">
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>Consulte o CEP para acelerar o preenchimento e revise os dados antes de salvar.</DialogDescription>
                    </div>
                </DialogHeader>

                <form id="address-form" className="grid gap-5 px-6" onSubmit={submit}>
                    <section className="rounded-lg border bg-muted/20 p-4">
                        <div className="grid gap-4 sm:grid-cols-12">
                            <FormField id="address-zipcode" label="CEP" required error={errors.zipcode} className="sm:col-span-5">
                                <ActionInput
                                    id="address-zipcode"
                                    value={form.zipcode}
                                    onChange={(event) => {
                                        setCepStatus('idle');
                                        setField('zipcode', formatCep(event.target.value));
                                    }}
                                    onBlur={handleCepBlur}
                                    onAction={() => void lookupCep()}
                                    actionLabel="Buscar"
                                    actionAriaLabel="Buscar Endereço pelo CEP"
                                    actionIcon={Search}
                                    actionLoading={cepStatus === 'loading'}
                                    actionLoadingIcon={<Loader2 className="size-4 animate-spin" />}
                                    autoComplete="postal-code"
                                    inputMode="numeric"
                                    placeholder="00000-000"
                                    required
                                />
                            </FormField>

                            <FormField id="address-name" label="Identificação" error={errors.name} className="sm:col-span-7">
                                <Input
                                    id="address-name"
                                    value={form.name}
                                    onChange={(event) => setField('name', event.target.value)}
                                    autoComplete="address-title"
                                    placeholder="Casa, escritorio, cobranca..."
                                />
                            </FormField>

                            <div className="sm:col-span-12">
                                <p
                                    className={
                                        cepStatus === 'found'
                                            ? 'text-sm text-emerald-600 dark:text-emerald-400'
                                            : 'text-sm text-muted-foreground'
                                    }
                                >
                                    {cepMessage}
                                </p>
                            </div>
                        </div>
                    </section>

                    {showDetails ? (
                        <>
                    <section className="grid gap-4">
                        <div>
                            <h3 className="text-sm font-semibold">Endereço principal</h3>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-12">
                            <FormField id="address-street" label="Rua" required error={errors.street} className="sm:col-span-8">
                                <Input
                                    id="address-street"
                                    value={form.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    autoComplete="address-line1"
                                    required
                                />
                            </FormField>

                            <FormField id="address-number" label="Numero" required error={errors.number} className="sm:col-span-4">
                                <Input
                                    id="address-number"
                                    value={form.number}
                                    onChange={(event) => setField('number', event.target.value)}
                                    autoComplete="address-line2"
                                    required
                                />
                            </FormField>

                            <FormField id="address-district" label="Bairro" required error={errors.district} className="sm:col-span-4">
                                <Input
                                    id="address-district"
                                    value={form.district}
                                    onChange={(event) => setField('district', event.target.value)}
                                    required
                                />
                            </FormField>

                            <FormField id="address-city" label="Cidade" required error={errors.city} className="sm:col-span-4">
                                <Input
                                    id="address-city"
                                    value={form.city}
                                    onChange={(event) => setField('city', event.target.value)}
                                    autoComplete="address-level2"
                                    required
                                />
                            </FormField>

                            <FormField id="address-state" label="UF" required error={errors.state} className="sm:col-span-4">
                                <Input
                                    id="address-state"
                                    value={form.state}
                                    onChange={(event) => setField('state', event.target.value.toUpperCase().slice(0, 2))}
                                    autoComplete="address-level1"
                                    maxLength={2}
                                    required
                                />
                            </FormField>
                        </div>
                    </section>

                    <section className="grid gap-4">
                        <div>
                            <h3 className="text-sm font-semibold">Complementos e fiscal</h3>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-12">
                            <FormField id="address-complement" label="Complemento" error={errors.complement} className="sm:col-span-6">
                                <Input
                                    id="address-complement"
                                    value={form.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    autoComplete="address-line3"
                                />
                            </FormField>

                            <FormField id="address-neighborhood" label="Regiao" error={errors.neighborhood} className="sm:col-span-6">
                                <Input
                                    id="address-neighborhood"
                                    value={form.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                />
                            </FormField>

                            <FormField id="address-city-code" label="Codigo IBGE" error={errors.city_code} className="sm:col-span-4">
                                <Input
                                    id="address-city-code"
                                    value={form.city_code}
                                    onChange={(event) => setField('city_code', event.target.value)}
                                    inputMode="numeric"
                                />
                            </FormField>

                            <FormField id="address-country" label="Pais" error={errors.country} className="sm:col-span-4">
                                <Input
                                    id="address-country"
                                    value={form.country}
                                    onChange={(event) => setField('country', event.target.value)}
                                    autoComplete="country-name"
                                />
                            </FormField>

                            <FormField
                                id="address-state-registration"
                                label="Inscrição Estadual"
                                error={errors.state_registration}
                                className="sm:col-span-4"
                            >
                                <Input
                                    id="address-state-registration"
                                    value={form.state_registration}
                                    onChange={(event) => setField('state_registration', event.target.value)}
                                />
                            </FormField>

                            <SwitchOption
                                className="sm:col-span-12"
                                checked={form.is_default}
                                onCheckedChange={(checked) => setField('is_default', checked)}
                                title="Endereço padrao"
                                description="Usar este Endereço como principal nas proximas operacoes do cliente."
                                icon={<Star className="size-4" />}
                            />
                        </div>
                    </section>
                        </>
                    ) : null}
                </form>

                <DialogFooter className="border-t px-6 py-4">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button type="submit" form="address-form" disabled={processing || !showDetails}>
                        <Save className="size-4" />
                        {processing ? 'Salvando...' : 'Salvar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
