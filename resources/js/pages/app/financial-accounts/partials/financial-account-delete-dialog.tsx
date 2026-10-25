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
import type { FinancialAccount } from '@/types/app/financial-account';

interface Props {
    financialAccount: FinancialAccount | null;
    onCancel: () => void;
    onConfirm: () => void;
}

export function FinancialAccountDeleteDialog({
    financialAccount,
    onCancel,
    onConfirm,
}: Props) {
    return (
        <Dialog
            open={financialAccount !== null}
            onOpenChange={(open) => !open && onCancel()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir conta financeira</DialogTitle>
                    <DialogDescription>
                        A exclusão só é permitida quando a conta ainda não tem
                        baixas, movimentos ou extratos vinculados.
                    </DialogDescription>
                </DialogHeader>

                {financialAccount ? (
                    <div className="rounded-lg border border-border bg-muted p-3 text-sm">
                        <p className="font-medium">{financialAccount.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {financialAccount.type_label}
                        </p>
                        <div className="mt-3 flex gap-2 text-muted-foreground">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>
                                Se a conta já teve uso operacional, inative-a
                                para preservar o histórico financeiro.
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
