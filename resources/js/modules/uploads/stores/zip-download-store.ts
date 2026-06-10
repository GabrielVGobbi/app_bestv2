import { create } from 'zustand'

export interface ZipJob {
    uuid: string
    label: string
    status: 'pending' | 'processing' | 'ready' | 'failed'
    fileCount: number
    downloadUrl?: string
    errorMessage?: string
    createdAt: number
}

interface ZipDownloadStore {
    jobs: ZipJob[]
    addJob: (job: ZipJob) => void
    updateJob: (uuid: string, patch: Partial<ZipJob>) => void
    removeJob: (uuid: string) => void
}

export const useZipDownloadStore = create<ZipDownloadStore>((set) => ({
    jobs: [],

    addJob: (job) =>
        set((state) => ({
            jobs: [...state.jobs, job],
        })),

    updateJob: (uuid, patch) =>
        set((state) => ({
            jobs: state.jobs.map((j) =>
                j.uuid === uuid ? { ...j, ...patch } : j,
            ),
        })),

    removeJob: (uuid) =>
        set((state) => ({
            jobs: state.jobs.filter((j) => j.uuid !== uuid),
        })),
}))
