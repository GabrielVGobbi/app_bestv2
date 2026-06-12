import * as React from 'react';

import { Input } from '@/components/ui/input';
import { maskCnpj, maskCpf, maskCpfCnpj } from '@/lib/masks';

type DocumentKind = 'cpf' | 'cnpj' | 'auto';

type InputDocumentProps = Omit<React.ComponentProps<'input'>, 'value' | 'defaultValue' | 'onChange' | 'type'> & {
    /** Tipo de documento. `auto` decide entre CPF/CNPJ pela quantidade de digitos. */
    kind?: DocumentKind;
    /** Valor controlado (string mascarada ou apenas digitos). */
    value?: string;
    /** Valor inicial para uso nao-controlado (ex.: dentro do <Form> do Inertia). */
    defaultValue?: string;
    /** Recebe sempre a string ja mascarada. */
    onChange?: (value: string) => void;
};

function maskByKind(kind: DocumentKind, value: string): string {
    if (kind === 'cpf') {
        return maskCpf(value);
    }

    if (kind === 'cnpj') {
        return maskCnpj(value);
    }

    return maskCpfCnpj(value);
}

/**
 * Input de CPF/CNPJ com mascara automatica.
 * - CPF: 999.999.999-99 | CNPJ: 99.999.999/9999-99
 * - Funciona controlado (value/onChange) ou nao-controlado (defaultValue + name),
 *   postando a string mascarada via atributo `name` no submit nativo.
 */
function InputDocument({ kind = 'auto', value, defaultValue, onChange, ...props }: InputDocumentProps) {
    const isControlled = value !== undefined;
    const [internal, setInternal] = React.useState<string>(() => maskByKind(kind, defaultValue ?? ''));

    const displayValue = isControlled ? maskByKind(kind, value) : internal;

    function handleChange(event: React.ChangeEvent<HTMLInputElement>): void {
        const masked = maskByKind(kind, event.target.value);

        if (!isControlled) {
            setInternal(masked);
        }

        onChange?.(masked);
    }

    return (
        <Input
            type="text"
            inputMode="numeric"
            placeholder={kind === 'cnpj' ? '00.000.000/0000-00' : '000.000.000-00'}
            value={displayValue}
            onChange={handleChange}
            {...props}
        />
    );
}

export { InputDocument };
