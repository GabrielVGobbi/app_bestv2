import * as React from 'react'

import { cn } from '@/lib/utils'
import { Input } from './input'

interface InputMoneyProps extends Omit<React.ComponentProps<'input'>, 'type' | 'onChange' | 'value'> {
    /**
     * Preferir string decimal em reais (ex.: "8.50" ou "8,50").
     * Número puro é interpretado como reais, não centavos — evite passar centavos como number.
     * String só com dígitos (ex.: "850") é tratada como centavos.
     */
    value?: string | number
    onChange?: (value: string) => void
    allowNegative?: boolean
}

/**
 * Input de dinheiro no padrão brasileiro (R$)
 * - Digitação começa pelos centavos (como caixa de supermercado)
 * - Formato: R$ 1.234,56
 * - Exemplo: digitar "1234" resulta em "R$ 12,34"
 * - Valores vindos da API em centavos: use `(cents / 100).toFixed(2)` antes de passar em `value`
 */
function InputMoney({
    className,
    value,
    onChange,
    allowNegative = false,
    disabled,
    onKeyDown: externalOnKeyDown,
    ...props
}: InputMoneyProps) {
    const inputRef = React.useRef<HTMLInputElement>(null)
    const isSelectingAll = React.useRef(false)

    // Converte valor string/number para centavos (número inteiro)
    const parseValueToCents = (val: string | number | undefined): number => {
        if (val === undefined || val === null || val === '') return 0

        const strValue = String(val)

        // Verifica se tem ponto/vírgula ANTES de limpar (indica formato decimal)
        const hasDecimal = strValue.includes('.') || strValue.includes(',')

        // Remove tudo exceto números e sinal negativo
        const cleanValue = strValue.replace(/[^\d-]/g, '')

        if (cleanValue === '' || cleanValue === '-') return 0

        // Se tinha ponto/vírgula decimal, converte de reais para centavos
        if (hasDecimal) {
            // Reconstrói o número decimal
            const normalized = strValue.replace(',', '.')
            const num = parseFloat(normalized)
            if (isNaN(num)) return 0
            return Math.round(num * 100)
        }

        // Se não tinha decimal mas tem muitos dígitos, pode ser um número em centavos já
        // Caso contrário, se for um número "limpo" tipo 100, pode ser reais
        // Para segurança: se veio de um número puro (sem ponto) E tem mais de 2 dígitos,
        // assumimos que já está em centavos (legado ou edição direta)
        const numValue = parseInt(cleanValue, 10)

        // Se o número original tinha exatamente 2 casas decimais implícitas (ex: 100 → 100.00)
        // e veio de um tipo number, multiplica por 100
        if (typeof val === 'number' && val > 0 && val < 1000000) {
            // Número puro vindo do JavaScript (ex: 100) → trata como reais
            return Math.round(val * 100)
        }

        // String de números puros (ex: "10000") → trata como centavos
        return numValue
    }

    // Converte centavos para string formatada em reais (R$ 1.234,56)
    const formatCentsToDisplay = (cents: number): string => {
        const isNegative = cents < 0
        const absCents = Math.abs(cents)

        // Converte centavos para reais
        const reais = (absCents / 100).toFixed(2)

        // Separa parte inteira e decimal
        const [integerPart, decimalPart] = reais.split('.')

        // Adiciona separadores de milhares
        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

        // Retorna formato brasileiro
        const formatted = `${formattedInteger},${decimalPart}`
        return isNegative ? `-R$ ${formatted}` : `R$ ${formatted}`
    }

    // Converte centavos para valor decimal string (para enviar ao backend)
    const formatCentsToValue = (cents: number): string => {
        return (cents / 100).toFixed(2)
    }

    const [displayValue, setDisplayValue] = React.useState('')
    const [internalCents, setInternalCents] = React.useState(0)

    // Sincroniza valor externo com estado interno
    React.useEffect(() => {
        const cents = parseValueToCents(value)
        setInternalCents(cents)
        setDisplayValue(formatCentsToDisplay(cents))
    }, [value])

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        const allowedKeys = [
            'Backspace',
            'Delete',
            'Tab',
            'Escape',
            'Enter',
            'ArrowLeft',
            'ArrowRight',
            'Home',
            'End',
        ]

        // Permite Ctrl/Cmd + A, C, V, X
        if (e.ctrlKey || e.metaKey) {
            if (e.key.toLowerCase() === 'a') {
                isSelectingAll.current = true
                return
            }
            if (['c', 'v', 'x'].includes(e.key.toLowerCase())) {
                return
            }
        }

        const wasSelectingAll = isSelectingAll.current
        isSelectingAll.current = false

        // Permite sinal negativo se configurado
        if (allowNegative && e.key === '-' && internalCents >= 0) {
            e.preventDefault()
            const newCents = -internalCents
            setInternalCents(newCents)
            setDisplayValue(formatCentsToDisplay(newCents))
            onChange?.(formatCentsToValue(newCents))
            return
        }

        // Permite teclas de navegação
        if (allowedKeys.includes(e.key)) {
            if (e.key === 'Backspace') {
                e.preventDefault()
                const newCents = wasSelectingAll ? 0 : Math.floor(internalCents / 10)
                setInternalCents(newCents)
                setDisplayValue(formatCentsToDisplay(newCents))
                onChange?.(formatCentsToValue(newCents))
            }
            externalOnKeyDown?.(e)
            return
        }

        // Apenas permite números
        if (!/^\d$/.test(e.key)) {
            e.preventDefault()
            return
        }

        e.preventDefault()

        // Se havia seleção total (Ctrl+A), descarta o valor anterior e começa do dígito digitado
        const baseCents = wasSelectingAll ? 0 : internalCents
        const digit = parseInt(e.key, 10)
        const newCents = baseCents * 10 + digit

        // Limita a 999999999 (R$ 9.999.999,99)
        if (newCents > 999999999) {
            return
        }

        setInternalCents(newCents)
        setDisplayValue(formatCentsToDisplay(newCents))
        onChange?.(formatCentsToValue(newCents))
    }

    const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
        e.preventDefault()
        isSelectingAll.current = false
        const pastedText = e.clipboardData.getData('text')

        // Extrai apenas números do texto colado
        const numbersOnly = pastedText.replace(/[^\d]/g, '')

        if (numbersOnly === '') return

        const pastedCents = parseInt(numbersOnly, 10)

        if (pastedCents > 999999999) return

        setInternalCents(pastedCents)
        setDisplayValue(formatCentsToDisplay(pastedCents))
        onChange?.(formatCentsToValue(pastedCents))
    }

    const handleFocus = (e: React.FocusEvent<HTMLInputElement>) => {
        isSelectingAll.current = false
        e.target.select()
        props.onFocus?.(e)
    }

    return (
        <Input
            ref={inputRef}
            type="text"
            inputMode="numeric"
            data-slot="input"
            className={cn('tabular-nums', className)}
            value={displayValue}
            onChange={() => {}}
            onKeyDown={handleKeyDown}
            onPaste={handlePaste}
            onFocus={handleFocus}
            disabled={disabled}
            {...props}
        />
    )
}

export { InputMoney }
