import { Archive, Loader2, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

interface BulkActionBarProps {
    count: number;
    total: number;
    onSelectAll: () => void;
    onClearSelection: () => void;
    onBulkDelete: () => void;
    onBulkDownload: () => void;
    isDeleting?: boolean;
    isZipping?: boolean;
}

export function BulkActionBar({
    count,
    total,
    onSelectAll,
    onClearSelection,
    onBulkDelete,
    onBulkDownload,
    isDeleting = false,
    isZipping = false,
}: BulkActionBarProps) {
    const allSelected = count === total;
    const indeterminate = count > 0 && count < total;

    return (
        <div className="sticky top-0 z-10 flex items-center gap-2 rounded-lg border bg-muted/80 px-3 py-2 text-sm backdrop-blur-sm">
            <Checkbox
                checked={
                    allSelected ? true : indeterminate ? 'indeterminate' : false
                }
                onCheckedChange={(checked) => {
                    if (checked) {
                        onSelectAll();
                    } else {
                        onClearSelection();
                    }
                }}
                aria-label="Selecionar todos"
            />

            <span className="text-muted-foreground">
                <span className="font-medium text-foreground">{count}</span>{' '}
                selecionado{count !== 1 ? 's' : ''}
            </span>

            <div className="ml-auto flex items-center gap-1.5">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 gap-1.5 text-xs"
                    onClick={onBulkDownload}
                    disabled={isZipping || isDeleting}
                >
                    {isZipping ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Archive className="size-3.5" />
                    )}
                    Baixar ZIP
                </Button>

                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    className="h-7 gap-1.5 text-xs"
                    onClick={onBulkDelete}
                    disabled={isDeleting || isZipping}
                >
                    {isDeleting ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Trash2 className="size-3.5" />
                    )}
                    Excluir
                </Button>

                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-7 text-muted-foreground"
                    onClick={onClearSelection}
                    aria-label="Cancelar seleção"
                >
                    <X className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}
