import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import {
    edit,
    index,
    show,
    update,
} from '@/actions/App/Modules/Finance/Http/Controllers/PaymentMethodController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
            { title: 'Métodos de pagamento', href: index.url() },
            { title: paymentMethod.name, href: show.url(paymentMethod.uuid) },
            { title: 'Editar', href: edit.url(paymentMethod.uuid) },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title={`Editar ${paymentMethod.name}`} />

            <Header
                title="Editar método de pagamento"
                subtitle={paymentMethod.type_label}
                backHref={show.url(paymentMethod.uuid)}
            />

            <Form
                action={update.url(paymentMethod.uuid)}
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
                                    <PaymentMethodFormFields
                                        paymentMethod={paymentMethod}
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
                                            href={show.url(paymentMethod.uuid)}
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
