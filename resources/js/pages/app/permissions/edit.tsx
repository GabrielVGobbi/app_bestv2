import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { httpPut } from '@/lib/http';
import { edit, index, show, update } from '@/routes/permissions';
import type { Permission } from '@/types/app/permission';

interface Props {
    permissionData: { data: Permission };
}

export default function EditPermission({ permissionData }: Props) {
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
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await httpPut(update.url(permission.id), form);
            toast.success('Permissão atualizada com sucesso!');
            router.visit(show.url(permission.id));
        } catch (err: any) {
            if (err?.type === 'validation') {
                setErrors(err.errors);
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

            <div className="container mx-auto max-w-3xl">
                <div className="page-section">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Editar Permissão"
                            description="Atualize as informações da permissão"
                        />
                        <Button variant="outline" asChild>
                            <Link href={show.url(permission.id)}>Cancelar</Link>
                        </Button>
                    </div>

                    <Card variant="form">
                        <CardHeader>
                            <CardTitle>Informações da Permissão</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid gap-4">
                                    <div className="space-y-1">
                                        <Label>
                                            Nome{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
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
                                            <p className="text-sm text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        <Label>
                                            Slug{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
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
                                            <p className="text-sm text-destructive">
                                                {errors.slug}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        <Label>Grupo</Label>
                                        <Input
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
                                            <p className="text-sm text-destructive">
                                                {errors.group}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        <Label>Descrição</Label>
                                        <Input
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
                                            <p className="text-sm text-destructive">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Salvando...'
                                            : 'Salvar alterações'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={show.url(permission.id)}>
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
