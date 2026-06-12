/**
 * Mascaras de formatacao para inputs no padrao brasileiro.
 *
 * Regras do projeto:
 * - Telefone, CPF e CNPJ SEMPRE usam mascara (via componentes InputPhone / InputDocument).
 * - Datas SEMPRE usam o componente DatePicker (Calendar + Popover).
 * - Campos monetarios SEMPRE usam o componente InputMoney.
 *
 * Estas funcoes sao puras: recebem um valor cru e retornam a string mascarada.
 * Use `onlyDigits` para enviar ao backend apenas os digitos quando necessario.
 */

/** Remove tudo que nao for digito. */
export function onlyDigits(value: string | null | undefined): string {
    return (value ?? '').replace(/\D/g, '');
}

/**
 * Telefone brasileiro.
 * - 10 digitos: (99) 9999-9999 (fixo)
 * - 11 digitos: (99) 99999-9999 (celular)
 */
export function maskPhone(value: string | null | undefined): string {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length === 0) {
        return '';
    }

    if (digits.length <= 2) {
        return `(${digits}`;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

/** CPF: 999.999.999-99 */
export function maskCpf(value: string | null | undefined): string {
    const digits = onlyDigits(value).slice(0, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
}

/** CNPJ: 99.999.999/9999-99 */
export function maskCnpj(value: string | null | undefined): string {
    const digits = onlyDigits(value).slice(0, 14);

    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4')
        .replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
}

/**
 * CPF ou CNPJ automaticamente, decidindo pela quantidade de digitos.
 * Ate 11 digitos -> CPF, acima -> CNPJ.
 */
export function maskCpfCnpj(value: string | null | undefined): string {
    const digits = onlyDigits(value);

    return digits.length <= 11 ? maskCpf(digits) : maskCnpj(digits);
}

/** CEP: 99999-999 */
export function maskCep(value: string | null | undefined): string {
    const digits = onlyDigits(value).slice(0, 8);

    if (digits.length <= 5) {
        return digits;
    }

    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}
