import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import type { Client } from '@/types/app/client';

interface Props {
    client?: Partial<Client>;
    errors: Record<string, string>;
}

export default function ClientFormFields({ client, errors }: Props) {
    const record = client;

    return (
        <div className="grid gap-4">
            <div className="grid gap-2 space-y-1">
                <FormField
                    id="field-name"
                    label="Name"
                    variant="stacked"
                    required
                    error={errors.name}
                >
                    <Input
                        id="field-name"
                        name="name"
                        type="text"
                        defaultValue={record?.name ?? ''}
                    required
                        aria-invalid={!!errors.name}
                    />
                </FormField>

                <FormField
                    id="field-email"
                    label="Email"
                    variant="stacked"
                    error={errors.email}
                >
                    <Input
                        id="field-email"
                        name="email"
                        type="text"
                        defaultValue={record?.email ?? ''}
                        aria-invalid={!!errors.email}
                    />
                </FormField>
            </div>
        </div>
    );
}
