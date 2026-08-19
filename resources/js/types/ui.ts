import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    headerActions?: ReactNode;
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type CommandItem = {
    id: string;
    group: 'Navegação' | 'Ações';
    label: string;
    description?: string;
    keywords: string[];
    href: string;
    icon?: LucideIcon | null;
};

export type OperationalDashboard = {
    generated_at: string;
    sales?: {
        active_campaigns: number;
        awaiting_quotes: number;
        month_amount?: number;
    };
    playlists?: {
        pending_deliveries: number;
        active_slots: number;
    };
    finance?: {
        overdue_receivables: number;
        overdue_payables: number;
        pending_reconciliations: number;
    };
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
