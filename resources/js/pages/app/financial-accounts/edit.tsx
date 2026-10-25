import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialAccountController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
        <div className="flex min-h-full flex-col">
            <Head title={`Editar ${financialAccount.name}`} />

            <Header
                title="Editar conta financeira"
                subtitle={financialAccount.type_label}
                backHref={show.url(financialAccount.uuid)}
            />

            <Form
                action={update.url(financialAccount.uuid)}
                method="put"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
                            <Card>
                                <CardContent>
                                    <FinancialAccountFormFields
                                        financialAccount={financialAccount}
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <div className="sticky bottom-0 z-10 border-t bg-card/95">
                            <div className="page-wrapper-md mb-0 flex items-center justify-between gap-3 py-3">
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    Campos marcados com{' '}
                                    <span className="text-destructive">*</span>{' '}
                                    são obrigatórios
                                </p>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link
                                            href={show.url(
                                                financialAccount.uuid,
                                            )}
                                        >
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancelar
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save />
                                        {processing ? 'Salvando...' : 'Salvar'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
