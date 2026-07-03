import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

import {
    create,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/utils';
import type {
    SaleCollectionResource,
    SalePagePermissions,
} from '@/types/app/sale';

interface Props {
    sales: SaleCollectionResource;
    permissions: SalePagePermissions;
}

export default function SaleIndex({ sales, permissions }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Vendas', href: index.url() }],
    });

    return (
        <>
            <Head title="Vendas" />

            <div className="page-wrapper-xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Vendas"
                            description="Rascunhos, orçamentos e campanhas comerciais"
                        />
                        {permissions.can_create ? (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus className="size-4" />
                                    Nova venda
                                </Link>
                            </Button>
                        ) : null}
                    </div>

                    <div className="grid gap-3">
                        {sales.data.map((sale) => (
                            <Card key={sale.uuid}>
                                <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-semibold">
                                                {sale.order_number}
                                            </h2>
                                            <Badge variant="outline">
                                                {sale.status_presenter.label}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 truncate text-sm text-muted-foreground">
                                            {sale.title ??
                                                sale.client?.name ??
                                                'Venda sem título'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {sale.company?.nickname ??
                                                sale.company?.name ??
                                                'Sem empresa'}{' '}
                                            · Criada{' '}
                                            {formatDate(sale.created_at)}
                                        </p>
                                    </div>
                                    <div className="flex items-center justify-between gap-3 sm:justify-end">
                                        <div className="text-right">
                                            <p className="text-sm font-semibold">
                                                {formatMoney(
                                                    sale.total_amount,
                                                    {
                                                        fromCents: true,
                                                        fallback: '-',
                                                    },
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {sale.seller?.name ??
                                                    'Sem vendedor'}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={show.url(sale.uuid)}
                                                aria-label="Visualizar venda"
                                            >
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
