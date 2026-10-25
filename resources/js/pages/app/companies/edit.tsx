import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
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
import { Card, CardContent } from '@/components/ui/card';
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
        <div className="flex min-h-full flex-col">
            <Head title="Editar empresa" />

            <Header
                title={companyName}
                subtitle={company.nickname}
                backHref={show.url(company.uuid)}
            >
                <div className="flex flex-wrap items-center gap-2">
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

            <Form
                action={update.url(company.uuid)}
                method="post"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <input type="hidden" name="_method" value="put" />

                        <div className="page-wrapper-md flex-1 pb-8">
                            <Card>
                                <CardContent>
                                    <CompanyFormFields
                                        company={company}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t border-border bg-card/95">
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
                                        <Link href={show.url(company.uuid)}>
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
