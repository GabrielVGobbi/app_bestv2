import { Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Client } from '@/types/app/client';

interface ClientDeleteDialogProps {
    client: Client | null;
    onCancel: () => void;
    onConfirm: () => void;
}

export function ClientDeleteDialog({
    client,
    onCancel,
    onConfirm,
}: ClientDeleteDialogProps) {
    return (
        <Dialog
            open={client !== null}
            onOpenChange={(open) => !open && onCancel()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir cliente</DialogTitle>
                    <DialogDescription>
                        Tem certeza que deseja excluir{' '}
                        <strong>{client?.name}</strong>? Esta ação não pode ser
                        desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel}>
                        <X className="size-4" aria-hidden="true" />
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={onConfirm}>
                        <Trash2 className="size-4" aria-hidden="true" />
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
