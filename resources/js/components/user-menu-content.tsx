import { router } from '@inertiajs/react';
import { LogOut, PanelLeft, Settings } from 'lucide-react';
import {
    DropdownLabel,
    DropdownSeparator,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { UserInfo } from '@/components/user-info';
import { usePrivateState } from '@/contexts/private-state-context';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
    /** Menu da sidebar: inclui "Restaurar sidebar" (largura e recolhimento padrão). */
    onResetSidebar?: () => void;
};

/** Itens do menu da conta; renderizar dentro de um DropdownContent do Fluid. */
export function UserMenuContent({ user, onResetSidebar }: Props) {
    const cleanup = useMobileNavigation();
    const { endSession } = usePrivateState();

    const handleLogout = () => {
        endSession();
        cleanup();
        router.flushAll();
        router.visit(logout());
    };

    return (
        <>
            <DropdownLabel className="flex items-center gap-2 text-left text-foreground">
                <UserInfo user={user} showEmail={true} />
            </DropdownLabel>
            <DropdownSeparator />
            <MenuItem
                index={0}
                icon={Settings}
                label="Configurações"
                onSelect={() => {
                    cleanup();
                    router.visit(edit());
                }}
            />
            {onResetSidebar && (
                <MenuItem
                    index={1}
                    icon={PanelLeft}
                    label="Restaurar sidebar"
                    onSelect={onResetSidebar}
                />
            )}
            <DropdownSeparator />
            <MenuItem
                index={onResetSidebar ? 2 : 1}
                icon={LogOut}
                label="Sair"
                onSelect={handleLogout}
                data-test="logout-button"
            />
        </>
    );
}
