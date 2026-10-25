import { useForm } from '@inertiajs/react';
import { Loader2, RotateCcw, X } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

import { reverse } from '@/actions/App/Modules/Finance/Http/Controllers/FinancialSettlementController';
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
import type { FinancialSettlement } from '@/types/app/financial-entry';

interface SettlementReversalDialogProps {
    settlement: FinancialSettlement | null;
    onOpenChange: (open: boolean) => void;
}

interface ReversalFormData {
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
        ) ?? 'Revise os dados do estorno.'
    );
}

export function SettlementReversalDialog({
    settlement,
    onOpenChange,
}: SettlementReversalDialogProps) {
    const form = useForm<ReversalFormData>({
        reason: '',
    });
    const { clearErrors, data, post, processing, reset, setData } = form;
    const errors = form.errors as FieldErrors;
    const open = settlement !== null;

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

        if (!settlement) {
            return;
        }

        form.transform(() => ({
            reason: data.reason.trim() || null,
        }));

        post(reverse.url(settlement.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Baixa estornada.');
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
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary-soft-foreground">
                            <RotateCcw className="size-5" />
                        </span>
                        <div>
                            <DialogTitle>Estornar baixa</DialogTitle>
                            <DialogDescription>
                                O lançamento original será preservado e um
                                movimento inverso será criado.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {settlement ? (
                    <div className="rounded-lg border border-border bg-muted p-3 text-sm">
                        <p className="font-medium tabular-nums">
                            Baixa de {money(settlement.net_amount)}
                        </p>
                    </div>
                ) : null}

                <form onSubmit={submit} className="space-y-4">
                    <FormField label="Motivo" error={errors.reason}>
                        <Textarea
                            value={data.reason}
                            rows={3}
                            maxLength={255}
                            onChange={(event) =>
                                setData('reason', event.target.value)
                            }
                            placeholder="Ex.: pagamento duplicado"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={processing}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing || !settlement}
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RotateCcw className="size-4" />
                            )}
                            Estornar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
