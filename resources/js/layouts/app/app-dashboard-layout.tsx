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
    const { layout, nav } = usePage().props;
    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);
    const isSidebarLogoLayout = layout.style === 'sidebar-logo';

    useEffect(() => {
        return router.on('navigate', () => {
            setIsMobileSidebarOpen(false);
        });
    }, []);

    const inner = isSidebarLogoLayout ? (
        <div className="app-workspace flex h-screen overflow-hidden">
            <DashboardSidebar
                isCollapsed={isSidebarCollapsed}
                onToggle={() => setIsSidebarCollapsed((prev) => !prev)}
                showLogo
                mobileOpen={isMobileSidebarOpen}
                onMobileClose={() => setIsMobileSidebarOpen(false)}
            />

            <div className="relative z-10 flex min-w-0 flex-1 flex-col overflow-hidden">
                <DashboardHeader
                    breadcrumbs={breadcrumbs}
                    headerActions={headerActions}
                    onMobileToggle={() =>
                        setIsMobileSidebarOpen((prev) => !prev)
                    }
                />

                <main className="custom-scrollbar min-h-0 flex-1 overflow-y-auto">
                    {children}
                </main>
            </div>
        </div>
    ) : (
        <div className="app-workspace relative min-h-screen overflow-x-hidden">
            <DashboardHeader
                breadcrumbs={breadcrumbs}
                headerActions={headerActions}
                showLogo
                onMobileToggle={() => setIsMobileSidebarOpen((prev) => !prev)}
            />

            <main className="fixed inset-0 z-10 flex overflow-hidden bg-background/80 pt-header backdrop-blur-[1px]">
                <DashboardSidebar
                    isCollapsed={isSidebarCollapsed}
                    onToggle={() => setIsSidebarCollapsed((prev) => !prev)}
                    mobileOpen={isMobileSidebarOpen}
                    onMobileClose={() => setIsMobileSidebarOpen(false)}
                />

                <div
                    className="custom-scrollbar min-h-0 flex-1 overflow-y-auto"
                    scroll-region=""
                >
                    {children}
                </div>
            </main>
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
