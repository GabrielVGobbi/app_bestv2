import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import CompanyFormFields from '@/pages/app/companies/partials/company-form-fields';
import type { CompanyResource } from '@/types/app/company';

import { CompanySubNav } from './components/company-sub-nav';

interface Props {
    companyData: CompanyResource;
    permissions: {
        can_manage: boolean;
    };
}

export default function EditCompany({ companyData, permissions }: Props) {
    const company = companyData.data;
    const companyName = String(company.name ?? company.uuid);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index.url() },
            { title: companyName, href: show.url(company.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title="Editar empresa" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(company.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{companyName}</h1>
                        <p className="text-sm text-muted-foreground">
                            {company.uuid}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <CompanySubNav
                        companyUuid={company.uuid}
                        currentPage="edit"
                        canManage={permissions.can_manage}
                    />
                    {permissions.can_manage ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <DeleteButton
                                url={destroy.url(company.uuid)}
                                resourceName={companyName}
                                resourceType="empresa"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(company.uuid)}
                    method="post"
                    options={{ preserveScroll: true }}
                    onError={() =>
                        toast.error('Verifique os campos obrigatorios.')
                    }
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <input type="hidden" name="_method" value="put" />

                            <div className="flex flex-1 flex-col gap-6">
                                <Card variant="form">
                                    <CardHeader>
                                        <CardTitle>Dados da empresa</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <CompanyFormFields
                                            company={company}
                                            errors={errors}
                                        />
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="sticky bottom-0 border-t bg-background">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>{' '}
                                        sao obrigatorios.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                            disabled={processing}
                                        >
                                            <Link href={show.url(company.uuid)}>
                                                Cancelar
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <Save />
                                            {processing
                                                ? 'Salvando...'
                                                : 'Salvar'}
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
