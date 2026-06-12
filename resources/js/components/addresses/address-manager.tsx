import { Edit, MapPin, Plus, Star, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { AddressFormDialog } from '@/components/addresses/address-form-dialog';
import type { AddressFormData, AddressFormFields } from '@/components/addresses/address-form-dialog';
import { DeleteConfirmModal } from '@/components/delete-confirm-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { apiRoutes } from '@/lib/api/api-routes';
import {
    getErrorMessage,
    httpDelete,
    httpGet,
    httpPatch,
    httpPost,
    httpPut,
    isApiError,
} from '@/lib/http';
import type { ApiResponse, ValidationErrors } from '@/lib/http';
import type { Address } from '@/types/address';

interface AddressManagerProps {
    model: string;
    modelId: string | number;
    initialAddresses?: Address[];
    title?: string;
    emptyMessage?: string;
}

type AddressCollectionResponse = {
    data: Address[];
};

type AddressPayload = AddressFormData & {
    model?: string;
    model_id?: string | number;
};

export function AddressManager({
    model,
    modelId,
    initialAddresses = [],
    title = 'Endereços',
    emptyMessage = 'Nenhum Endereço cadastrado.',
}: AddressManagerProps) {
    const [addresses, setAddresses] = useState<Address[]>(initialAddresses);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogVersion, setDialogVersion] = useState(0);
    const [editingAddress, setEditingAddress] = useState<Address | null>(null);
    const [addressToDelete, setAddressToDelete] = useState<Address | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<ValidationErrors<AddressFormFields>>({});

    const orderedAddresses = useMemo(
        () => [...addresses].sort((a, b) => Number(b.is_default) - Number(a.is_default)),
        [addresses],
    );

    async function refreshAddresses(): Promise<void> {
        const params = new URLSearchParams({
            model,
            model_id: String(modelId),
        });

        const response = await httpGet<AddressCollectionResponse>(`${apiRoutes.addresses.index}?${params.toString()}`);
        setAddresses(response.data);
    }

    function openCreateDialog(): void {
        setEditingAddress(null);
        setErrors({});
        setDialogVersion((version) => version + 1);
        setDialogOpen(true);
    }

    function openEditDialog(address: Address): void {
        setEditingAddress(address);
        setErrors({});
        setDialogVersion((version) => version + 1);
        setDialogOpen(true);
    }

    async function saveAddress(data: AddressFormData): Promise<void> {
        setProcessing(true);
        setErrors({});

        const payload: AddressPayload = editingAddress
            ? data
            : {
                  ...data,
                  model,
                  model_id: modelId,
              };

        try {
            if (editingAddress) {
                await httpPut<ApiResponse<Address>, AddressPayload>(apiRoutes.addresses.update(editingAddress.uuid), payload);
            } else {
                await httpPost<ApiResponse<Address>, AddressPayload>(apiRoutes.addresses.store, payload);
            }

            await refreshAddresses();
            setDialogOpen(false);
            setEditingAddress(null);
            toast.success(editingAddress ? 'Endereço atualizado.' : 'Endereço cadastrado.');
        } catch (error) {
            if (isApiError(error) && error.type === 'validation') {
                setErrors(error.errors as ValidationErrors<AddressFormFields>);

                return;
            }

            toast.error(getErrorMessage(isApiError(error) ? error : { type: 'server', status: 500, message: 'Erro inesperado.' }));
        } finally {
            setProcessing(false);
        }
    }

    async function setDefaultAddress(address: Address): Promise<void> {
        if (address.is_default) {
            return;
        }

        setProcessing(true);

        try {
            await httpPatch<ApiResponse<Address>, void>(apiRoutes.addresses.setDefault(address.uuid));
            await refreshAddresses();
            toast.success('Endereço Padrão atualizado.');
        } catch (error) {
            toast.error(getErrorMessage(isApiError(error) ? error : { type: 'server', status: 500, message: 'Erro inesperado.' }));
        } finally {
            setProcessing(false);
        }
    }

    async function deleteAddress(): Promise<void> {
        if (!addressToDelete) {
            return;
        }

        setProcessing(true);

        try {
            await httpDelete<ApiResponse<null>>(apiRoutes.addresses.destroy(addressToDelete.uuid));
            await refreshAddresses();
            setAddressToDelete(null);
            toast.success('Endereço removido.');
        } catch (error) {
            toast.error(getErrorMessage(isApiError(error) ? error : { type: 'server', status: 500, message: 'Erro inesperado.' }));
        } finally {
            setProcessing(false);
        }
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle>{title}</CardTitle>
                <Button type="button" size="sm" onClick={openCreateDialog}>
                    <Plus className="size-4" />
                    Adicionar
                </Button>
            </CardHeader>
            <CardContent className="space-y-3">
                {orderedAddresses.length === 0 ? (
                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">{emptyMessage}</div>
                ) : (
                    orderedAddresses.map((address) => (
                        <div key={address.uuid} className="rounded-md border p-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="flex min-w-0 gap-2">
                                    <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-medium">{address.name || 'Endereço'}</p>
                                            {address.is_default && <Badge variant="secondary">Padrão</Badge>}
                                        </div>
                                        <p className="text-sm text-muted-foreground">{address.full_address}</p>
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => setDefaultAddress(address)}
                                        disabled={processing || address.is_default}
                                        title="Definir como Padrão"
                                        aria-label="Definir como Endereço Padrão"
                                    >
                                        <Star className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => openEditDialog(address)}
                                        aria-label="Editar Endereço"
                                    >
                                        <Edit className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => setAddressToDelete(address)}
                                        aria-label="Remover Endereço"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </CardContent>

            <AddressFormDialog
                key={`${editingAddress?.uuid ?? 'new-address'}-${dialogVersion}`}
                open={dialogOpen}
                address={editingAddress}
                processing={processing}
                errors={errors}
                onOpenChange={setDialogOpen}
                onSubmit={(data) => void saveAddress(data)}
            />

            <DeleteConfirmModal
                isOpen={addressToDelete !== null}
                onClose={() => setAddressToDelete(null)}
                onConfirm={() => void deleteAddress()}
                isLoading={processing}
                title="Remover Endereço"
                description="Este Endereço sera removido do cadastro. Se ele for o Padrão, o sistema promovera outro Endereço automaticamente."
                confirmLabel="Remover Endereço"
            />
        </Card>
    );
}
