import { Head, setLayoutProps } from '@inertiajs/react';

import {
    create,
    index,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import { ContentHeader } from '@/components/content-header';
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
            { title: 'Lançamentos Financeiros', href: index.url() },
            { title: 'Novo lançamento', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo lançamento financeiro" />

            <ContentHeader
                title="Novo lançamento financeiro"
                description="Entrada manual independente de vendas, compras ou fiscal"
                backHref={index.url()}
            />

            <div className="mx-auto max-w-2xl p-3 sm:p-6">
                <FinancialEntryForm
                    directionOptions={directionOptions}
                    amountAdjustmentOptions={amountAdjustmentOptions}
                    counterpartyTypeByDirection={counterpartyTypeByDirection}
                />
            </div>
        </>
    );
}
