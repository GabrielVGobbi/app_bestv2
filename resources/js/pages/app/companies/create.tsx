import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import CompanyFormFields from '@/pages/app/companies/partials/company-form-fields';

export default function CreateCompany() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index.url() },
            { title: 'Nova empresa', href: create.url() },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Nova empresa" />

            <Header
                title="Nova empresa"
                subtitle="Cadastrar empresa interna prestadora"
                backHref={index.url()}
            />

            <Form
                action={store.url()}
                method="post"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
                            <Card>
                                <CardContent>
                                    <CompanyFormFields errors={errors} />
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
                                        <Link href={index.url()}>
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
