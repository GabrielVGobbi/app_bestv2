import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    create,
    index,
    store,
} from '@/actions/App/Modules/Finance/Http/Controllers/PaymentMethodController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaymentMethodTypeOption } from '@/types/app/payment-method';

import PaymentMethodFormFields from './partials/payment-method-form-fields';

interface Props {
    typeOptions: PaymentMethodTypeOption[];
}

export default function CreatePaymentMethod({ typeOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Metodos de pagamento', href: index.url() },
            { title: 'Novo metodo', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo metodo de pagamento" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Novo metodo de pagamento
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Cadastre um meio aceito pelo financeiro
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={store.url()}
                    method="post"
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
                                        typeOptions={typeOptions}
                                        errors={errors}
                                    />
                                </CardContent>
                            </Card>

                            <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                <div className="flex items-center justify-between gap-3 py-3">
                                    <p className="text-xs text-muted-foreground">
                                        Campos marcados com{' '}
                                        <span className="text-destructive">*</span>{' '}
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
                                            <Link href={index.url()}>Cancelar</Link>
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
