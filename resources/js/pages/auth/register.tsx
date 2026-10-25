import { Form, Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import InputError, { errorProps } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    return (
        <>
            <Head title="Cadastrar" />
            <Form
                {...store()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    {...errorProps('name', errors.name)}
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nome completo"
                                />
                                <InputError
                                    field="name"
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    {...errorProps('email', errors.email)}
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="nome@empresa.com.br"
                                />
                                <InputError
                                    field="email"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Senha</Label>
                                <PasswordInput
                                    id="password"
                                    {...errorProps('password', errors.password)}
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Senha"
                                />
                                <InputError
                                    field="password"
                                    message={errors.password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    {...errorProps(
                                        'password_confirmation',
                                        errors.password_confirmation,
                                    )}
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirmar senha"
                                />
                                <InputError
                                    field="password_confirmation"
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <UserPlus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Criar conta
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Já tem conta?{' '}
                            <TextLink href={login()} tabIndex={6}>
                                Entrar
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Criar uma conta',
    description: 'Preencha os dados abaixo para criar sua conta',
};
