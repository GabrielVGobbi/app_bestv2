import { Loader2, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiRoutes } from '@/lib/api/api-routes';
import { csrfCookie } from '@/routes/sanctum';
import type { SaleFormOption } from '@/types/app/sale';

interface ClientQuickCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (client: SaleFormOption) => void;
}

interface ClientQuickCreateForm {
    name: string;
    phone: string;
    email: string;
}

interface ClientQuickCreateResponse {
    data?: SaleFormOption;
    message?: string;
    errors?: Record<string, string[]>;
}

type FieldErrors = Record<keyof ClientQuickCreateForm, string | undefined>;

const emptyForm: ClientQuickCreateForm = {
    name: '',
    phone: '',
    email: '',
};

function csrfToken(): string | undefined {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content;
}

function xsrfToken(): string | undefined {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : undefined;
}

function csrfHeaders(): Record<string, string> {
    const token = csrfToken();
    const xsrf = xsrfToken();

    return {
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    };
}

async function refreshCsrfCookie(): Promise<void> {
    const response = await fetch(csrfCookie.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('CSRF cookie request failed.');
    }
}

function firstValidationErrors(
    errors: ClientQuickCreateResponse['errors'],
): FieldErrors {
    return {
        name: errors?.name?.[0],
        phone: errors?.phone?.[0],
        email: errors?.email?.[0],
    };
}

export function ClientQuickCreateDialog({
    open,
    onOpenChange,
    onCreated,
}: ClientQuickCreateDialogProps) {
    const [form, setForm] = useState<ClientQuickCreateForm>(emptyForm);
    const [errors, setErrors] = useState<FieldErrors>({
        name: undefined,
        phone: undefined,
        email: undefined,
    });
    const [processing, setProcessing] = useState(false);

    function updateField<K extends keyof ClientQuickCreateForm>(
        field: K,
        value: ClientQuickCreateForm[K],
    ): void {
        setForm((current) => ({ ...current, [field]: value }));
        setErrors((current) => ({ ...current, [field]: undefined }));
    }

    function reset(): void {
        setForm(emptyForm);
        setErrors({
            name: undefined,
            phone: undefined,
            email: undefined,
        });
    }

    function close(): void {
        onOpenChange(false);
        reset();
    }

    function handleOpenChange(nextOpen: boolean): void {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            reset();
        }
    }

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        setProcessing(true);
        setErrors({
            name: undefined,
            phone: undefined,
            email: undefined,
        });

        try {
            let response = await createClient();

            if (response.status === 419) {
                await refreshCsrfCookie();
                response = await createClient();
            }

            const payload = (await response
                .json()
                .catch(() => ({}))) as ClientQuickCreateResponse;

            if (!response.ok) {
                if (response.status === 422) {
                    setErrors(firstValidationErrors(payload.errors));
                    toast.error('Revise os dados do cliente.');

                    return;
                }

                if (response.status === 419) {
                    toast.error(
                        'Sua sessão expirou. Recarregue a página e tente novamente.',
                    );

                    return;
                }

                toast.error(
                    payload.message ?? 'Não foi possível criar o cliente.',
                );

                return;
            }

            if (!payload.data) {
                toast.error('Cliente criado, mas a resposta veio incompleta.');

                return;
            }

            onCreated(payload.data);
            toast.success('Cliente criado e selecionado.');
            close();
        } finally {
            setProcessing(false);
        }
    }

    function createClient(): Promise<Response> {
        return fetch(apiRoutes.clients.store, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                name: form.name,
                phone: form.phone || null,
                email: form.email || null,
            }),
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Novo cliente</DialogTitle>
                    <DialogDescription>
                        Cadastre o contato sem sair da venda.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="quick_client_name">Nome</Label>
                        <Input
                            id="quick_client_name"
                            value={form.name}
                            onChange={(event) =>
                                updateField('name', event.target.value)
                            }
                            autoComplete="name"
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quick_client_phone">Telefone</Label>
                        <Input
                            id="quick_client_phone"
                            value={form.phone}
                            onChange={(event) =>
                                updateField('phone', event.target.value)
                            }
                            autoComplete="tel"
                            inputMode="tel"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quick_client_email">
                            E-mail opcional
                        </Label>
                        <Input
                            id="quick_client_email"
                            type="email"
                            value={form.email}
                            onChange={(event) =>
                                updateField('email', event.target.value)
                            }
                            autoComplete="email"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            Adicionar cliente
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
