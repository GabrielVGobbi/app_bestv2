import { router } from '@inertiajs/react';
import { Check, Copy, Mail } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

type TemporaryUserPassword = {
    name: string;
    email: string;
    password: string;
};

export default function TemporaryPasswordDialog() {
    const [temporaryUserPassword, setTemporaryUserPassword] =
        useState<TemporaryUserPassword | null>(null);
    const [copiedText, copy] = useClipboard();

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.temporaryUserPassword as
                | TemporaryUserPassword
                | undefined;

            if (data) {
                setTemporaryUserPassword(data);
            }
        });
    }, []);

    const temporaryPassword = temporaryUserPassword?.password ?? '';
    const copied = copiedText === temporaryPassword;

    async function copyTemporaryPassword(): Promise<void> {
        if (await copy(temporaryPassword)) {
            toast.success('Senha temporaria copiada.');
        }
    }

    function mockEmailSend(): void {
        toast.info('Mock: envio por email simulado.');
    }

    return (
        <Dialog
            open={temporaryUserPassword !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setTemporaryUserPassword(null);
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Senha temporaria criada</DialogTitle>
                    <DialogDescription>
                        Copie esta senha agora. Ela nao sera exibida novamente.
                    </DialogDescription>
                </DialogHeader>

                {temporaryUserPassword && (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Usuario</Label>
                            <div className="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                                <div className="font-medium">
                                    {temporaryUserPassword.name}
                                </div>
                                <div className="text-muted-foreground">
                                    {temporaryUserPassword.email}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="temporary-password">
                                Senha temporaria
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="temporary-password"
                                    value={temporaryPassword}
                                    readOnly
                                    className="font-mono"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={copyTemporaryPassword}
                                    aria-label="Copiar senha temporaria"
                                >
                                    {copied ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={mockEmailSend}
                    >
                        <Mail className="size-4" />
                        Enviar por email
                    </Button>
                    <Button
                        type="button"
                        onClick={() => setTemporaryUserPassword(null)}
                    >
                        Fechar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
