import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { DashboardHeader } from '@/components/dashboard/dashboard-header';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';
import { SidebarProvider, useSidebar } from '@/components/ui/fluid/sidebar';
import { NavModuleProvider } from '@/contexts/nav-module-context';
import {
    setSidebarCollapsed,
    setSidebarWidth,
    useSidebarPreferences,
} from '@/hooks/use-sidebar-preferences';
// Import direto: o barrel @/modules/uploads leva o módulo inteiro para o bundle inicial.
import { ZipDownloadPanel } from '@/modules/uploads/components/zip-download-panel';
import type { AppLayoutProps } from '@/types';

/** Salva a largura quando o arraste termina, não a cada movimento. */
function SidebarWidthPersistence() {
    const { width, isResizing } = useSidebar();

    useEffect(() => {
        if (!isResizing) {
            setSidebarWidth(width);
        }
    }, [width, isResizing]);

    return null;
}

export default function AppDashboardLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { nav, layout } = usePage().props;
    const { collapsed: isSidebarCollapsed, width: sidebarWidth } =
        useSidebarPreferences();
    const isSidebarLogo = layout.style === 'sidebar-logo';

    // Fluid SidebarProvider: recolher e largura ficam no localStorage
    // (persist={false} dispensa o cookie); recolhida, vira a faixa de 80px.
    const inner = (
        <SidebarProvider
            open={!isSidebarCollapsed}
            onOpenChange={(open) => setSidebarCollapsed(!open)}
            persist={false}
            width={sidebarWidth}
            collapsedWidth="5rem"
            className={
                isSidebarLogo
                    ? 'h-screen overflow-hidden bg-background'
                    : 'min-h-screen overflow-x-hidden bg-background'
            }
        >
            <SidebarWidthPersistence />
            {isSidebarLogo ? (
                /* ── sidebar-logo: logo fica no sidebar, header só cobre a coluna direita ── */
                <>
                    <DashboardSidebar showLogo />
                    <div className="relative z-10 flex min-h-0 min-w-0 flex-1 flex-col overflow-clip">
                        <DashboardHeader breadcrumbs={breadcrumbs} />
                        <main className="custom-scrollbar min-h-0 flex-1 overflow-y-auto">
                            {children}
                        </main>
                    </div>
                </>
            ) : (
                /* ── header-logo: logo fica no header full-width, sidebar só tem nav ── */
                <>
                    <DashboardHeader breadcrumbs={breadcrumbs} showLogo />
                    <main className="fixed inset-0 z-10 flex overflow-hidden bg-background/80 pt-header backdrop-blur-[1px]">
                        <DashboardSidebar className="h-full" />
                        <div className="custom-scrollbar flex-1 overflow-y-auto px-8 py-5">
                            {children}
                        </div>
                    </main>
                </>
            )}
        </SidebarProvider>
    );

    const needsModuleProvider =
        nav.style === 'modules' || nav.modulesMenuPosition === 'header';

    if (needsModuleProvider) {
        return (
            <NavModuleProvider>
                {inner}
                <ZipDownloadPanel />
            </NavModuleProvider>
        );
    }

    return (
        <>
            {inner}
            <ZipDownloadPanel />
        </>
    );
}
