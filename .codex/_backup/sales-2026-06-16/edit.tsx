import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { edit, index, show } from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
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
        <>
            <Head title={`Editar ${sale.order_number}`} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={show.url(sale.uuid)}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">Editar venda</h1>
                        <p className="text-sm text-muted-foreground">{sale.order_number}</p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm p-4">
                <SaleForm sale={sale} formOptions={formOptions} cancelHref={show.url(sale.uuid)} />
            </div>
        </>
    );
}
