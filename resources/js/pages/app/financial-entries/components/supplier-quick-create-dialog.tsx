import { useHttp } from '@inertiajs/react';
import { Loader2, Plus, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
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
import { InputDocument } from '@/components/ui/input-document';
import { InputPhone } from '@/components/ui/input-phone';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { apiRoutes } from '@/lib/api/api-routes';
import { csrfCookie } from '@/routes/sanctum';
import type { Supplier, SupplierResource } from '@/types/app/supplier';

interface SupplierQuickCreateDialogProps {
    companyId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (supplier: Supplier) => void;
}

interface SupplierQuickCreateForm {
    company_id: number | null;
    name: string;
    company_name: string | null;
    document: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    status: 'active';
}

const emptyForm: SupplierQuickCreateForm = {
    company_id: null,
    name: '',
    company_name: null,
    document: null,
    email: null,
    phone: null,
    notes: null,
    status: 'active',
};

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

function nullableText(value: string | null): string | null {
    const trimmedValue = (value ?? '').trim();

    return trimmedValue === '' ? null : trimmedValue;
}

function errorMessage(error: unknown): string | undefined {
    if (typeof error === 'string') {
        return error;
    }

    if (Array.isArray(error)) {
        return error.find((item): item is string => typeof item === 'string');
    }

    return undefined;
}

export function SupplierQuickCreateDialog({
    companyId,
    open,
    onOpenChange,
    onCreated,
}: SupplierQuickCreateDialogProps) {
    const form = useHttp<SupplierQuickCreateForm, SupplierResource>(emptyForm);
    const { data, errors, processing, post, resetAndClearErrors, setData } = form;

    function updateField<K extends keyof SupplierQuickCreateForm>(
        field: K,
        value: SupplierQuickCreateForm[K],
    ): void {
        setData((currentData) => ({
            ...currentData,
            [field]: value,
        }));
        form.clearErrors(field);
    }

    function close(): void {
        onOpenChange(false);
        resetAndClearErrors();
    }

    function handleOpenChange(nextOpen: boolean): void {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            resetAndClearErrors();
        }
    }

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        if (!companyId) {
            toast.error('Selecione uma empresa antes de criar o fornecedor.');

            return;
        }

        form.transform((currentData) => ({
            ...currentData,
            company_id: companyId,
            name: currentData.name.trim(),
            company_name: nullableText(currentData.company_name),
            document: nullableText(currentData.document),
            email: nullableText(currentData.email),
            phone: nullableText(currentData.phone),
            notes: nullableText(currentData.notes),
            status: 'active',
        }));

        try {
            await refreshCsrfCookie();
        } catch {
            toast.error(
                'Não foi possível preparar a sessão. Recarregue a página e tente novamente.',
            );

            return;
        }

        await post(apiRoutes.suppliers.store, {
            onSuccess: (response) => {
                if (!response.data) {
                    toast.error(
                        'Fornecedor criado, mas a resposta veio incompleta.',
                    );

                    return;
                }

                onCreated(response.data);
                toast.success('Fornecedor criado e selecionado.');
                close();
            },
            onError: () => {
                toast.error('Revise os dados do fornecedor.');
            },
            onHttpException: (response) => {
                if (response.status === 419) {
                    toast.error(
                        'Sua sessão expirou. Recarregue a página e tente novamente.',
                    );

                    return;
                }

                const body = response.data as { message?: string } | undefined;

                toast.error(body?.message ?? 'Não foi possível criar o fornecedor.');
            },
            onNetworkError: () => {
                toast.error('Falha de conexão ao criar o fornecedor.');
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                            <UserPlus className="size-5" />
                        </span>
                        <div>
                            <DialogTitle>Novo fornecedor</DialogTitle>
                            <DialogDescription>
                                Cadastre e selecione para este lançamento.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="quick_supplier_name">
                                Nome <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="quick_supplier_name"
                                value={data.name}
                                onChange={(event) =>
                                    updateField('name', event.target.value)
                                }
                                autoComplete="organization"
                                autoFocus
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError message={errorMessage(errors.name)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="quick_supplier_company_name">
                                Razão social
                            </Label>
                            <Input
                                id="quick_supplier_company_name"
                                value={data.company_name ?? ''}
                                onChange={(event) =>
                                    updateField(
                                        'company_name',
                                        event.target.value,
                                    )
                                }
                                autoComplete="organization"
                                aria-invalid={Boolean(errors.company_name)}
                            />
                            <InputError
                                message={errorMessage(errors.company_name)}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="quick_supplier_document">
                                CPF/CNPJ
                            </Label>
                            <InputDocument
                                id="quick_supplier_document"
                                value={data.document ?? ''}
                                onChange={(value) =>
                                    updateField('document', value)
                                }
                                aria-invalid={Boolean(errors.document)}
                            />
                            <InputError
                                message={errorMessage(errors.document)}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="quick_supplier_phone">
                                Telefone
                            </Label>
                            <InputPhone
                                id="quick_supplier_phone"
                                value={data.phone ?? ''}
                                onChange={(value) => updateField('phone', value)}
                                aria-invalid={Boolean(errors.phone)}
                            />
                            <InputError message={errorMessage(errors.phone)} />
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="quick_supplier_email">E-mail</Label>
                            <Input
                                id="quick_supplier_email"
                                type="email"
                                value={data.email ?? ''}
                                onChange={(event) =>
                                    updateField('email', event.target.value)
                                }
                                autoComplete="email"
                                aria-invalid={Boolean(errors.email)}
                            />
                            <InputError message={errorMessage(errors.email)} />
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="quick_supplier_notes">
                                Observações
                            </Label>
                            <Textarea
                                id="quick_supplier_notes"
                                value={data.notes ?? ''}
                                rows={3}
                                onChange={(event) =>
                                    updateField('notes', event.target.value)
                                }
                                aria-invalid={Boolean(errors.notes)}
                            />
                            <InputError message={errorMessage(errors.notes)} />
                        </div>
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
                        <Button type="submit" disabled={processing || !companyId}>
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            Criar fornecedor
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
