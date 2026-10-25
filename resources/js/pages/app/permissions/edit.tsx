import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { X, Save } from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePrivateState } from '@/contexts/private-state-context';
import { httpPut, toApiError } from '@/lib/http';
import type { ValidationErrors } from '@/lib/http';
import { update as apiUpdate } from '@/routes/api/permissions';
import { edit, index, show } from '@/routes/permissions';
import type { Permission } from '@/types/app/permission';

interface Props {
    permissionData: { data: Permission };
}

export default function EditPermission({ permissionData }: Props) {
    const formId = useId();
    const { scope } = usePrivateState();
    const permission = permissionData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Permissões', href: index.url() },
            { title: permission.name, href: show.url(permission.id) },
            { title: 'Editar', href: edit.url(permission.id) },
        ],
    });

    const [form, setForm] = useState({
        name: permission.name,
        slug: permission.slug,
        group: permission.group ?? '',
        description: permission.description ?? '',
    });
    const [errors, setErrors] = useState<ValidationErrors>({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            if (!scope.isCurrent()) {
                return;
            }

            await httpPut(apiUpdate.url(permission.id), form);

            if (!scope.isCurrent()) {
                return;
            }

            toast.success('Permissão atualizada com sucesso!');
            router.visit(show.url(permission.id));
        } catch (err: unknown) {
            const failure = toApiError(err);

            if (!scope.isCurrent() || failure.type === 'canceled') {
                return;
            }

            if (failure.type === 'validation') {
                setErrors(failure.errors);
            } else {
                toast.error('Erro ao atualizar permissão.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title={`Editar ${permission.name}`} />

            <Header
                title={permission.name}
                subtitle="Atualize as informações da permissão"
                backHref={show.url(permission.id)}
            >
                <Button variant="outline" asChild>
                    <Link href={show.url(permission.id)}>
                        <X className="size-4" aria-hidden="true" />
                        Cancelar
                    </Link>
                </Button>
            </Header>

            <div className="page-wrapper-sm">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações da permissão</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor={`${formId}-name`}>
                                            Nome{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id={`${formId}-name`}
                                            aria-invalid={Boolean(errors.name)}
                                            aria-describedby={
                                                errors.name
                                                    ? `${formId}-name-error`
                                                    : undefined
                                            }
                                            value={form.name}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    name: e.target.value,
                                                }))
                                            }
                                            placeholder="Ex: Gerenciar Usuários"
                                            disabled={processing}
                                        />
                                        {errors.name && (
                                            <p
                                                id={`${formId}-name-error`}
                                                className="text-sm text-destructive"
                                            >
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`${formId}-slug`}>
                                            Slug{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id={`${formId}-slug`}
                                            aria-invalid={Boolean(errors.slug)}
                                            aria-describedby={
                                                errors.slug
                                                    ? `${formId}-slug-error`
                                                    : undefined
                                            }
                                            value={form.slug}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    slug: e.target.value,
                                                }))
                                            }
                                            placeholder="Ex: manage-users"
                                            disabled={processing}
                                        />
                                        {errors.slug && (
                                            <p
                                                id={`${formId}-slug-error`}
                                                className="text-sm text-destructive"
                                            >
                                                {errors.slug}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`${formId}-group`}>
                                            Grupo
                                        </Label>
                                        <Input
                                            id={`${formId}-group`}
                                            aria-invalid={Boolean(errors.group)}
                                            aria-describedby={
                                                errors.group
                                                    ? `${formId}-group-error`
                                                    : undefined
                                            }
                                            value={form.group}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    group: e.target.value,
                                                }))
                                            }
                                            placeholder="Ex: Usuários"
                                            disabled={processing}
                                        />
                                        {errors.group && (
                                            <p
                                                id={`${formId}-group-error`}
                                                className="text-sm text-destructive"
                                            >
                                                {errors.group}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`${formId}-description`}
                                        >
                                            Descrição
                                        </Label>
                                        <Input
                                            id={`${formId}-description`}
                                            aria-invalid={Boolean(
                                                errors.description,
                                            )}
                                            aria-describedby={
                                                errors.description
                                                    ? `${formId}-description-error`
                                                    : undefined
                                            }
                                            value={form.description}
                                            onChange={(e) =>
                                                setForm((f) => ({
                                                    ...f,
                                                    description: e.target.value,
                                                }))
                                            }
                                            placeholder="Descrição da permissão"
                                            disabled={processing}
                                        />
                                        {errors.description && (
                                            <p
                                                id={`${formId}-description-error`}
                                                className="text-sm text-destructive"
                                            >
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        <Save
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Salvando...'
                                            : 'Salvar alterações'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={show.url(permission.id)}>
                                            <X
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancelar
                                        </Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
