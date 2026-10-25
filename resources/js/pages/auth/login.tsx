import { Form, Head, router } from '@inertiajs/react';
import { LogIn, UserRound } from 'lucide-react';
import { useState } from 'react';
import InputError, { errorProps } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type LocalLoginUser = {
    role: string;
    email: string;
    password: string;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    /** Contas de desenvolvimento enviadas pelo backend somente em ambiente local. */
    localLoginUsers?: LocalLoginUser[];
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
    localLoginUsers = [],
}: Props) {
    const [localLoginPending, setLocalLoginPending] = useState(false);

    function signInAs(user: LocalLoginUser): void {
        router.post(
            store().url,
            { email: user.email, password: user.password, remember: false },
            {
                onStart: () => setLocalLoginPending(true),
                onFinish: () => setLocalLoginPending(false),
            },
        );
    }

    return (
        <>
            <Head title="Entrar" />

            <Form
                {...store()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {localLoginUsers.length > 0 && (
                                <div className="grid gap-3 rounded-lg border border-dashed border-border bg-muted p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Acesso local
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            Somente neste ambiente
                                        </span>
                                    </div>

                                    <div className="grid gap-2">
                                        {localLoginUsers.map((user, index) => (
                                            <Button
                                                key={user.email}
                                                type="button"
                                                variant="outline"
                                                className="h-auto justify-start gap-3 px-3 py-2 text-left"
                                                tabIndex={index + 6}
                                                disabled={
                                                    processing ||
                                                    localLoginPending
                                                }
                                                onClick={() => signInAs(user)}
                                            >
                                                <UserRound
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <span className="grid min-w-0 gap-0.5">
                                                    <span className="text-sm leading-tight font-medium">
                                                        {user.role}
                                                    </span>
                                                    <span className="truncate text-xs leading-tight text-muted-foreground">
                                                        {user.email}
                                                    </span>
                                                </span>
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    {...errorProps('email', errors.email)}
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="nome@empresa.com.br"
                                />
                                <InputError
                                    field="email"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Senha</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Esqueceu a senha?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    {...errorProps('password', errors.password)}
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Senha"
                                />
                                <InputError
                                    field="password"
                                    message={errors.password}
                                />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Lembrar de mim</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing || localLoginPending}
                                data-test="login-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <LogIn
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Entrar
                            </Button>
                        </div>

                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                Não tem conta?{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    Cadastre-se
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success-soft-foreground">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Entrar na sua conta',
    description: 'Informe seu e-mail e senha para continuar',
};
