import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
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

    function useLocalLogin(user: LocalLoginUser): void {
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
            <Head title="Log in" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    {localLoginUsers.length > 0 && (
                        <div className="grid gap-3 rounded-lg border border-dashed bg-muted/40 p-3">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Acesso local
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Somente local
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
                                        disabled={form.processing}
                                        onClick={() => useLocalLogin(user)}
                                    >
                                        <LogIn className="size-4 text-muted-foreground" />
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
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            placeholder="email@example.com"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="current-password">Password</Label>
                            {canResetPassword && (
                                <TextLink
                                    href={request()}
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <PasswordInput
                            id="current-password"
                            name="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            placeholder="Password"
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
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        tabIndex={4}
                        disabled={form.processing}
                        data-test="login-button"
                    >
                        {form.processing && <Spinner />}
                        Log in
                    </Button>
                </div>

                {canRegister && (
                    <div className="text-center text-sm text-muted-foreground">
                        Don't have an account?{' '}
                        <TextLink href={register()} tabIndex={5}>
                            Sign up
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
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
