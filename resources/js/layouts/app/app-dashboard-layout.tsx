import { usePage } from '@inertiajs/react';
import { DashboardHeader } from '@/components/dashboard/dashboard-header';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';
import { NavModuleProvider } from '@/contexts/nav-module-context';
import { ZipDownloadPanel } from '@/modules/uploads';
import type { AppLayoutProps } from '@/types';

export default function AppDashboardLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const { layout, nav } = usePage().props;
    const isSidebarLogoLayout = layout.style === 'sidebar-logo';

    // DashboardSidebar manages its own collapse state internally so toggling
    // doesn't trigger a re-render of the entire layout (header + children).
    const inner = (
        <div className="relative min-h-screen overflow-x-hidden bg-background">
            {isSidebarLogoLayout ? (
                <main className="fixed inset-0 flex overflow-hidden bg-background">
                    <DashboardSidebar showLogo />

                    <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                        <DashboardHeader
                            breadcrumbs={breadcrumbs}
                            logoVisibility="mobile"
                            variant="content"
                        />

                        <div
                            className="custom-scrollbar min-h-0 flex-1 overflow-y-auto"
                            scroll-region=""
                        >
                            {children}
                        </div>
                    </div>
                </main>
            ) : (
                <>
                    <DashboardHeader breadcrumbs={breadcrumbs} />

                    <main className="fixed inset-0 flex overflow-hidden bg-background pt-header">
                        <DashboardSidebar />

                        <div
                            className="custom-scrollbar min-h-0 flex-1 overflow-y-auto px-8 py-5"
                            scroll-region=""
                        >
                            {children}
                        </div>
                    </main>
                </>
            )}
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
