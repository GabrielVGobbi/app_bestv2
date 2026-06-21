import { Form, Link } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { toast } from 'sonner';

import {
    store,
    update,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/utils';
import type { Sale, SaleFormOptions } from '@/types/app/sale';

interface Props {
    sale?: Sale;
    formOptions: SaleFormOptions;
    cancelHref: string;
}

export default function SaleForm({ sale, formOptions, cancelHref }: Props) {
    const isEditing = Boolean(sale);

    return (
        <Form
            action={isEditing && sale ? update.url(sale.uuid) : store.url()}
            method={isEditing ? 'patch' : 'post'}
            options={{ preserveScroll: true }}
            onError={() => toast.error('Verifique os campos obrigatórios.')}
            className="space-y-4"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados comerciais</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="company_id"
                                >
                                    Empresa
                                </label>
                                <Select
                                    name="company_id"
                                    defaultValue={
                                        sale?.company_id
                                            ? String(sale.company_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger
                                        id="company_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Selecione a empresa" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {formOptions.companies.map(
                                            (company) => (
                                                <SelectItem
                                                    key={company.id}
                                                    value={String(company.id)}
                                                >
                                                    {company.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.company_id} />
                            </div>

                            <div className="grid gap-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="client_id"
                                >
                                    Cliente
                                </label>
                                <Select
                                    name="client_id"
                                    defaultValue={
                                        sale?.client_id
                                            ? String(sale.client_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger
                                        id="client_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Selecione um cliente" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {formOptions.clients.map((client) => (
                                            <SelectItem
                                                key={client.id}
                                                value={String(client.id)}
                                            >
                                                {client.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.client_id} />
                            </div>

                            <div className="grid gap-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="title"
                                >
                                    Título
                                </label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={sale?.title ?? ''}
                                    placeholder="Campanha, pacote ou negociação"
                                />
                                <InputError message={errors.title} />
                            </div>

                            {isEditing ? (
                                <div className="grid gap-2">
                                    <label
                                        className="text-sm font-medium"
                                        htmlFor="discount_amount"
                                    >
                                        Desconto da venda em centavos
                                    </label>
                                    <Input
                                        id="discount_amount"
                                        name="discount_amount"
                                        type="number"
                                        min="0"
                                        defaultValue={
                                            sale?.discount_amount ?? 0
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Total atual:{' '}
                                        {formatMoney(sale?.total_amount, {
                                            fromCents: true,
                                            fallback: '-',
                                        })}
                                    </p>
                                    <InputError
                                        message={errors.discount_amount}
                                    />
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <label
                                    className="text-sm font-medium"
                                    htmlFor="notes"
                                >
                                    Observações
                                </label>
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    defaultValue={sale?.notes ?? ''}
                                    rows={5}
                                />
                                <InputError message={errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="sticky bottom-0 flex items-center justify-end gap-2 border-t bg-background/95 py-3 backdrop-blur">
                        <Button
                            type="button"
                            variant="ghost"
                            asChild
                            disabled={processing}
                        >
                            <Link href={cancelHref}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="size-4" />
                            {processing ? 'Salvando...' : 'Salvar'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
