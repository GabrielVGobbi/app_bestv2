import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialAccountController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    FinancialAccountResource,
    FinancialAccountTypeOption,
} from '@/types/app/financial-account';

import FinancialAccountFormFields from './partials/financial-account-form-fields';

interface Props {
    financialAccountData: FinancialAccountResource;
    typeOptions: FinancialAccountTypeOption[];
}

export default function EditFinancialAccount({
    financialAccountData,
    typeOptions,
}: Props) {
    const financialAccount = financialAccountData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Contas financeiras', href: index.url() },
            {
                title: financialAccount.name,
                href: show.url(financialAccount.uuid),
            },
            { title: 'Editar', href: edit.url(financialAccount.uuid) },
        ],
    });

    return (
        <>
            <Head title={`Editar ${financialAccount.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={show.url(financialAccount.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Editar conta financeira
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {financialAccount.type_label}
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-md">
                <Form
                    action={update.url(financialAccount.uuid)}
                    method="put"
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
                                        financialAccount={financialAccount}
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={show.url(financialAccount.uuid)}>
                                            Cancelar
                                        </Link>
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
                    )}
                </Form>
            </div>
        </>
    );
}
