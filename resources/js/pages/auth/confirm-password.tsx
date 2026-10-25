import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import InputError, { errorProps } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar senha" />

            <Form {...store()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Senha</Label>
                            <PasswordInput
                                id="password"
                                {...errorProps('password', errors.password)}
                                name="password"
                                placeholder="Senha"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError
                                field="password"
                                message={errors.password}
                            />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <KeyRound
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Confirmar senha
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirme sua senha',
    description:
        'Esta é uma área protegida. Confirme sua senha para continuar.',
};
