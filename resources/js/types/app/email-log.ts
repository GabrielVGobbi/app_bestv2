export type EmailStatus =
    | 'queued'
    | 'processing'
    | 'sent'
    | 'failed'
    | 'bounced'
    | 'delivered'
    | 'delivery_delayed'
    | 'complained'
    | 'opened'
    | 'clicked'
    | 'received'
    | 'scheduled'
    | 'suppressed';
export type EmailCategory = 'auth' | 'export' | 'notification' | 'system';

export interface EmailLog {
    uuid: string;
    to_email: string;
    to_name: string | null;
    subject: string;
    template: string;
    category: EmailCategory;
    status: EmailStatus;
    error_message: string | null;
    attempts: number;
    mailer: string | null;
    message_id: string | null;
    sent_at: string | null;
    failed_at: string | null;
    created_at: string;
}

export interface EmailSettings {
    enabled?: boolean;
    auth_enabled?: boolean;
    export_enabled?: boolean;
    notification_enabled?: boolean;
    system_enabled?: boolean;
}
