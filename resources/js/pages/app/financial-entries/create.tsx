import { Head, setLayoutProps } from '@inertiajs/react';

import {
    create,
    index,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Header from '@/components/dashboard/header';
import type {
    CounterpartyTypeByDirection,
    FinancialAmountAdjustmentOption,
    FinancialEntryOption,
} from '@/types/app/financial-entry';
import FinancialEntryForm from './components/financial-entry-form';

interface Props {
    directionOptions: FinancialEntryOption[];
    amountAdjustmentOptions: FinancialAmountAdjustmentOption[];
    counterpartyTypeByDirection: CounterpartyTypeByDirection;
}

export default function CreateFinancialEntry({
    directionOptions,
    amountAdjustmentOptions,
    counterpartyTypeByDirection,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Lançamentos financeiros', href: index.url() },
            { title: 'Novo lançamento', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo lançamento financeiro" />

            <Header
                title="Novo lançamento financeiro"
                subtitle="Entrada manual independente de vendas, compras ou fiscal"
                backHref={index.url()}
            />

            <div className="page-wrapper-md">
                <FinancialEntryForm
                    directionOptions={directionOptions}
                    amountAdjustmentOptions={amountAdjustmentOptions}
                    counterpartyTypeByDirection={counterpartyTypeByDirection}
                />
            </div>
        </>
    );
}
