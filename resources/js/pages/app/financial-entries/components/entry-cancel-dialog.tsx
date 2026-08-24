import { useForm } from '@inertiajs/react';
import { Loader2, XCircle } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import { cancel } from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
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
import { Textarea } from '@/components/ui/textarea';
import type { FinancialEntry } from '@/types/app/financial-entry';

interface EntryCancelDialogProps {
    entry: FinancialEntry;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface CancelFormData {
    reason: string;
}

type FieldErrors = Record<string, string | undefined>;

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function firstError(errors: FieldErrors): string {
    return (
        Object.values(errors).find((message): message is string =>
            Boolean(message),
        ) ?? 'Revise os dados do cancelamento.'
    );
}

export function EntryCancelDialog({
    entry,
    open,
    onOpenChange,
}: EntryCancelDialogProps) {
    const form = useForm<CancelFormData>({
        reason: '',
    });
    const { clearErrors, data, patch, processing, reset, setData } = form;
    const errors = form.errors as FieldErrors;

    useEffect(() => {
        if (!open) {
            return;
        }

        setData('reason', '');
        clearErrors();
    }, [clearErrors, open, setData]);

    function close(): void {
        onOpenChange(false);
        reset();
        clearErrors();
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        patch(cancel.url(entry.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Lançamento financeiro cancelado.');
                close();
            },
            onError: (requestErrors) =>
                toast.error(firstError(requestErrors as FieldErrors)),
        });
    }

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => !nextOpen && close()}>
            <DialogContent>
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-700">
                            <XCircle className="size-5" />
                        </span>
                        <div>
                            <DialogTitle>Cancelar lançamento</DialogTitle>
                            <DialogDescription>
                                As parcelas em aberto serão canceladas e o
                                saldo deixará de contar nos relatórios.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="rounded-md border bg-muted/30 p-3 text-sm">
                    <p className="font-medium">{entry.description}</p>
                    <p className="text-xs text-muted-foreground">
                        Saldo em aberto de{' '}
                        {money(entry.balance_amount, entry.currency)}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <FormField label="Motivo" error={errors.reason} required>
                        <Textarea
                            value={data.reason}
                            rows={3}
                            maxLength={1000}
                            onChange={(event) =>
                                setData('reason', event.target.value)
                            }
                            placeholder="Ex.: lançamento duplicado"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={processing}
                        >
                            Voltar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <XCircle className="size-4" />
                            )}
                            Cancelar lançamento
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
