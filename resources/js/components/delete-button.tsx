import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { httpDelete } from '@/lib/http';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface DeleteButtonProps {
    /** URL da rota de exclusão (ex: destroy.url(uuid)) */
    url: string;
    /** Nome do recurso exibido no dialog (ex: "Gabriel Gobbi", "Cliente X") */
    resourceName: string;
    /** Tipo do recurso para textos do dialog (ex: "usuário", "cliente") */
    resourceType?: string;
    /** Rota para redirecionar após exclusão bem-sucedida */
    redirectTo?: string;
    /** Callback alternativo ao redirectTo */
    onSuccess?: () => void;
    size?: 'sm' | 'default';
}

export default function DeleteButton({
    url,
    resourceName,
    resourceType = 'registro',
    redirectTo,
    onSuccess,
    size = 'sm',
}: DeleteButtonProps) {
    const [open, setOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const handleDelete = async () => {
        setDeleting(true);
        try {
            await httpDelete(url);
            toast.success(`${capitalize(resourceType)} removido com sucesso!`);
            setOpen(false);
            if (onSuccess) {
                onSuccess();
            } else if (redirectTo) {
                router.visit(redirectTo);
            }
        } catch {
            toast.error(`Erro ao remover ${resourceType}.`);
            setDeleting(false);
        }
    };

    return (
        <>
            <Button variant="destructive" size={size} onClick={() => setOpen(true)}>
                <Trash2 className="mr-1.5 size-4" />
                Excluir
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Excluir {resourceType}</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir <strong>{resourceName}</strong>? Esta ação
                            não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)} disabled={deleting}>
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={deleting}>
                            {deleting ? 'Excluindo...' : 'Excluir'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function capitalize(str: string) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
