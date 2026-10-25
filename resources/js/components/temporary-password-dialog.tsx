import { router } from '@inertiajs/react';
import { Check, Copy, Mail, X } from 'lucide-react';
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
            toast.success('Senha temporária copiada.');
        }
    }

    function mockEmailSend(): void {
        toast.info('Mock: envio por e-mail simulado.');
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
                    <DialogTitle>Senha temporária criada</DialogTitle>
                    <DialogDescription>
                        Copie esta senha agora. Ela não será exibida novamente.
                    </DialogDescription>
                </DialogHeader>

                {temporaryUserPassword && (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Usuário</Label>
                            <div className="rounded-md border border-border bg-muted px-3 py-2 text-sm">
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
                                Senha temporária
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
                                    aria-label="Copiar senha temporária"
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
                        Enviar por e-mail
                    </Button>
                    <Button
                        type="button"
                        onClick={() => setTemporaryUserPassword(null)}
                    >
                        <X className="size-4" aria-hidden="true" />
                        Fechar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
