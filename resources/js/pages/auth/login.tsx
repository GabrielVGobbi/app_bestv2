import { Head, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    localLoginUsers?: LocalLoginUser[];
};

type LocalLoginUser = {
    role: string;
    email: string;
    password: string;
};

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
    localLoginUsers = [],
}: Props) {
    const form = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        postLogin(form.data);
    }

    function postLogin(data: LoginForm): void {
        form.transform(() => data);
        form.post(store.url(), {
            onFinish: () => {
                form.transform((currentData) => currentData);
                form.reset('password');
            },
        });
    }

    function loginAsLocalUser(user: LocalLoginUser): void {
        const loginData = {
            email: user.email,
            password: user.password,
            remember: false,
        };

        form.setData(loginData);
        postLogin(loginData);
    }

    return (
        <>
            <Head title="Entrar" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    {localLoginUsers.length > 0 && (
                        <details className="group rounded-lg border border-border bg-muted/30">
                            <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between px-3 text-sm text-muted-foreground">
                                Acessos de desenvolvimento
                                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                            </summary>

                            <div className="grid max-h-52 gap-1 overflow-y-auto border-t border-border p-2">
                                {localLoginUsers.map((user, index) => (
                                    <Button
                                        key={user.email}
                                        type="button"
                                        variant="outline"
                                        className="h-auto justify-start px-3 py-2 text-left"
                                        tabIndex={index + 6}
                                        disabled={form.processing}
                                        onClick={() => loginAsLocalUser(user)}
                                    >
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
                        </details>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="email">E-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            placeholder="voce@empresa.com.br"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="current-password">Senha</Label>
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
                            id="current-password"
                            name="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder="Sua senha"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            tabIndex={3}
                            checked={form.data.remember}
                            onCheckedChange={(checked) =>
                                form.setData('remember', checked === true)
                            }
                        />
                        <Label htmlFor="remember">Manter conectado</Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        tabIndex={4}
                        disabled={form.processing}
                        data-test="login-button"
                    >
                        {form.processing && <Spinner />}
                        Entrar
                    </Button>
                </div>

                {canRegister && (
                    <div className="text-center text-sm text-muted-foreground">
                        Não possui uma conta?{' '}
                        <TextLink href={register()} tabIndex={5}>
                            Criar conta
                        </TextLink>
                    </div>
                )}
            </form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Entrar na BestPlay',
    description: 'Use seu e-mail e senha para continuar.',
};
