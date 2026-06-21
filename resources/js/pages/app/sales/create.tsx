import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import {
    create,
    index,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import type { SaleFormOptions } from '@/types/app/sale';

import SaleForm from './partials/sale-form';

interface Props {
    formOptions: SaleFormOptions;
}

export default function CreateSale({ formOptions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vendas', href: index.url() },
            { title: 'Nova venda', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Nova venda" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">Nova venda</h1>
                        <p className="text-sm text-muted-foreground">
                            Criar rascunho comercial sem financeiro
                        </p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-md p-4 sm:p-6">
                <SaleForm formOptions={formOptions} cancelHref={index.url()} />
            </div>
        </>
    );
}
