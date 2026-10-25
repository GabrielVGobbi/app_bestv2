import { useMutation } from '@tanstack/react-query';

import { toast } from 'sonner';
import { usePrivateState } from '@/contexts/private-state-context';
import {
    http,
    toApiError,
    getErrorMessage,
    canceledPrivateRequest,
} from '@/lib/http';

import { uploadsEndpoints } from '../api/endpoints';
import type { ModelId, ZipRequestStatus } from '../api/types';
import { useZipDownloadStore } from '../stores/zip-download-store';

type CreateZipVars = { label?: string } & (
    | { ids: number[]; all_matching?: never }
    | {
          ids?: never;
          all_matching: {
              parentable_type: string;
              parentable_id: ModelId;
              tag?: string | null;
              collection?: string | null;
              search?: string;
          };
      }
);

interface ZipRequestResponse {
    success: boolean;
    data: ZipRequestStatus;
}

export function useZipRequest() {
    const { scope } = usePrivateState();
    const addJob = useZipDownloadStore((s) => s.addJob);

    const mutation = useMutation({
        mutationFn: async (vars: CreateZipVars) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            const res = await http.post<ZipRequestResponse>(
                uploadsEndpoints.zipRequests(),
                vars,
                { signal: scope.signal },
            );

            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return res.data;
        },
        onSuccess: (response) => {
            if (!scope.isCurrent()) {
                return;
            }

            const { uuid, status, file_count, label } = response.data;

            addJob({
                uuid,
                label,
                status,
                fileCount: file_count,
                createdAt: Date.now(),
            });

            toast.info('Gerando ZIP…', {
                description: `${file_count} arquivo(s) sendo compactado(s).`,
            });
        },
        onError: (error) => {
            const apiError = toApiError(error);

            if (!scope.isCurrent() || apiError.type === 'canceled') {
                return;
            }

            toast.error('Erro ao iniciar download', {
                description: getErrorMessage(apiError),
            });
        },
    });

    return {
        createZip: mutation.mutate,
        createZipAsync: mutation.mutateAsync,
        isPending: mutation.isPending,
    };
}
