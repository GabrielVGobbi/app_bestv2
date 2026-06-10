import { useMutation } from '@tanstack/react-query'

import { http, toApiError, getErrorMessage } from '@/lib/http'
import { toast } from 'sonner'

import { uploadsEndpoints } from '../api/endpoints'
import type { ZipRequestStatus } from '../api/types'
import { useZipDownloadStore } from '../stores/zip-download-store'

interface CreateZipVars {
    ids: number[]
    label?: string
}

interface ZipRequestResponse {
    success: boolean
    data: ZipRequestStatus
}

export function useZipRequest() {
    const addJob = useZipDownloadStore((s) => s.addJob)

    const mutation = useMutation({
        mutationFn: async (vars: CreateZipVars) => {
            const res = await http.post<ZipRequestResponse>(
                uploadsEndpoints.zipRequests(),
                { ids: vars.ids, label: vars.label },
            )
            return res.data
        },
        onSuccess: (response, vars) => {
            const { uuid, status, file_count, label } = response.data

            addJob({
                uuid,
                label,
                status,
                fileCount: file_count,
                createdAt: Date.now(),
            })

            toast.info('Gerando ZIP…', { description: `${vars.ids.length} arquivo(s) sendo compactado(s).` })
        },
        onError: (error) => {
            const apiError = toApiError(error)
            toast.error('Erro ao iniciar download', { description: getErrorMessage(apiError) })
        },
    })

    return {
        createZip: mutation.mutate,
        createZipAsync: mutation.mutateAsync,
        isPending: mutation.isPending,
    }
}
