import { createContext, createElement, useContext, useState } from 'react';
import type { ReactNode } from 'react';
import { useStore } from 'zustand';
import { createStore } from 'zustand/vanilla';
import type { StoreApi } from 'zustand/vanilla';

import { usePrivateState } from '@/contexts/private-state-context';

export interface ZipJob {
    uuid: string;
    label: string;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    fileCount: number;
    downloadUrl?: string;
    errorMessage?: string;
    createdAt: number;
}

interface ZipDownloadStore {
    jobs: ZipJob[];
    addJob: (job: ZipJob) => void;
    updateJob: (uuid: string, patch: Partial<ZipJob>) => void;
    removeJob: (uuid: string) => void;
}

const ZipDownloadContext = createContext<StoreApi<ZipDownloadStore> | null>(
    null,
);

export function ZipDownloadProvider({ children }: { children: ReactNode }) {
    const { scope } = usePrivateState();
    const [store] = useState(() => {
        const ownedStore = createStore<ZipDownloadStore>((set) => ({
            jobs: [],
            addJob: (job) => {
                if (scope.isCurrent()) {
                    set((state) => ({ jobs: [...state.jobs, job] }));
                }
            },
            updateJob: (uuid, patch) => {
                if (scope.isCurrent()) {
                    set((state) => ({
                        jobs: state.jobs.map((job) =>
                            job.uuid === uuid ? { ...job, ...patch } : job,
                        ),
                    }));
                }
            },
            removeJob: (uuid) => {
                if (scope.isCurrent()) {
                    set((state) => ({
                        jobs: state.jobs.filter((job) => job.uuid !== uuid),
                    }));
                }
            },
        }));
        scope.onInvalidate(() => ownedStore.setState({ jobs: [] }));

        return ownedStore;
    });

    return createElement(
        ZipDownloadContext.Provider,
        { value: store },
        children,
    );
}

export function useZipDownloadStore<T>(
    selector: (state: ZipDownloadStore) => T,
): T {
    const store = useContext(ZipDownloadContext);

    if (!store) {
        throw new Error('ZIP downloads require ZipDownloadProvider.');
    }

    return useStore(store, selector);
}
