import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DashboardHeader } from '@/components/dashboard/dashboard-header';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';
import { NavModuleProvider } from '@/contexts/nav-module-context';
import { ZipDownloadPanel } from '@/modules/uploads';
import type { AppLayoutProps } from '@/types';

export default function AppDashboardLayout({
    children,
    breadcrumbs = [],
    headerActions,
}: AppLayoutProps) {
    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(() =>
        typeof window === 'undefined'
            ? false
            : window.localStorage.getItem('bestplay.sidebar.collapsed') ===
              'true',
    );
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);

    useEffect(() => {
        window.localStorage.setItem(
            'bestplay.sidebar.collapsed',
            String(isSidebarCollapsed),
        );
    }, [isSidebarCollapsed]);

    useEffect(() => {
        return router.on('navigate', () => {
            setIsMobileSidebarOpen(false);
        });
    }, []);

    const inner = (
        <div className="app-workspace flex overflow-hidden">
            <a
                href="#main-content"
                className="sr-only z-50 rounded-lg bg-foreground px-4 py-3 text-background focus:not-sr-only focus:absolute focus:top-3 focus:left-3"
            >
                Pular para o conteúdo
            </a>
            <DashboardSidebar
                isCollapsed={isSidebarCollapsed}
                onToggle={() => setIsSidebarCollapsed((prev) => !prev)}
                mobileOpen={isMobileSidebarOpen}
                onMobileClose={() => setIsMobileSidebarOpen(false)}
            />
            <div className="workspace-content flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <DashboardHeader
                    breadcrumbs={breadcrumbs}
                    headerActions={headerActions}
                    mobileOpen={isMobileSidebarOpen}
                    onMobileToggle={() =>
                        setIsMobileSidebarOpen((prev) => !prev)
                    }
                />
                <main
                    id="main-content"
                    tabIndex={-1}
                    className="custom-scrollbar min-h-0 min-w-0 flex-1 overflow-y-auto bg-background outline-none"
                    scroll-region=""
                >
                    {children}
                </main>
            </div>
        </div>
    );

    const dashboardWithZipPanel = (
        <>
            {inner}
            <ZipDownloadPanel />
        </>
    );

    return <NavModuleProvider>{dashboardWithZipPanel}</NavModuleProvider>;
}
