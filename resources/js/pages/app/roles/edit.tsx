import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { edit, index, show, update } from '@/routes/roles';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Role } from '@/types/app/role';
import { useState } from 'react';
import { httpPut } from '@/lib/http';
import { toast } from 'sonner';

interface Props {
    roleData: { data: Role };
}

export default function EditRole({ roleData }: Props) {
    const role = roleData.data;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Papéis', href: index.url() },
            { title: role.name, href: show.url(role.id) },
            { title: 'Editar', href: edit.url(role.id) },
        ],
    });

    const [form, setForm] = useState({
        name: role.name,
        slug: role.slug,
        description: role.description ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        try {
            await httpPut(update.url(role.id), form);
            toast.success('Papel atualizado com sucesso!');
            router.visit(show.url(role.id));
        } catch (err: any) {
            if (err?.type === 'validation') {
                setErrors(err.errors);
            } else {
                toast.error('Erro ao atualizar papel.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title={`Editar ${role.name}`} />

            <div className="container mx-auto max-w-3xl">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between">
                        <Heading title="Editar Papel" description="Atualize as informações do papel" />
                        <Button variant="outline" asChild>
                            <Link href={show.url(role.id)}>Cancelar</Link>
                        </Button>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Informações do Papel</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid gap-4">
                                    <div className="space-y-1">
                                        <Label>
                                            Nome <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={form.name}
                                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                            placeholder="Ex: Administrador"
                                            disabled={processing}
                                        />
                                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                    </div>
                                    <div className="space-y-1">
                                        <Label>
                                            Slug <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            value={form.slug}
                                            onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
                                            placeholder="Ex: admin"
                                            disabled={processing}
                                        />
                                        {errors.slug && <p className="text-sm text-destructive">{errors.slug}</p>}
                                    </div>
                                    <div className="space-y-1">
                                        <Label>Descrição</Label>
                                        <Input
                                            value={form.description}
                                            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                            placeholder="Descrição do papel"
                                            disabled={processing}
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-destructive">{errors.description}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Salvando...' : 'Salvar alterações'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={show.url(role.id)}>Cancelar</Link>
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
