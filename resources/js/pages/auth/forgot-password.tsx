// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import InputError, { errorProps } from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Esqueci a senha" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success-soft-foreground">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    {...errorProps('email', errors.email)}
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="nome@empresa.com.br"
                                />

                                <InputError
                                    field="email"
                                    message={errors.email}
                                />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing ? (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Mail
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Enviar link de redefinição
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Ou volte para</span>
                    <TextLink href={login()}>entrar</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Esqueci a senha',
    description: 'Informe seu e-mail para receber o link de redefinição',
};
