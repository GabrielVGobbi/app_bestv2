import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialAccountController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FinancialAccountTypeOption } from '@/types/app/financial-account';

import FinancialAccountFormFields from './partials/financial-account-form-fields';

interface Props {
    typeOptions: FinancialAccountTypeOption[];
}

export default function CreateFinancialAccount({ typeOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Contas financeiras', href: index.url() },
            { title: 'Nova conta', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Nova conta financeira" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Nova conta financeira
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cadastre uma conta para baixas e conciliação
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-md">
                <Form
                    action={store.url()}
                    method="post"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatórios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Dados da conta</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FinancialAccountFormFields
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">*</span>{' '}
                                        são obrigatórios.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                            disabled={processing}
                                        >
                                            <Link href={index.url()}>Cancelar</Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <Save />
                                            {processing ? 'Salvando...' : 'Salvar'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
