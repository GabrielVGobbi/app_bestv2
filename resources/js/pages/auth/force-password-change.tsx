import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useRef } from 'react';
import { update } from '@/actions/App/Http/Controllers/ForcePasswordChangeController';
import InputError, { errorProps } from '@/components/input-error';
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
            <Head title="Defina uma nova senha" />

            <div className="grid gap-6">
                <Alert>
                    <AlertTitle>Troca de senha obrigatória</AlertTitle>
                    <AlertDescription>
                        Você entrou com uma senha temporária. Confirme essa
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
                                    Senha temporária
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    {...errorProps(
                                        'current_password',
                                        errors.current_password,
                                    )}
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder="Senha temporária"
                                    autoFocus
                                />
                                <InputError
                                    field="current_password"
                                    message={errors.current_password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Nova senha</Label>
                                <PasswordInput
                                    id="password"
                                    {...errorProps('password', errors.password)}
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder="Nova senha"
                                />
                                <InputError
                                    field="password"
                                    message={errors.password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar nova senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    {...errorProps(
                                        'password_confirmation',
                                        errors.password_confirmation,
                                    )}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder="Confirmar nova senha"
                                />
                                <InputError
                                    field="password_confirmation"
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="force-password-change-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <KeyRound
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Salvar nova senha
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

ForcePasswordChange.layout = {
    title: 'Defina uma nova senha',
    description: 'Sua senha temporária precisa ser trocada antes de continuar',
};
