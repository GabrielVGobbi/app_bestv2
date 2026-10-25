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
import type { FinancialCategory } from '@/types/app/financial-category';

interface Props {
    category: FinancialCategory | null;
    onCancel: () => void;
    onConfirm: () => void;
}

export function FinancialCategoryDeleteDialog({
    category,
    onCancel,
    onConfirm,
}: Props) {
    const blockers = category?.deletion_blockers ?? [];
    const canDelete = category ? blockers.length === 0 : false;

    return (
        <Dialog
            open={category !== null}
            onOpenChange={(open) => !open && onCancel()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir categoria financeira</DialogTitle>
                    <DialogDescription>
                        {canDelete
                            ? 'Esta categoria será removida da lista operacional.'
                            : 'Esta categoria possui vínculos e não pode ser excluída.'}
                    </DialogDescription>
                </DialogHeader>

                {category ? (
                    <div className="rounded-lg border border-border bg-muted p-3 text-sm">
                        <p className="font-medium">{category.full_path}</p>
                        {blockers.length > 0 ? (
                            <div className="mt-3 flex gap-2 text-destructive">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <p>
                                    Realoque filhos ou lançamentos vinculados
                                    antes de excluir.
                                </p>
                            </div>
                        ) : null}
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
                        disabled={!canDelete}
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
