import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    destroy,
    index,
    show,
    update,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ServiceFormFields from '@/pages/app/services/partials/service-form-fields';
import type { ServiceResource } from '@/types/app/service';

import { ServiceSubNav } from './components/service-sub-nav';

interface Props {
    serviceData: ServiceResource;
    permissions: {
        can_update: boolean;
        can_delete: boolean;
    };
}

export default function EditService({ serviceData, permissions }: Props) {
    const service = serviceData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Serviços', href: index.url() },
            { title: service.name, href: show.url(service.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title={`Editar ${service.name}`} />

            <Header title={service.name} backHref={show.url(service.uuid)}>
                <div className="flex flex-wrap items-center gap-2">
                    <ServiceSubNav
                        serviceUuid={service.uuid}
                        currentPage="edit"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <DeleteButton
                                url={destroy.url(service.uuid)}
                                resourceName={service.name}
                                resourceType="serviço"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <Form
                action={update.url(service.uuid)}
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
                                    <ServiceFormFields
                                        service={service}
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
                                        <Link href={show.url(service.uuid)}>
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
