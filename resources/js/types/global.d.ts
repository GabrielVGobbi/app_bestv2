import type { Auth } from '@/types/auth';
import type { RawNavModule, RawNavItem } from '@/types/navigation';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            layout: {
                style: 'sidebar-logo' | 'header-logo';
            };
            nav: {
                style: 'modules' | 'flat';
                modulesMenuPosition: 'sidebar' | 'header';
                global: RawNavItem[];
                modules: RawNavModule[];
                footer: RawNavItem[];
            };
            [key: string]: unknown;
        };
    }
}

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        label?: string;
        align?: 'left' | 'center' | 'right';
        type?: 'text' | 'number' | 'date' | 'boolean' | 'actions';
        initialVisibility?: boolean;
        /** Truncate cell text with ellipsis. Works with the column `size` as max-width. */
        truncate?: boolean;
        /** Pin column to the edge of the scrollable table. */
        sticky?: 'right';
    }
}
