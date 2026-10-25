import { router } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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

    const handleDelete = () => {
        setDeleting(true);

        router.delete(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `${capitalize(resourceType)} removido com sucesso!`,
                );
                setOpen(false);

                if (onSuccess) {
                    onSuccess();
                } else if (redirectTo) {
                    router.visit(redirectTo);
                }
            },
            onError: () => {
                toast.error(`Erro ao remover ${resourceType}.`);
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    return (
        <>
            <Button
                variant="destructive"
                size={size}
                onClick={() => setOpen(true)}
            >
                <Trash2 className="mr-1.5 size-4" />
                Excluir
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Excluir {resourceType}</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir{' '}
                            <strong>{resourceName}</strong>? Esta ação não pode
                            ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={deleting}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
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
