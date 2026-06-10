import AppDashboardLayoutTemplate from '@/layouts/app/app-dashboard-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppDashboardLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppDashboardLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppDashboardLayoutTemplate>
    );
}
