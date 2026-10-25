import { Form } from '@inertiajs/react';
import { Trash2, TriangleAlert, X } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError, { errorProps } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Excluir conta"
                description="Exclui sua conta e todos os seus dados"
            />
            <div className="space-y-4">
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>Atenção</AlertTitle>
                    <AlertDescription>
                        Esta ação não pode ser desfeita.
                    </AlertDescription>
                </Alert>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Excluir conta
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Tem certeza de que quer excluir sua conta?
                        </DialogTitle>
                        <DialogDescription>
                            Depois da exclusão, todos os dados serão apagados
                            definitivamente. Digite sua senha para confirmar.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Senha
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            {...errorProps(
                                                'password',
                                                errors.password,
                                            )}
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Senha"
                                            autoComplete="current-password"
                                        />

                                        <InputError
                                            field="password"
                                            message={errors.password}
                                        />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                <X
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Cancelar
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Excluir conta
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
