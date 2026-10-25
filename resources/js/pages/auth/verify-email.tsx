import { Form, Head } from '@inertiajs/react';
import { Mail } from 'lucide-react';
// Components
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificar e-mail" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-success-soft-foreground">
                    Um novo link de verificação foi enviado para o e-mail
                    cadastrado.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Mail className="size-4" aria-hidden="true" />
                            )}
                            Reenviar e-mail de verificação
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Sair
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verificar e-mail',
    description: 'Confirme seu e-mail pelo link que acabamos de enviar.',
};
