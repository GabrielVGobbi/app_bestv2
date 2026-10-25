import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/UserController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';
import type { UserResource } from '@/types/user';

import { UserSubNav } from './components/user-sub-nav';

interface Props {
    userData: UserResource;
}

export default function Edit({ userData }: Props) {
    const user = userData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuários', href: index.url() },
            { title: user.name, href: show.url(user.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{user.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <UserSubNav userUuid={user.uuid} currentPage="edit" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url(user.uuid)}
                        resourceName={user.name}
                        resourceType="usuário"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <div className="page-section">
                    <Card variant="form">
                        <CardHeader>
                            <CardTitle>Informações do Usuário</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={update.url(user.uuid)}
                                method="put"
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <UserFormFields
                                            user={user}
                                            errors={errors}
                                        />

                                        <div className="flex gap-3">
                                            <Button disabled={processing}>
                                                {processing
                                                    ? 'Salvando...'
                                                    : 'Salvar alterações'}
                                            </Button>
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={show.url(user.uuid)}
                                                >
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
