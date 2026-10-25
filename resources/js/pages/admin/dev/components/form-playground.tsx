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
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { components } from '@/actions/App/Http/Controllers/Admin/DevToolsController';
import Heading from '@/components/heading';
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
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const fieldVariants = [
    {
        title: 'Default stacked',
        description: 'Label above, control below, helper text under it.',
        variant: 'stacked' as const,
    },
    {
        title: 'Inline',
        description: 'Side label for dense admin settings.',
        variant: 'inline' as const,
    },
    {
        title: 'Input with overlapping label',
        description: 'Label floats on top of the input border.',
        variant: 'overlap' as const,
    },
    {
        title: 'Input with inset label',
        description: 'Label sits inside the field frame.',
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
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-background">
                <Icon className="size-4 text-primary" />
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

function NativeSelect({ id, children }: { id: string; children: ReactNode }) {
    return (
        <select
            id={id}
            name={id}
            className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-[border-color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15"
        >
            {children}
        </select>
    );
}

function FieldStyleGallery() {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle>FormField styles</CardTitle>
                        <CardDescription>
                            Shared field variations stacked one below another.
                        </CardDescription>
                    </div>
                    <Badge variant="outline">shared component</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="space-y-5">
                        {fieldVariants.map((item) => (
                            <div
                                key={item.title}
                                className="rounded-lg border bg-muted/20 p-4"
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
                                    label="Company email"
                                    description="Use an address that receives system notices."
                                    variant={item.variant}
                                    required
                                >
                                    <Input
                                        id={`field-${item.variant}`}
                                        type="email"
                                        placeholder="finance@example.com"
                                    />
                                </FormField>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-lg border bg-background p-4">
                        <h3 className="text-sm font-semibold text-foreground">
                            Field states
                        </h3>
                        <div className="mt-4 space-y-4">
                            <FormField
                                id="field-error"
                                label="Document number"
                                error="Enter a valid document."
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
                                description="Ready to submit."
                                variant="inset"
                            >
                                <Input
                                    id="field-success"
                                    defaultValue="Verified"
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
                    title="Account form"
                    description="Classic card form with grouped fields and footer actions."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-5 md:grid-cols-2">
                    <FormField id="account-name" label="Account name" required>
                        <Input
                            id="account-name"
                            placeholder="Northwind Finance"
                        />
                    </FormField>
                    <FormField id="account-owner" label="Owner">
                        <Input id="account-owner" placeholder="Jane Cooper" />
                    </FormField>
                    <FormField
                        id="account-email"
                        label="Billing email"
                        variant="overlap"
                        required
                    >
                        <Input
                            id="account-email"
                            type="email"
                            placeholder="billing@example.com"
                        />
                    </FormField>
                    <FormField
                        id="account-plan"
                        label="Plan"
                        description="Controls limits and permissions."
                    >
                        <NativeSelect id="account-plan">
                            <option>Startup</option>
                            <option>Business</option>
                            <option>Enterprise</option>
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="account-notes"
                        label="Internal notes"
                        className="md:col-span-2"
                    >
                        <textarea
                            id="account-notes"
                            name="account-notes"
                            rows={4}
                            placeholder="Commercial details, contracts, and support notes."
                            className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/15"
                        />
                    </FormField>
                </div>
            </CardContent>
            <CardFooter className="justify-end gap-2 border-t pt-5">
                <Button type="button" variant="outline">
                    Cancel
                </Button>
                <Button type="button">
                    <Save />
                    Save account
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
                    title="Inline settings"
                    description="Left aligned labels for admin configuration screens."
                />
            </CardHeader>
            <CardContent>
                <div className="space-y-5">
                    <FormField
                        id="settings-slug"
                        label="Slug"
                        variant="inline"
                        description="Used in public URLs and integrations."
                    >
                        <Input id="settings-slug" defaultValue="northwind" />
                    </FormField>
                    <FormField
                        id="settings-timezone"
                        label="Timezone"
                        variant="inline"
                    >
                        <NativeSelect id="settings-timezone">
                            <option>America/Sao_Paulo</option>
                            <option>America/New_York</option>
                            <option>Europe/Lisbon</option>
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="settings-domain"
                        label="Domain"
                        variant="inline"
                        error="The domain must point to this environment."
                    >
                        <Input
                            id="settings-domain"
                            aria-invalid
                            defaultValue="app.example.com"
                        />
                    </FormField>
                    <div className="grid gap-2 sm:grid-cols-[10rem_1fr] sm:items-start">
                        <Label className="pt-2 text-sm font-semibold">
                            Access
                        </Label>
                        <div className="grid gap-3 rounded-lg border bg-muted/20 p-4">
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox defaultChecked />
                                <span>
                                    <span className="block font-medium text-foreground">
                                        Require 2FA
                                    </span>
                                    <span className="text-muted-foreground">
                                        Ask all members for two factor
                                        authentication.
                                    </span>
                                </span>
                            </label>
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox />
                                <span>
                                    <span className="block font-medium text-foreground">
                                        Restrict exports
                                    </span>
                                    <span className="text-muted-foreground">
                                        Require explicit permission to download
                                        reports.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="justify-end border-t pt-5">
                <Button type="button" variant="secondary">
                    Apply settings
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
                    title="Compact profile"
                    description="Smaller card with inset fields, avatar block, and quick actions."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-5 sm:grid-cols-[4rem_1fr]">
                    <div className="flex size-16 items-center justify-center rounded-lg border bg-muted text-lg font-semibold">
                        JC
                    </div>
                    <div className="grid gap-4">
                        <FormField
                            id="profile-name"
                            label="Display name"
                            variant="inset"
                        >
                            <Input
                                id="profile-name"
                                defaultValue="Jane Cooper"
                            />
                        </FormField>
                        <FormField
                            id="profile-email"
                            label="Email"
                            variant="inset"
                        >
                            <Input
                                id="profile-email"
                                type="email"
                                defaultValue="jane@example.com"
                            />
                        </FormField>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="justify-between gap-3 border-t pt-5">
                <Badge variant="secondary">
                    <ShieldCheck />
                    Verified
                </Badge>
                <Button type="button" size="sm">
                    <Check />
                    Update
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
                    title="Payment details"
                    description="Overlapping labels, icon prefix, and responsive rows."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-4">
                    <FormField
                        id="card-name"
                        label="Name on card"
                        variant="overlap"
                    >
                        <Input id="card-name" placeholder="Jane Cooper" />
                    </FormField>
                    <FormField
                        id="card-number"
                        label="Card number"
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
                            label="Expiration"
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
                    title="Search filters"
                    description="Horizontal row for list pages and data tables."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 lg:grid-cols-[1fr_12rem_12rem_auto]">
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Search by name or email"
                        />
                    </div>
                    <Select defaultValue="active">
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select defaultValue="owner">
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="owner">Owner</SelectItem>
                            <SelectItem value="manager">Manager</SelectItem>
                            <SelectItem value="viewer">Viewer</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline">
                        <Filter />
                        Filter
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
                    title="Split address"
                    description="Two visual groups inside the same form surface."
                />
            </CardHeader>
            <CardContent>
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-4">
                        <Badge variant="outline">Shipping</Badge>
                        <FormField id="shipping-name" label="Recipient">
                            <Input
                                id="shipping-name"
                                placeholder="Jane Cooper"
                            />
                        </FormField>
                        <FormField
                            id="shipping-address"
                            label="Address"
                            variant="overlap"
                        >
                            <Input
                                id="shipping-address"
                                placeholder="123 Market Street"
                            />
                        </FormField>
                        <FormField id="shipping-city" label="City">
                            <Input id="shipping-city" placeholder="Sao Paulo" />
                        </FormField>
                    </div>
                    <div className="space-y-4">
                        <Badge variant="outline">Billing</Badge>
                        <FormField id="billing-company" label="Company">
                            <Input
                                id="billing-company"
                                placeholder="Northwind LTDA"
                            />
                        </FormField>
                        <FormField
                            id="billing-document"
                            label="Tax document"
                            variant="overlap"
                        >
                            <Input
                                id="billing-document"
                                placeholder="00.000.000/0001-00"
                            />
                        </FormField>
                        <FormField id="billing-email" label="Billing contact">
                            <Input
                                id="billing-email"
                                type="email"
                                placeholder="fiscal@example.com"
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
            { title: 'Components', href: components.url() },
            { title: 'Form playground', href: '#' },
        ],
    });

    return (
        <>
            <Head title="Form playground" />

            <div className="page-wrapper-lg">
                <div className="page-section">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading
                            title="Form playground"
                            description="Workbench for FormField styles, card forms, inline forms, filters, and field states."
                        />
                        <div className="flex gap-2">
                            <Button type="button" variant="outline">
                                <FileText />
                                Draft
                            </Button>
                            <Button type="button">
                                <Send />
                                Submit
                            </Button>
                        </div>
                    </div>

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

                    <div className="rounded-lg border bg-muted/20 p-4">
                        <div className="grid gap-3 md:grid-cols-[12rem_1fr_auto] md:items-center">
                            <div>
                                <p className="text-sm font-semibold text-foreground">
                                    Inline row
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Standalone toolbar form example.
                                </p>
                            </div>
                            <Input
                                placeholder="Invite teammate by email"
                                type="email"
                            />
                            <Button type="button" variant="secondary">
                                <Mail />
                                Invite
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
