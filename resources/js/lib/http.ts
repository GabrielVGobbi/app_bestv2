import axios from 'axios';
import type { AxiosRequestConfig, InternalAxiosRequestConfig } from 'axios';

export type {
    Paginated as PaginatedResponse,
    PaginationLinks,
    PaginationMeta,
} from '@/types/pagination';

export type ValidationErrors<TFields extends string = string> = Partial<
    Record<TFields, string>
>;

export type ApiError<TFields extends string = string> =
    | {
          type: 'validation';
          status: 422;
          message: string;
          errors: ValidationErrors<TFields>;
      }
    | { type: 'auth'; status: 401 | 419; message: string }
    | { type: 'forbidden'; status: 403; message: string }
    | { type: 'not_found'; status: 404; message: string }
    | { type: 'canceled'; status: 0; message: string }
    | { type: 'network'; status: 0; message: string }
    | { type: 'server'; status: number; message: string };

export interface ApiResponse<T> {
    data: T;
    message?: string;
}

export type HttpConfig = Omit<AxiosRequestConfig, 'url' | 'method' | 'data'>;

export interface ListParams {
    page?: number;
    per_page?: number;
    search?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    [key: string]: unknown;
}

type LaravelErrorResponse = {
    message?: string;
    errors?: Record<string, string | string[]>;
};

function normalizeLaravelErrors<TFields extends string>(
    errors: unknown,
): ValidationErrors<TFields> {
    if (!errors || typeof errors !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(errors as Record<string, unknown>).map(
            ([key, value]) => [
                key,
                Array.isArray(value) ? String(value[0] ?? '') : String(value),
            ],
        ),
    ) as ValidationErrors<TFields>;
}

function firstErrorMessage<TFields extends string>(
    errors: ValidationErrors<TFields>,
): string | undefined {
    return Object.values(errors).find(
        (message): message is string =>
            typeof message === 'string' && message.length > 0,
    );
}

export function isApiError(error: unknown): error is ApiError {
    return (
        typeof error === 'object' &&
        error !== null &&
        'type' in error &&
        'status' in error
    );
}

export function toApiError<TFields extends string = string>(
    error: unknown,
): ApiError<TFields> {
    if (isApiError(error)) {
        return error as ApiError<TFields>;
    }

    if (!axios.isAxiosError<LaravelErrorResponse>(error)) {
        return {
            type: 'server',
            status: 500,
            message:
                error instanceof Error
                    ? error.message
                    : 'Erro inesperado. Tente novamente.',
        };
    }

    if (
        axios.isCancel(error) ||
        error.code === 'ERR_CANCELED' ||
        error.name === 'CanceledError'
    ) {
        return {
            type: 'canceled',
            status: 0,
            message: 'Requisicao cancelada.',
        };
    }

    if (!error.response) {
        return {
            type: 'network',
            status: 0,
            message: 'Erro de conexao. Verifique sua internet.',
        };
    }

    const { status, data } = error.response;

    if (status === 422) {
        const errors = normalizeLaravelErrors<TFields>(data?.errors);

        return {
            type: 'validation',
            status: 422,
            message:
                data?.message ||
                firstErrorMessage(errors) ||
                'Erro de validacao.',
            errors,
        };
    }

    if (status === 401 || status === 419) {
        return {
            type: 'auth',
            status,
            message:
                status === 419
                    ? 'Sessao expirada. Atualize a pagina e tente novamente.'
                    : 'Voce precisa estar logado para continuar.',
        };
    }

    if (status === 403) {
        return {
            type: 'forbidden',
            status: 403,
            message:
                data?.message ||
                'Voce nao tem permissao para realizar esta acao.',
        };
    }

    if (status === 404) {
        return {
            type: 'not_found',
            status: 404,
            message: data?.message || 'Recurso nao encontrado.',
        };
    }

    return {
        type: 'server',
        status,
        message: data?.message || 'Erro inesperado. Tente novamente.',
    };
}

export function getErrorMessage(error: ApiError): string {
    if (error.type === 'validation') {
        return firstErrorMessage(error.errors) || error.message;
    }

    return error.message;
}

export const http = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

interface PrivateHttpScope {
    signal: AbortSignal;
    isCurrent: () => boolean;
}

let privateScope: PrivateHttpScope | null = null;
const scopedRequests = new WeakMap<
    InternalAxiosRequestConfig,
    { scope: PrivateHttpScope; cleanup: () => void }
>();

export function bindPrivateHttpScope(scope: PrivateHttpScope): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    privateScope = scope;

    return () => {
        if (privateScope === scope) {
            privateScope = null;
        }
    };
}

export function canceledPrivateRequest(): ApiError {
    return { type: 'canceled', status: 0, message: 'Requisicao cancelada.' };
}

http.interceptors.request.use(
    (config) => {
        const scope = privateScope;

        if (!scope) {
            return config;
        }

        if (!scope.isCurrent()) {
            throw canceledPrivateRequest();
        }

        const controller = new AbortController();
        const caller = config.signal;
        const abort = () => controller.abort();
        scope.signal.addEventListener('abort', abort, { once: true });
        caller?.addEventListener?.('abort', abort, { once: true });

        if (scope.signal.aborted || caller?.aborted) {
            abort();
        }

        config.signal = controller.signal;
        scopedRequests.set(config, {
            scope,
            cleanup: () => {
                scope.signal.removeEventListener('abort', abort);
                caller?.removeEventListener?.('abort', abort);
            },
        });

        return config;
    },
    (error: unknown) => {
        throw error;
    },
    { synchronous: true },
);

function finishPrivateRequest(config?: InternalAxiosRequestConfig): boolean {
    const captured = config && scopedRequests.get(config);

    if (!captured) {
        return true;
    }

    captured.cleanup();
    scopedRequests.delete(config!);

    return captured.scope.isCurrent() && !config?.signal?.aborted;
}

http.interceptors.response.use(
    (response) => {
        if (!finishPrivateRequest(response.config)) {
            return Promise.reject(canceledPrivateRequest());
        }

        return response;
    },
    (error: unknown) => {
        const config = axios.isAxiosError(error) ? error.config : undefined;

        return Promise.reject(
            config && !finishPrivateRequest(config)
                ? canceledPrivateRequest()
                : toApiError(error),
        );
    },
);

export function createAbortController(): AbortController {
    return new AbortController();
}

export async function httpGet<T>(url: string, config?: HttpConfig): Promise<T> {
    const response = await http.get<T>(url, config);

    return response.data;
}

export async function httpPost<T, D = unknown>(
    url: string,
    data?: D,
    config?: HttpConfig,
): Promise<T> {
    const response = await http.post<T>(url, data, config);

    return response.data;
}

export async function httpPut<T, D = unknown>(
    url: string,
    data?: D,
    config?: HttpConfig,
): Promise<T> {
    const response = await http.put<T>(url, data, config);

    return response.data;
}

export async function httpPatch<T, D = unknown>(
    url: string,
    data?: D,
    config?: HttpConfig,
): Promise<T> {
    const response = await http.patch<T>(url, data, config);

    return response.data;
}

export async function httpDelete<T>(
    url: string,
    config?: HttpConfig,
): Promise<T> {
    const response = await http.delete<T>(url, config);

    return response.data;
}
