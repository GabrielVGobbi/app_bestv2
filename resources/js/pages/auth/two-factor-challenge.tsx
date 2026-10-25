import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { ArrowRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError, { errorProps } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Código de recuperação',
                description:
                    'Confirme o acesso à sua conta com um dos seus códigos de recuperação.',
                toggleText: 'usar o código do autenticador',
            };
        }

        return {
            title: 'Autenticação em duas etapas',
            description: 'Digite o código do seu aplicativo autenticador',
            toggleText: 'usar um código de recuperação',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Autenticação em duas etapas" />

            <div className="space-y-6">
                <Form
                    {...store()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <>
                                    <Input
                                        name="recovery_code"
                                        {...errorProps(
                                            'recovery_code',
                                            errors.recovery_code,
                                        )}
                                        type="text"
                                        placeholder="Código de recuperação"
                                        autoFocus={showRecoveryInput}
                                        required
                                    />
                                    <InputError
                                        field="recovery_code"
                                        message={errors.recovery_code}
                                    />
                                </>
                            ) : (
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <div className="flex w-full items-center justify-center">
                                        <InputOTP
                                            name="code"
                                            {...errorProps('code', errors.code)}
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </div>
                                    <InputError
                                        field="code"
                                        message={errors.code}
                                    />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Continuar
                            </Button>

                            <div className="text-center text-sm text-muted-foreground">
                                <span>ou </span>
                                <button
                                    type="button"
                                    className="cursor-pointer text-foreground underline decoration-muted-foreground underline-offset-4 transition-colors hover:decoration-foreground"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                >
                                    {authConfigContent.toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
