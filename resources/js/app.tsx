import { createInertiaApp } from '@inertiajs/react';
import { PrivateStateLayout } from '@/contexts/private-state-context';
import { initializeTheme } from '@/hooks/use-appearance';
import AppDashboardLayout from '@/layouts/app-dashboard-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

if (import.meta.env.DEV) {
    void import('react-grab');
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'error':
                return [PrivateStateLayout];
            case name.startsWith('auth/'):
                return [PrivateStateLayout, AuthLayout];
            case name.startsWith('settings/'):
                return [PrivateStateLayout, AppDashboardLayout, SettingsLayout];
            default:
                return [PrivateStateLayout, AppDashboardLayout];
        }
    },
    strictMode: true,
    progress: {
        color: '#4B5563',
    },
}).then(() => {
    document.getElementById('boot-shell')?.remove();
    document.getElementById('boot-loading')?.remove();
});

// This will set light / dark mode on load...
initializeTheme();
