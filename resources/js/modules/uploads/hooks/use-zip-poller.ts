import { useQueries } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { usePrivateState } from '@/contexts/private-state-context';

import { httpGet } from '@/lib/http';
import type { ApiResponse } from '@/lib/http';

import { uploadsEndpoints } from '../api/endpoints';
import type { ZipRequestStatus } from '../api/types';
import { useZipDownloadStore } from '../stores/zip-download-store';

const POLL_INTERVAL_MS = 2_000;

/**
 * Polls active ZIP jobs until they reach 'ready' or 'failed'.
 * Mount this hook once globally (inside ZipDownloadPanel) so it
 * persists across Inertia page navigations.
 */
export function useZipPoller() {
    const { scope } = usePrivateState();
    const jobs = useZipDownloadStore((s) => s.jobs);
    const updateJob = useZipDownloadStore((s) => s.updateJob);

    const notifiedRef = useRef<Set<string>>(new Set());

    const activeJobs = jobs.filter(
        (j) => j.status === 'pending' || j.status === 'processing',
    );

    const results = useQueries({
        queries: activeJobs.map((job) => ({
            queryKey: ['zip-request-status', job.uuid] as const,
            queryFn: ({ signal }) =>
                httpGet<ApiResponse<ZipRequestStatus>>(
                    uploadsEndpoints.zipRequestStatus(job.uuid),
                    { signal },
                ),
            enabled: scope.canRead,
            refetchInterval: POLL_INTERVAL_MS,
            // Hidden tabs pause; focus refetches immediately, so the ready toast still lands on return.
            refetchIntervalInBackground: false,
            staleTime: 0,
        })),
    });

    useEffect(() => {
        if (!scope.isCurrent()) {
            return;
        }

        results.forEach((result, idx) => {
            const job = activeJobs[idx];

            if (!job || !result.data) {
                return;
            }

            const remote = result.data.data;

            if (remote.status === job.status) {
                return;
            }

            if (remote.status === 'ready') {
                updateJob(job.uuid, {
                    status: 'ready',
                    downloadUrl: remote.download_url,
                });

                if (!notifiedRef.current.has(job.uuid)) {
                    notifiedRef.current.add(job.uuid);
                    toast.success('ZIP pronto para download!', {
                        description: job.label,
                    });
                }
            } else if (remote.status === 'failed') {
                updateJob(job.uuid, {
                    status: 'failed',
                    errorMessage: remote.error_message,
                });

                if (!notifiedRef.current.has(job.uuid)) {
                    notifiedRef.current.add(job.uuid);
                    toast.error('Erro ao gerar ZIP', {
                        description: remote.error_message,
                    });
                }
            } else if (
                remote.status === 'processing' &&
                job.status === 'pending'
            ) {
                updateJob(job.uuid, { status: 'processing' });
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [results]);
}
