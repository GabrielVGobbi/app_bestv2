import { createInertiaApp } from '@inertiajs/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import TemporaryPasswordDialog from '@/components/temporary-password-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { NotificationBellProvider } from '@/contexts/notification-bell-context';
import { initializeTheme } from '@/hooks/use-appearance';
import AppDashboardLayout from '@/layouts/app-dashboard-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

if (import.meta.env.DEV) {
    void import('react-grab');
}

const appName = 'BestPlay';
const queryClient = new QueryClient();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'error':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppDashboardLayout, SettingsLayout];
            default:
                return AppDashboardLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <QueryClientProvider client={queryClient}>
                <TooltipProvider delayDuration={0}>
                    <NotificationBellProvider>
                        {app}
                        <TemporaryPasswordDialog />
                        <Toaster />
                    </NotificationBellProvider>
                </TooltipProvider>
                <ReactQueryDevtools initialIsOpen={false} />
            </QueryClientProvider>
        );
    },
    progress: {
        color: '#eb0459',
    },
}).then(() => {
    document.getElementById('boot-shell')?.remove();
    document.getElementById('boot-loading')?.remove();
});

// This will set light / dark mode on load...
initializeTheme();
