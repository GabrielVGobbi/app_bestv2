import { Head, setLayoutProps } from '@inertiajs/react';

import {
    edit,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import Header from '@/components/dashboard/header';
import type { SaleFormOptions, SaleResource } from '@/types/app/sale';

import SaleForm from './partials/sale-form';

interface Props {
    saleData: SaleResource;
    formOptions: SaleFormOptions;
}

export default function EditSale({ saleData, formOptions }: Props) {
    const sale = saleData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vendas', href: index.url() },
            { title: sale.order_number, href: show.url(sale.uuid) },
            { title: 'Editar', href: edit.url(sale.uuid) },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title={`Editar ${sale.order_number}`} />

            <Header
                title={`Editar ${sale.order_number}`}
                subtitle={sale.title ?? sale.client?.name ?? undefined}
                backHref={show.url(sale.uuid)}
            />

            <SaleForm
                sale={sale}
                formOptions={formOptions}
                cancelHref={show.url(sale.uuid)}
            />
        </div>
    );
}
