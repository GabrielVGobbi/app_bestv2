export interface ActivityLogCauser {
    id: number;
    uuid: string | null;
    name: string;
    email: string | null;
}

export interface ActivityLogSubject {
    id: number;
    name: string;
    email: string | null;
}

export interface ActivityActionDefinition {
    key: string;
    label: string;
    log_name: string;
    event: string;
    description: string;
    icon: string;
    severity: 'success' | 'info' | 'warning' | 'danger' | 'muted' | string;
    subject_type: string | null;
    enabled: boolean;
}

export interface ActivityLog {
    id: number;
    action_key: string | null;
    action: ActivityActionDefinition | null;
    log_name: string;
    event: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    subject: ActivityLogSubject | null;
    causer_type: string | null;
    causer_id: number | null;
    causer: ActivityLogCauser | null;
    properties: Record<string, unknown> | null;
    batch_uuid: string | null;
    created_at: string;
}
