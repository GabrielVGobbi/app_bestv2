import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialCategoryController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    FinancialCategoryResource,
    FinancialCategoryTypeOption,
} from '@/types/app/financial-category';

import FinancialCategoryFormFields from './partials/financial-category-form-fields';

interface Props {
    categoryData: FinancialCategoryResource;
    typeOptions: FinancialCategoryTypeOption[];
}

export default function EditFinancialCategory({
    categoryData,
    typeOptions,
}: Props) {
    const category = categoryData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Categorias financeiras', href: index.url() },
            { title: category.name, href: show.url(category.uuid) },
            { title: 'Editar', href: edit.url(category.uuid) },
        ],
    });

    return (
        <>
            <Head title={`Editar ${category.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(category.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">
                            Editar categoria financeira
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {category.full_path}
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(category.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() =>
                        toast.error('Verifique os campos obrigatórios.')
                    }
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <Card variant="form">
                                <CardHeader>
                                    <CardTitle>Dados da categoria</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FinancialCategoryFormFields
                                        category={category}
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-background">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={show.url(category.uuid)}>
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
