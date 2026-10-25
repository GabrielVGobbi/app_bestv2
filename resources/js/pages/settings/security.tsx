import { Form, Head, usePage } from '@inertiajs/react';
import { ShieldCheck, Save, ShieldOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError, { errorProps } from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function Security({
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { auth } = usePage().props;
    const mustChangePassword = Boolean(auth.user.must_change_password);

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    return (
        <>
            <Head title="Segurança" />

            <div className="space-y-6">
                {mustChangePassword && (
                    <Alert>
                        <AlertTitle>Troca de senha obrigatória</AlertTitle>
                        <AlertDescription>
                            Você acessou com uma senha temporária. Defina uma
                            nova senha para continuar usando o sistema.
                        </AlertDescription>
                    </Alert>
                )}

                <Heading
                    variant="small"
                    title="Alterar senha"
                    description="Use uma senha longa e aleatória para manter a conta protegida"
                />

                <Form
                    {...SecurityController.update()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Senha atual
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    {...errorProps(
                                        'current_password',
                                        errors.current_password,
                                    )}
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder="Senha atual"
                                />

                                <InputError
                                    field="current_password"
                                    message={errors.current_password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Nova senha</Label>

                                <PasswordInput
                                    id="password"
                                    {...errorProps('password', errors.password)}
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder="Nova senha"
                                />

                                <InputError
                                    field="password"
                                    message={errors.password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar senha
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    {...errorProps(
                                        'password_confirmation',
                                        errors.password_confirmation,
                                    )}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder="Confirmar senha"
                                />

                                <InputError
                                    field="password_confirmation"
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    <Save
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Salvar senha
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {canManageTwoFactor && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Autenticação em duas etapas"
                        description="Gerencie a autenticação em duas etapas da conta"
                    />
                    {twoFactorEnabled ? (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                No login, será pedido um código aleatório gerado
                                pelo aplicativo autenticador do seu celular.
                            </p>

                            <div className="relative inline">
                                <Form {...disable()}>
                                    {({ processing }) => (
                                        <Button
                                            variant="outline"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <ShieldOff
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Desativar 2FA
                                        </Button>
                                    )}
                                </Form>
                            </div>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Com a autenticação em duas etapas ativa, o login
                                pede um código gerado pelo aplicativo
                                autenticador do seu celular.
                            </p>

                            <div>
                                {hasSetupData ? (
                                    <Button
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck />
                                        Continuar configuração
                                    </Button>
                                ) : (
                                    <Form
                                        {...enable()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <ShieldCheck
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Ativar 2FA
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={errors}
                    />
                </div>
            )}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Segurança',
            href: edit(),
        },
    ],
};
