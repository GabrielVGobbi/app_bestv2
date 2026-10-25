import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import type { User } from '@/types/user';

interface Props {
    user?: Pick<User, 'name' | 'email'>;
    errors: Record<string, string>;
}

export default function UserFormFields({ user, errors }: Props) {
    return (
        <>
            <FormField label="Nome" required error={errors.name}>
                <Input
                    id="name"
                    name="name"
                    defaultValue={user?.name ?? ''}
                    required
                    autoComplete="name"
                    placeholder="Nome completo"
                />
            </FormField>

            <FormField label="E-mail" required error={errors.email}>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    defaultValue={user?.email ?? ''}
                    required
                    autoComplete="email"
                    placeholder="nome@empresa.com.br"
                />
            </FormField>
        </>
    );
}
