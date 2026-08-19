import { router, usePage } from '@inertiajs/react';
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
    const { nav } = usePage().props;
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
        <div className="app-workspace flex h-screen flex-col overflow-hidden">
            <DashboardHeader
                breadcrumbs={breadcrumbs}
                headerActions={headerActions}
                showLogo
                onMobileToggle={() => setIsMobileSidebarOpen((prev) => !prev)}
            />

            <div className="flex min-h-0 flex-1 overflow-hidden">
                <DashboardSidebar
                    isCollapsed={isSidebarCollapsed}
                    onToggle={() => setIsSidebarCollapsed((prev) => !prev)}
                    mobileOpen={isMobileSidebarOpen}
                    onMobileClose={() => setIsMobileSidebarOpen(false)}
                />

                <main
                    className="custom-scrollbar min-h-0 min-w-0 flex-1 overflow-y-auto"
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

    if (nav.style === 'modules') {
        return <NavModuleProvider>{dashboardWithZipPanel}</NavModuleProvider>;
    }

    return dashboardWithZipPanel;
}
