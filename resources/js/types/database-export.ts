export type DatabaseExportStatus =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed';
export type DatabaseExportType = 'schema' | 'data' | 'full';

export interface DatabaseExportRecord {
    uuid: string;
    status: DatabaseExportStatus;
    export_type: DatabaseExportType;
    tables: string[] | null;
    file_name: string | null;
    file_size: number | null;
    error_message?: string | null;
    download_url?: string | null;
    expires_at: string | null;
    created_at: string;
    completed_at: string | null;
}

/** Shape stored in localStorage */
export interface StoredDatabaseExport {
    uuid: string;
    status: DatabaseExportStatus;
    export_type: DatabaseExportType;
    file_name: string | null;
    download_url: string | null;
    expires_at: string | null;
    /** Unix timestamp in ms */
    createdAt: number;
    /** Set when user clicks download */
    downloadedAt?: number;
}
