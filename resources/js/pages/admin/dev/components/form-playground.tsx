import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Building2,
    Check,
    CreditCard,
    FileText,
    Filter,
    Mail,
    MapPin,
    Save,
    Send,
    ShieldCheck,
    SlidersHorizontal,
    User,
    X,
} from 'lucide-react';
import type { ComponentType } from 'react';

import { components } from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Header from '@/components/dashboard/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const fieldVariants = [
    {
        title: 'Empilhado (padrão)',
        description: 'Rótulo acima, controle abaixo e texto de ajuda embaixo.',
        variant: 'stacked' as const,
    },
    {
        title: 'Em linha',
        description: 'Rótulo ao lado para configurações densas.',
        variant: 'inline' as const,
    },
    {
        title: 'Rótulo sobre a borda',
        description: 'O rótulo flutua sobre a borda do campo.',
        variant: 'overlap' as const,
    },
    {
        title: 'Rótulo interno',
        description: 'O rótulo fica dentro da moldura do campo.',
        variant: 'inset' as const,
    },
];

function SectionHeader({
    icon: Icon,
    title,
    description,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary-soft text-primary-soft-foreground">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <h2 className="text-base font-semibold text-foreground">
                    {title}
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function OptionSelect({ id, options }: { id: string; options: string[] }) {
    return (
        <Select name={id} defaultValue={options[0]}>
            <SelectTrigger id={id} className="w-full" />
            <SelectContent>
                {options.map((option, optionIndex) => (
                    <SelectItem key={option} index={optionIndex} value={option}>
                        {option}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function FieldStyleGallery() {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle>Estilos do FormField</CardTitle>
                        <CardDescription>
                            Variações do campo compartilhado, uma abaixo da
                            outra.
                        </CardDescription>
                    </div>
                    <Badge variant="outline">componente compartilhado</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="space-y-5">
                        {fieldVariants.map((item) => (
                            <div
                                key={item.title}
                                className="rounded-lg border border-border bg-muted p-4"
                            >
                                <div className="mb-4">
                                    <h3 className="text-sm font-semibold text-foreground">
                                        {item.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                                <FormField
                                    id={`field-${item.variant}`}
                                    label="E-mail da empresa"
                                    description="Use um endereço que receba os avisos do sistema."
                                    variant={item.variant}
                                    required
                                >
                                    <Input
                                        id={`field-${item.variant}`}
                                        type="email"
                                        placeholder="financeiro@empresa.com.br"
                                    />
                                </FormField>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4">
                        <h3 className="text-sm font-semibold text-foreground">
                            Estados do campo
                        </h3>
                        <div className="mt-4 space-y-4">
                            <FormField
                                id="field-error"
                                label="CNPJ"
                                error="Informe um documento válido."
                                variant="overlap"
                            >
                                <Input
                                    id="field-error"
                                    aria-invalid
                                    placeholder="00.000.000/0001-00"
                                />
                            </FormField>
                            <FormField
                                id="field-success"
                                label="Status"
                                description="Pronto para enviar."
                                variant="inset"
                            >
                                <Input
                                    id="field-success"
                                    defaultValue="Verificado"
                                    readOnly
                                />
                            </FormField>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function AccountFormCard() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={Building2}
                    title="Formulário de conta"
                    description="Card clássico com campos agrupados e ações no rodapé."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-5 md:grid-cols-2">
                    <FormField id="account-name" label="Nome da conta" required>
                        <Input
                            id="account-name"
                            placeholder="Northwind Financeira"
                        />
                    </FormField>
                    <FormField id="account-owner" label="Responsável">
                        <Input id="account-owner" placeholder="Joana Costa" />
                    </FormField>
                    <FormField
                        id="account-email"
                        label="E-mail de cobrança"
                        variant="overlap"
                        required
                    >
                        <Input
                            id="account-email"
                            type="email"
                            placeholder="cobranca@empresa.com.br"
                        />
                    </FormField>
                    <FormField
                        id="account-plan"
                        label="Plano"
                        description="Controla limites e permissões."
                    >
                        <OptionSelect
                            id="account-plan"
                            options={['Startup', 'Business', 'Enterprise']}
                        />
                    </FormField>
                    <FormField
                        id="account-notes"
                        label="Observações internas"
                        className="md:col-span-2"
                    >
                        <Textarea
                            id="account-notes"
                            name="account-notes"
                            rows={4}
                            placeholder="Detalhes comerciais, contratos e notas de suporte."
                            className="min-h-28"
                        />
                    </FormField>
                </div>
            </CardContent>
            <CardFooter className="justify-end gap-2 border-t pt-5">
                <Button type="button" variant="outline">
                    <X className="size-4" aria-hidden="true" />
                    Cancelar
                </Button>
                <Button type="button">
                    <Save />
                    Salvar conta
                </Button>
            </CardFooter>
        </Card>
    );
}

function InlineSettingsForm() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={SlidersHorizontal}
                    title="Configurações em linha"
                    description="Rótulos à esquerda para telas de configuração."
                />
            </CardHeader>
            <CardContent>
                <div className="space-y-5">
                    <FormField
                        id="settings-slug"
                        label="Slug"
                        variant="inline"
                        description="Usado em URLs públicas e integrações."
                    >
                        <Input id="settings-slug" defaultValue="northwind" />
                    </FormField>
                    <FormField
                        id="settings-timezone"
                        label="Fuso horário"
                        variant="inline"
                    >
                        <OptionSelect
                            id="settings-timezone"
                            options={[
                                'America/Sao_Paulo',
                                'America/New_York',
                                'Europe/Lisbon',
                            ]}
                        />
                    </FormField>
                    <FormField
                        id="settings-domain"
                        label="Domínio"
                        variant="inline"
                        error="O domínio precisa apontar para este ambiente."
                    >
                        <Input
                            id="settings-domain"
                            aria-invalid
                            defaultValue="app.example.com"
                        />
                    </FormField>
                    <div className="grid gap-2 sm:grid-cols-[10rem_1fr] sm:items-start">
                        <Label className="pt-2 text-sm font-semibold">
                            Acesso
                        </Label>
                        <div className="grid gap-3 rounded-lg border border-border bg-muted p-4">
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox defaultChecked />
                                <span>
                                    <span className="block font-medium text-foreground">
                                        Exigir 2FA
                                    </span>
                                    <span className="text-muted-foreground">
                                        Pede autenticação em duas etapas a todos
                                        os membros.
                                    </span>
                                </span>
                            </label>
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox />
                                <span>
                                    <span className="block font-medium text-foreground">
                                        Restringir exportações
                                    </span>
                                    <span className="text-muted-foreground">
                                        Exige permissão explícita para baixar
                                        relatórios.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="justify-end border-t pt-5">
                <Button type="button" variant="secondary">
                    <Save className="size-4" aria-hidden="true" />
                    Aplicar configurações
                </Button>
            </CardFooter>
        </Card>
    );
}

function CompactProfileForm() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={User}
                    title="Perfil compacto"
                    description="Card menor com campos internos, avatar e ações rápidas."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-5 sm:grid-cols-[4rem_1fr]">
                    <div className="flex size-16 items-center justify-center rounded-lg bg-primary-soft text-lg font-semibold text-primary-soft-foreground">
                        JC
                    </div>
                    <div className="grid gap-4">
                        <FormField
                            id="profile-name"
                            label="Nome de exibição"
                            variant="inset"
                        >
                            <Input
                                id="profile-name"
                                defaultValue="Joana Costa"
                            />
                        </FormField>
                        <FormField
                            id="profile-email"
                            label="E-mail"
                            variant="inset"
                        >
                            <Input
                                id="profile-email"
                                type="email"
                                defaultValue="joana@empresa.com.br"
                            />
                        </FormField>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="justify-between gap-3 border-t pt-5">
                <Badge variant="success">
                    <ShieldCheck />
                    Verificado
                </Badge>
                <Button type="button" size="sm">
                    <Check />
                    Atualizar
                </Button>
            </CardFooter>
        </Card>
    );
}

function PaymentForm() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={CreditCard}
                    title="Dados de pagamento"
                    description="Rótulos sobre a borda, ícone como prefixo e linhas responsivas."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-4">
                    <FormField
                        id="card-name"
                        label="Nome no cartão"
                        variant="overlap"
                    >
                        <Input id="card-name" placeholder="Joana Costa" />
                    </FormField>
                    <FormField
                        id="card-number"
                        label="Número do cartão"
                        variant="overlap"
                    >
                        <div className="relative">
                            <CreditCard className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="card-number"
                                className="pl-9"
                                placeholder="4242 4242 4242 4242"
                            />
                        </div>
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="card-expiration"
                            label="Validade"
                            variant="inset"
                        >
                            <Input id="card-expiration" placeholder="08/30" />
                        </FormField>
                        <FormField id="card-cvc" label="CVC" variant="inset">
                            <Input id="card-cvc" placeholder="123" />
                        </FormField>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function SearchFilterForm() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={Filter}
                    title="Filtros de busca"
                    description="Linha horizontal para listagens e tabelas."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 lg:grid-cols-[1fr_12rem_12rem_auto]">
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Buscar por nome ou e-mail"
                        />
                    </div>
                    <Select defaultValue="active">
                        <SelectTrigger
                            className="w-full"
                            placeholder="Status"
                        />
                        <SelectContent>
                            <SelectItem index={0} value="active">
                                Ativo
                            </SelectItem>
                            <SelectItem index={1} value="pending">
                                Pendente
                            </SelectItem>
                            <SelectItem index={2} value="archived">
                                Arquivado
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select defaultValue="owner">
                        <SelectTrigger className="w-full" placeholder="Papel" />
                        <SelectContent>
                            <SelectItem index={0} value="owner">
                                Responsável
                            </SelectItem>
                            <SelectItem index={1} value="manager">
                                Gestor
                            </SelectItem>
                            <SelectItem index={2} value="viewer">
                                Leitor
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="lg">
                        <Filter />
                        Filtrar
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function SplitAddressForm() {
    return (
        <Card>
            <CardHeader>
                <SectionHeader
                    icon={MapPin}
                    title="Endereço em duas partes"
                    description="Dois grupos visuais na mesma superfície de formulário."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-4">
                        <Badge variant="outline">Entrega</Badge>
                        <FormField id="shipping-name" label="Destinatário">
                            <Input
                                id="shipping-name"
                                placeholder="Joana Costa"
                            />
                        </FormField>
                        <FormField
                            id="shipping-address"
                            label="Endereço"
                            variant="overlap"
                        >
                            <Input
                                id="shipping-address"
                                placeholder="Rua do Mercado, 123"
                            />
                        </FormField>
                        <FormField id="shipping-city" label="Cidade">
                            <Input id="shipping-city" placeholder="São Paulo" />
                        </FormField>
                    </div>
                    <div className="space-y-4">
                        <Badge variant="outline">Cobrança</Badge>
                        <FormField id="billing-company" label="Empresa">
                            <Input
                                id="billing-company"
                                placeholder="Northwind LTDA"
                            />
                        </FormField>
                        <FormField
                            id="billing-document"
                            label="CNPJ"
                            variant="overlap"
                        >
                            <Input
                                id="billing-document"
                                placeholder="00.000.000/0001-00"
                            />
                        </FormField>
                        <FormField
                            id="billing-email"
                            label="Contato de cobrança"
                        >
                            <Input
                                id="billing-email"
                                type="email"
                                placeholder="fiscal@empresa.com.br"
                            />
                        </FormField>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function FormPlayground() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Componentes', href: components.url() },
            { title: 'Playground de formulários', href: '#' },
        ],
    });

    return (
        <>
            <Head title="Playground de formulários" />

            <Header
                title="Playground de formulários"
                subtitle="Bancada para estilos do FormField, formulários em card, em linha, filtros e estados de campo."
            >
                <div className="flex gap-2">
                    <Button type="button" variant="outline">
                        <FileText />
                        Rascunho
                    </Button>
                    <Button type="button">
                        <Send />
                        Enviar
                    </Button>
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <FieldStyleGallery />

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(22rem,0.75fr)]">
                        <div className="space-y-6">
                            <AccountFormCard />
                            <InlineSettingsForm />
                            <SearchFilterForm />
                        </div>

                        <div className="space-y-6">
                            <CompactProfileForm />
                            <PaymentForm />
                            <SplitAddressForm />
                        </div>
                    </div>

                    <div className="rounded-lg border border-border bg-muted p-4">
                        <div className="grid gap-3 md:grid-cols-[12rem_1fr_auto] md:items-center">
                            <div>
                                <p className="text-sm font-semibold text-foreground">
                                    Linha em formulário
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Exemplo de formulário solto em barra de
                                    ferramentas.
                                </p>
                            </div>
                            <Input
                                placeholder="Convidar colega por e-mail"
                                type="email"
                            />
                            <Button type="button" variant="secondary" size="lg">
                                <Mail />
                                Convidar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
