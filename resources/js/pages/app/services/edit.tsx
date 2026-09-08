import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
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

export default function EditService({
    serviceData,
    permissions,
}: Props) {
    const service = serviceData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Serviço', href: index.url() },
            { title: service.name, href: show.url(service.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <>
            <Head title={`Editar ${service.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={show.url(service.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{service.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {service.slug}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <ServiceSubNav
                        serviceUuid={service.uuid}
                        currentPage="edit"
                        canUpdate={permissions.can_update}
                    />
                    {permissions.can_delete ? (
                        <>
                            <Separator orientation="vertical" className="mx-1 h-6" />
                            <DeleteButton
                                url={destroy.url(service.uuid)}
                                resourceName={service.name}
                                resourceType="Serviço"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(service.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatorios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <div className="flex flex-1 flex-col gap-6">
                                <Card>
                                    <CardContent>
                                        <ServiceFormFields
                                            service={service}
                                            errors={errors}
                                        />
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="sticky bottom-0 border-t bg-canvas/95 backdrop-blur supports-backdrop-filter:bg-canvas/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">*</span> sao
                                        obrigatorios.
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                            disabled={processing}
                                        >
                                            <Link href={show.url(service.uuid)}>
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
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
