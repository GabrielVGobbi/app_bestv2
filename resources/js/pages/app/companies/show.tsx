import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Building2, CalendarDays, FileText } from 'lucide-react';

import {
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import Header from '@/components/dashboard/header';
import DeleteButton from '@/components/delete-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { maskCnpj } from '@/lib/masks';
import { formatDate } from '@/lib/utils';
import type { CompanyResource } from '@/types/app/company';

import { CompanySubNav } from './components/company-sub-nav';

interface Props {
    companyData: CompanyResource;
    permissions: {
        can_manage: boolean;
    };
}

function cleanValue(value: unknown): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    if (typeof value === 'number') {
        return String(value);
    }

    return '-';
}

function metadataValue(
    metadata: Record<string, unknown> | null,
    key: string,
): string {
    return cleanValue(metadata?.[key]);
}

function formatMetadata(metadata: Record<string, unknown> | null): string {
    if (!metadata) {
        return '{}';
    }

    return JSON.stringify(metadata, null, 2);
}

function InfoField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm break-words">{value}</p>
        </div>
    );
}

export default function ShowCompany({ companyData, permissions }: Props) {
    const company = companyData.data;
    const companyName = String(company.name ?? company.uuid);
    const initials = companyName
        .split(' ')
        .map((part) => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empresas', href: index.url() },
            { title: companyName, href: show.url(company.uuid) },
        ],
    });

    return (
        <>
            <Head title={companyName} />

            <Header>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        asChild
                    >
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="page-heading-title">{companyName}</h1>
                        <p className="text-sm text-muted-foreground">
                            {company.uuid}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <CompanySubNav
                        companyUuid={company.uuid}
                        currentPage="show"
                        canManage={permissions.can_manage}
                    />
                    {permissions.can_manage ? (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-6"
                            />
                            <DeleteButton
                                url={destroy.url(company.uuid)}
                                resourceName={companyName}
                                resourceType="empresa"
                                redirectTo={index.url()}
                            />
                        </>
                    ) : null}
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-lg border bg-card">
                            <div className="p-5">
                                <div className="flex h-28 items-center justify-center rounded-lg bg-secondary text-3xl font-semibold text-primary">
                                    {initials}
                                </div>

                                <div className="mt-4 space-y-1">
                                    <h2 className="truncate text-base font-semibold">
                                        {companyName}
                                    </h2>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {company.nickname}
                                    </p>
                                </div>

                                <div className="mt-5 space-y-2 text-sm text-muted-foreground">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-4 shrink-0" />
                                        <span className="truncate">
                                            {maskCnpj(company.cnpj)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <FileText className="size-4 shrink-0" />
                                        <span className="truncate">
                                            {metadataValue(
                                                company.fiscal_metadata,
                                                'razaoSocial',
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t px-5 py-4 text-xs text-muted-foreground">
                                <div className="flex items-center gap-2">
                                    <CalendarDays className="size-3.5" />
                                    <span>
                                        Criado {formatDate(company.created_at)}
                                    </span>
                                </div>
                                <p className="mt-1">
                                    Ultima edicao{' '}
                                    {formatDate(company.updated_at)}
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main className="min-w-0 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Resumo</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <InfoField label="Nome" value={company.name} />
                                <InfoField
                                    label="Apelido"
                                    value={company.nickname}
                                />
                                <InfoField
                                    label="CNPJ"
                                    value={maskCnpj(company.cnpj)}
                                />
                                <InfoField
                                    label="Razao social"
                                    value={metadataValue(
                                        company.fiscal_metadata,
                                        'razaoSocial',
                                    )}
                                />
                                <InfoField
                                    label="Nome fantasia"
                                    value={metadataValue(
                                        company.fiscal_metadata,
                                        'nomeFantasia',
                                    )}
                                />
                                <InfoField
                                    label="Inscricao municipal"
                                    value={metadataValue(
                                        company.fiscal_metadata,
                                        'inscricaoMunicipal',
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Metadados fiscais</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="max-h-[520px] overflow-auto rounded-md border bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground">
                                    {formatMetadata(company.fiscal_metadata)}
                                </pre>
                            </CardContent>
                        </Card>
                    </main>
                </div>
            </div>
        </>
    );
}
