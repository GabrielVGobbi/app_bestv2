import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import {
    create,
    index,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import type {
    CounterpartyTypeByDirection,
    FinancialEntryOption,
} from '@/types/app/financial-entry';
import FinancialEntryForm from './components/financial-entry-form';

interface Props {
    directionOptions: FinancialEntryOption[];
    componentCodeOptions: FinancialEntryOption[];
    componentOperationOptions: FinancialEntryOption[];
    counterpartyTypeByDirection: CounterpartyTypeByDirection;
}

export default function CreateFinancialEntry({
    directionOptions,
    componentCodeOptions,
    componentOperationOptions,
    counterpartyTypeByDirection,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Lançamentos Financeiros', href: index.url() },
            { title: 'Novo lançamento', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo lançamento financeiro" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">
                            Novo lançamento financeiro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Entrada manual independente de vendas, compras ou fiscal
                        </p>
                    </div>
                </div>
            </Header>

            <div className="mx-auto max-w-2xl p-6">
                <FinancialEntryForm
                    directionOptions={directionOptions}
                    componentCodeOptions={componentCodeOptions}
                    componentOperationOptions={componentOperationOptions}
                    counterpartyTypeByDirection={counterpartyTypeByDirection}
                />
            </div>
        </>
    );
}
