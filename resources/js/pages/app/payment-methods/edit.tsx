import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Modules/Finance/Http/Controllers/PaymentMethodController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    PaymentMethodResource,
    PaymentMethodTypeOption,
} from '@/types/app/payment-method';

import PaymentMethodFormFields from './partials/payment-method-form-fields';

interface Props {
    paymentMethodData: PaymentMethodResource;
    typeOptions: PaymentMethodTypeOption[];
}

export default function EditPaymentMethod({
    paymentMethodData,
    typeOptions,
}: Props) {
    const paymentMethod = paymentMethodData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Metodos de pagamento', href: index.url() },
            { title: paymentMethod.name, href: show.url(paymentMethod.uuid) },
            { title: 'Editar', href: edit.url(paymentMethod.uuid) },
        ],
    });

    return (
        <>
            <Head title={`Editar ${paymentMethod.name}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={show.url(paymentMethod.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Editar metodo de pagamento
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {paymentMethod.code}
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={update.url(paymentMethod.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatorios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Dados do metodo</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PaymentMethodFormFields
                                        paymentMethod={paymentMethod}
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-canvas/95 backdrop-blur supports-backdrop-filter:bg-canvas/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        disabled={processing}
                                    >
                                        <Link href={show.url(paymentMethod.uuid)}>
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
