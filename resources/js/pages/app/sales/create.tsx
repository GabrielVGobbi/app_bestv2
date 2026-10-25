import { Head, setLayoutProps } from '@inertiajs/react';

import {
    create,
    index,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import Header from '@/components/dashboard/header';
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
        <div className="flex min-h-full flex-col">
            <Head title="Nova venda" />

            <Header
                title="Nova venda"
                subtitle="Criar rascunho comercial sem financeiro"
                backHref={index.url()}
            />

            <SaleForm formOptions={formOptions} cancelHref={index.url()} />
        </div>
    );
}
