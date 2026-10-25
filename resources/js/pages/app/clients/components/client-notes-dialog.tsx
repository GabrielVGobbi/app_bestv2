import { router } from '@inertiajs/react';
import { useState } from 'react';

import { quickUpdate } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Client } from '@/types/app/client';

interface ClientNotesDialogProps {
    client: Client;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function errorMessage(errors: Record<string, string>): string {
    return (
        Object.values(errors)[0] ?? 'Não foi possível atualizar a observação.'
    );
}

export function ClientNotesDialog({
    client,
    open,
    onOpenChange,
}: ClientNotesDialogProps) {
    const hasNotes = Boolean(client.notes?.trim());
    const [notes, setNotes] = useState(client.notes ?? '');
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSave = () => {
        setIsSaving(true);
        setError(null);

        router.patch(
            quickUpdate.url(client.uuid),
            {
                field: 'notes',
                value: notes,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['clientData'],
                onSuccess: () => {
                    setIsSaving(false);
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setIsSaving(false);
                    setError(errorMessage(errors));
                },
                onFinish: () => {
                    setIsSaving(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {hasNotes
                            ? 'Editar observação'
                            : 'Adicionar observação'}
                    </DialogTitle>
                    <DialogDescription>
                        A observação fica visível na ficha do cliente e pode ser
                        atualizada a qualquer momento.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="client-notes">Observação</Label>
                    <Textarea
                        id="client-notes"
                        value={notes}
                        rows={6}
                        autoFocus
                        disabled={isSaving}
                        placeholder="Anotações internas sobre este cliente"
                        aria-invalid={error ? true : undefined}
                        onChange={(event) => setNotes(event.target.value)}
                    />
                    {error ? (
                        <p className="text-sm text-destructive">{error}</p>
                    ) : null}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={isSaving}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={handleSave}
                    >
                        {isSaving ? 'Salvando...' : 'Salvar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
