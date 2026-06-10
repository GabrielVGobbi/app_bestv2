import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';

export default function Create() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Users', href: index.url() },
            { title: 'New User', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="New User" />

            <div className="container mx-auto max-w-3xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="New User"
                            description="Create a new user account"
                        />
                        <Button variant="outline" asChild>
                            <Link href={index.url()}>Cancel</Link>
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>User Information</CardTitle>
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
                                        <Alert>
                                            <AlertTitle>
                                                First access with temporary
                                                password
                                            </AlertTitle>
                                            <AlertDescription>
                                                The account will be created with
                                                a secure temporary password.
                                                Copy it after creation and send
                                                it to the user. They will be
                                                required to change it after
                                                signing in.
                                            </AlertDescription>
                                        </Alert>

                                        <UserFormFields errors={errors} />

                                        <div className="flex gap-3">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Creating...'
                                                    : 'Create user'}
                                            </Button>
                                            <Button variant="outline" asChild>
                                                <Link href={index.url()}>
                                                    Cancel
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
