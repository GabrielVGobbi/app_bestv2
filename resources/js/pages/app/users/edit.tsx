import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';

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
import { dashboard } from '@/routes/app';
import type { UserResource } from '@/types/user';

import { UserSubNav } from './components/user-sub-nav';

interface Props {
    userData: UserResource;
    can: { view: boolean; delete: boolean; authority: boolean };
}

export default function Edit({ userData, can }: Props) {
    const user = userData.data;

    setLayoutProps({
        breadcrumbs: [
            ...(can.view
                ? [
                      { title: 'Usuários', href: index.url() },
                      { title: user.name, href: show.url(user.uuid) },
                  ]
                : []),
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <Header
                title={user.name}
                subtitle={user.email}
                backHref={can.view ? index.url() : dashboard.url()}
            >
                <div className="flex items-center gap-2">
                    <UserSubNav
                        userUuid={user.uuid}
                        currentPage="edit"
                        can={{
                            view: can.view,
                            update: true,
                            authority: can.authority,
                        }}
                    />
                    {can.delete && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <DeleteButton
                                url={destroy.url(user.uuid)}
                                resourceName={user.name}
                                resourceType="usuário"
                                redirectTo={
                                    can.view ? index.url() : dashboard.url()
                                }
                            />
                        </>
                    )}
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações do usuário</CardTitle>
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
                                                <Save
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {processing
                                                    ? 'Salvando...'
                                                    : 'Salvar alterações'}
                                            </Button>
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={
                                                        can.view
                                                            ? show.url(
                                                                  user.uuid,
                                                              )
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
