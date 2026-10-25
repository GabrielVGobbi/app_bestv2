import { AlertTriangle, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PaymentMethod } from '@/types/app/payment-method';

interface Props {
    paymentMethod: PaymentMethod | null;
    onCancel: () => void;
    onConfirm: () => void;
}

export function PaymentMethodDeleteDialog({
    paymentMethod,
    onCancel,
    onConfirm,
}: Props) {
    return (
        <Dialog
            open={paymentMethod !== null}
            onOpenChange={(open) => !open && onCancel()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir método de pagamento</DialogTitle>
                    <DialogDescription>
                        Este cadastro será removido da lista de métodos
                        disponíveis.
                    </DialogDescription>
                </DialogHeader>

                {paymentMethod ? (
                    <div className="rounded-lg border border-border bg-muted p-3 text-sm">
                        <p className="font-medium">{paymentMethod.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {paymentMethod.type_label} - {paymentMethod.code}
                        </p>
                        <div className="mt-3 flex gap-2 text-muted-foreground">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>
                                Quando este método tiver baixas vinculadas no
                                v2, a exclusão deverá ser bloqueada e
                                substituída por inativação.
                            </p>
                        </div>
                    </div>
                ) : null}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        <X className="size-4" aria-hidden="true" />
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
