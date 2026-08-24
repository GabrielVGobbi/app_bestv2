export type ExportStatus = 'pending' | 'processing' | 'completed' | 'failed';
export type ExportFormat = 'csv' | 'xlsx' | 'tsv';

export interface ExportRecord {
    uuid: string;
    status: ExportStatus;
    format: ExportFormat;
    model_class?: string;
    file_name: string | null;
    file_size: number | null;
    total_records: number | null;
    processed_records: number;
    error_message?: string | null;
    download_url?: string | null;
    expires_at: string | null;
    created_at: string;
    completed_at: string | null;
}

export interface ExportColumnOption {
    key: string;
    label: string;
}

export interface DataTableExportConfig {
    modelClass: string;
    columnsEndpoint: string;
    enabled?: boolean;
    fixedFilters?: Record<string, unknown>;
}

/** Shape stored in localStorage */
export interface StoredExport {
    uuid: string;
    status: ExportStatus;
    modelClass: string;
    format: ExportFormat;
    file_name: string | null;
    download_url: string | null;
    total_records: number | null;
    processed_records: number;
    expires_at: string | null;
    /** Unix timestamp in ms */
    createdAt: number;
    /** Set when user clicks download — hides from "recentes" indicator */
    downloadedAt?: number;
}
