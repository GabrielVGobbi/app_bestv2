import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { KeyRound, X, UserPlus } from 'lucide-react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/UserController';
import Header from '@/components/dashboard/header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';
import { dashboard } from '@/routes/app';

interface Props {
    can: { view: boolean };
}

export default function Create({ can }: Props) {
    setLayoutProps({
        breadcrumbs: [
            ...(can.view ? [{ title: 'Usuários', href: index.url() }] : []),
            { title: 'Novo usuário', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo usuário" />

            <Header
                title="Novo usuário"
                subtitle="Crie uma nova conta de usuário"
                backHref={can.view ? index.url() : dashboard.url()}
            >
                <Button variant="outline" asChild>
                    <Link href={can.view ? index.url() : dashboard.url()}>
                        <X className="size-4" aria-hidden="true" />
                        Cancelar
                    </Link>
                </Button>
            </Header>

            <div className="container mx-auto max-w-3xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações do usuário</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={store.url()}
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-6"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Alert variant="info">
                                            <KeyRound />
                                            <AlertTitle>
                                                Primeiro acesso com senha
                                                temporária
                                            </AlertTitle>
                                            <AlertDescription>
                                                A conta será criada com uma
                                                senha temporária segura. Copie
                                                após a criação e envie ao
                                                usuário; ele precisará trocá-la
                                                no primeiro acesso.
                                            </AlertDescription>
                                        </Alert>

                                        <UserFormFields errors={errors} />

                                        <div className="flex gap-3">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <UserPlus
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {processing
                                                    ? 'Criando...'
                                                    : 'Criar usuário'}
                                            </Button>
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={
                                                        can.view
                                                            ? index.url()
                                                            : dashboard.url()
                                                    }
                                                >
                                                    <X
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Cancelar
                                                </Link>
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
