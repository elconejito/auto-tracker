import axios from 'axios';

const AUTH_ENDPOINTS = new Set([
    '/auth/csrf-cookie',
    '/auth/register',
    '/auth/login',
    '/auth/refresh',
    '/auth/logout',
    '/auth/forgot-password',
    '/auth/reset-password',
]);

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete']);
const CSRF_ENDPOINT = '/auth/csrf-cookie';

const apiClient = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

let refreshPromise = null;
let csrfPromise = null;
let authFailureHandler = null;
let authFailureHandled = false;

function endpointPath(config) {
    const url = config?.url ?? '';
    const path = url.split('?')[0].replace(/^https?:\/\/[^/]+/, '');

    return path.replace(/^\/api/, '') || '/';
}

function isAuthEndpoint(config) {
    return AUTH_ENDPOINTS.has(endpointPath(config));
}

function requiresCsrf(config) {
    return MUTATING_METHODS.has((config?.method ?? 'get').toLowerCase())
        && endpointPath(config) !== CSRF_ENDPOINT
        && config?._csrfBootstrap !== true;
}

async function performCsrfBootstrap() {
    return apiClient.get(CSRF_ENDPOINT, { _csrfBootstrap: true });
}

export function ensureCsrfToken() {
    if (!csrfPromise) {
        csrfPromise = performCsrfBootstrap().finally(() => {
            csrfPromise = null;
        });
    }

    return csrfPromise;
}

async function performRefresh() {
    return apiClient.post('/auth/refresh');
}

export function refreshAccessToken() {
    if (!refreshPromise) {
        refreshPromise = performRefresh().finally(() => {
            refreshPromise = null;
        });
    }

    return refreshPromise;
}

export function setAuthFailureHandler(handler) {
    authFailureHandler = handler;
}

export function resetAuthFailureHandling() {
    authFailureHandled = false;
}

async function notifyAuthFailure() {
    if (authFailureHandled) {
        return;
    }

    authFailureHandled = true;

    if (authFailureHandler) {
        try {
            await authFailureHandler();
        } catch {
            // The failed refresh remains the authoritative request error.
        }
    }
}

apiClient.interceptors.request.use(async (config) => {
    if (requiresCsrf(config)) {
        await ensureCsrfToken();
    }

    return config;
});

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const request = error.config;

        if (
            error.response?.status !== 401 ||
            !request ||
            request._authRetry === true ||
            isAuthEndpoint(request)
        ) {
            return Promise.reject(error);
        }

        try {
            await refreshAccessToken();
            request._authRetry = true;

            return apiClient(request);
        } catch (refreshError) {
            if ([401, 403].includes(refreshError.response?.status)) {
                await notifyAuthFailure();
            }

            return Promise.reject(refreshError);
        }
    },
);

export default apiClient;
