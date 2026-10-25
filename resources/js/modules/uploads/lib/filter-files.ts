export interface UploadRejection {
    file: File;
    reason: string;
}

export interface FilterFilesOptions {
    maxSizeMb?: number;
    maxFiles?: number;
}

export interface FilterFilesResult {
    accepted: File[];
    rejected: UploadRejection[];
}

export function partitionFiles(
    incoming: FileList | File[],
    options: FilterFilesOptions = {},
): FilterFilesResult {
    const { maxSizeMb = 50, maxFiles } = options;
    const arr = Array.from(incoming);
    const maxBytes = maxSizeMb * 1024 * 1024;

    const accepted: File[] = [];
    const rejected: UploadRejection[] = [];

    for (const file of arr) {
        if (file.size > maxBytes) {
            rejected.push({
                file,
                reason: `Arquivo acima do limite (${maxSizeMb}MB).`,
            });
            continue;
        }

        accepted.push(file);
    }

    if (maxFiles !== undefined && accepted.length > maxFiles) {
        const extra = accepted.splice(maxFiles);

        for (const file of extra) {
            rejected.push({
                file,
                reason: `Limite de ${maxFiles} arquivos por envio atingido.`,
            });
        }
    }

    return { accepted, rejected };
}

export function filterFiles(
    incoming: FileList | File[],
    options: FilterFilesOptions = {},
): File[] {
    return partitionFiles(incoming, options).accepted;
}
