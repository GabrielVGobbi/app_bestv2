import { createRoot } from 'react-dom/client';

import '../../../resources/css/app.css';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import UserFormFields from '@/pages/app/users/partials/user-form-fields';

const params = new URLSearchParams(location.search);
document.documentElement.classList.toggle(
    'dark',
    params.get('theme') === 'dark',
);

createRoot(document.getElementById('app')!).render(
    <Dialog defaultOpen>
        <DialogContent className="outline-hidden sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Novo usuário</DialogTitle>
                <DialogDescription>
                    Cadastre um usuário sem sair da lista.
                </DialogDescription>
            </DialogHeader>
            <form
                className="space-y-6"
                onSubmit={(event) => event.preventDefault()}
            >
                <div className="grid gap-4">
                    <Alert>
                        <AlertTitle>
                            Primeiro acesso com senha temporária
                        </AlertTitle>
                        <AlertDescription>
                            A conta será criada com uma senha temporária segura.
                            Copie a senha após o cadastro e compartilhe com o
                            usuário. A troca será obrigatória no primeiro
                            acesso.
                        </AlertDescription>
                    </Alert>
                    <UserFormFields
                        errors={
                            params.has('errors')
                                ? {
                                      name: 'Informe o nome.',
                                      email: 'Informe um e-mail válido.',
                                  }
                                : {}
                        }
                    />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline">
                        Cancelar
                    </Button>
                    <Button type="submit">Criar usuário</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>,
);
