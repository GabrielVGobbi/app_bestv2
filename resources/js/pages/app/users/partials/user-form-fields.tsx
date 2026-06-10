import InputError from '@/components/input-error';
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types/user';

interface Props {
    user?: Pick<User, 'name' | 'email'>;
    errors: Record<string, string>;
}

export default function UserFormFields({ user, errors }: Props) {
    return (
        <>
            <div className="grid gap-2">
                <FormField label="Nome" required error={errors.name}>
                    <Input
                        id="name"
                        name="name"
                        defaultValue={user?.name ?? ''}
                        required
                        autoComplete="name"
                        placeholder="Full name"
                    />
                </FormField>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    defaultValue={user?.email ?? ''}
                    required
                    autoComplete="email"
                    placeholder="Email address"
                />
                <InputError message={errors.email} />
            </div>
        </>
    );
}
