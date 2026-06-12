import * as React from 'react';

import { Input } from '@/components/ui/input';
import { maskPhone } from '@/lib/masks';

type InputPhoneProps = Omit<React.ComponentProps<'input'>, 'value' | 'defaultValue' | 'onChange' | 'type'> & {
    /** Valor controlado (string mascarada ou apenas digitos). */
    value?: string;
    /** Valor inicial para uso nao-controlado (ex.: dentro do <Form> do Inertia). */
    defaultValue?: string;
    /** Recebe sempre a string ja mascarada. */
    onChange?: (value: string) => void;
};

/**
 * Input de telefone brasileiro com mascara automatica.
 * - (99) 99999-9999 (celular) ou (99) 9999-9999 (fixo)
 * - Funciona controlado (value/onChange) ou nao-controlado (defaultValue + name),
 *   postando a string mascarada via atributo `name` no submit nativo.
 */
function InputPhone({ value, defaultValue, onChange, ...props }: InputPhoneProps) {
    const isControlled = value !== undefined;
    const [internal, setInternal] = React.useState<string>(() => maskPhone(defaultValue ?? ''));

    const displayValue = isControlled ? maskPhone(value) : internal;

    function handleChange(event: React.ChangeEvent<HTMLInputElement>): void {
        const masked = maskPhone(event.target.value);

        if (!isControlled) {
            setInternal(masked);
        }

        onChange?.(masked);
    }

    return (
        <Input
            type="tel"
            inputMode="numeric"
            autoComplete="tel"
            placeholder="(00) 00000-0000"
            value={displayValue}
            onChange={handleChange}
            {...props}
        />
    );
}

export { InputPhone };
