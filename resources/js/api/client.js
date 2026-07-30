import axios from 'axios';

const AUTH_ENDPOINTS = new Set([
    '/auth/register',
    '/auth/login',
    '/auth/refresh',
    '/auth/logout',
    '/auth/forgot-password',
    '/auth/reset-password',
]);

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
            await notifyAuthFailure();

            return Promise.reject(refreshError);
        }
    },
);

export default apiClient;
