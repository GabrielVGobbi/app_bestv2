import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import { update } from '@/actions/App/Http/Controllers/ForcePasswordChangeController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function ForcePasswordChange() {
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Trocar senha" />

            <div className="grid gap-6">
                <Alert>
                    <AlertTitle>Troca de senha obrigatoria</AlertTitle>
                    <AlertDescription>
                        Voce entrou com uma senha temporaria. Confirme essa
                        senha e defina uma nova para continuar.
                    </AlertDescription>
                </Alert>

                <Form
                    {...update.form.put()}
                    resetOnError={[
                        'current_password',
                        'password',
                        'password_confirmation',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();

                            return;
                        }

                        if (errors.password) {
                            passwordInput.current?.focus();
                        }
                    }}
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Senha temporaria
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder="Senha temporaria"
                                    autoFocus
                                />
                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Nova senha</Label>
                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder="Nova senha"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar nova senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder="Confirmar nova senha"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="force-password-change-button"
                            >
                                {processing && <Spinner />}
                                Trocar senha
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

ForcePasswordChange.layout = {
    title: 'Troque sua senha',
    description: 'Defina uma nova senha antes de continuar',
};
