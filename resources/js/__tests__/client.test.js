import axios, { AxiosError } from 'axios';

import apiClient, {
    ensureCsrfToken,
    resetAuthFailureHandling,
    setAuthFailureHandler,
} from '../api/client';

function unauthorized(config) {
    return new AxiosError(
        'Unauthenticated',
        'ERR_BAD_REQUEST',
        config,
        null,
        {
            data: { errors: [{ code: 'unauthenticated' }] },
            status: 401,
            statusText: 'Unauthorized',
            headers: {},
            config,
        },
    );
}

function response(config, data = { data: { ok: true } }) {
    return {
        config,
        data,
        headers: {},
        status: 200,
        statusText: 'OK',
    };
}

describe('authenticated API client', () => {
    const originalAdapter = apiClient.defaults.adapter;

    afterEach(() => {
        apiClient.defaults.adapter = originalAdapter;
        setAuthFailureHandler(null);
        resetAuthFailureHandling();
        vi.restoreAllMocks();
    });

    it('refreshes once when concurrent requests receive 401 responses', async () => {
        const attempts = {};
        let initialFailures = 0;
        let refreshCalls = 0;

        apiClient.defaults.adapter = vi.fn(async (config) => {
            const path = config.url.split('?')[0];
            attempts[path] = (attempts[path] ?? 0) + 1;

            if (path === '/auth/refresh') {
                refreshCalls += 1;
                await new Promise((resolve) => setTimeout(resolve, 0));

                return response(config);
            }

            if (!config._authRetry && initialFailures < 2) {
                initialFailures += 1;

                return Promise.reject(unauthorized(config));
            }

            return response(config, { data: { path } });
        });

        const [first, second] = await Promise.all([
            apiClient.get('/vehicles'),
            apiClient.get('/vehicles'),
        ]);

        expect(refreshCalls).toBe(1);
        expect(attempts['/vehicles']).toBe(4);
        expect(first.data.data.path).toBe('/vehicles');
        expect(second.data.data.path).toBe('/vehicles');
    });

    it('bootstraps CSRF once for concurrent mutating requests', async () => {
        const calls = [];

        apiClient.defaults.adapter = vi.fn(async (config) => {
            calls.push(config.url);

            if (config.url === '/auth/csrf-cookie') {
                await new Promise((resolve) => setTimeout(resolve, 0));
            }

            return response(config, { data: { path: config.url } });
        });

        await Promise.all([
            apiClient.post('/vehicles', { nickname: 'Daily' }),
            apiClient.patch('/vehicles/vehicle-1', { nickname: 'Work' }),
            apiClient.delete('/fuel-entries/fuel-1'),
        ]);

        expect(calls.filter((path) => path === '/auth/csrf-cookie')).toHaveLength(1);
        expect(calls.filter((path) => path !== '/auth/csrf-cookie')).toEqual([
            '/vehicles',
            '/vehicles/vehicle-1',
            '/fuel-entries/fuel-1',
        ]);
    });

    it('does not bootstrap recursively for the CSRF endpoint or safe requests', async () => {
        const calls = [];

        apiClient.defaults.adapter = vi.fn(async (config) => {
            calls.push(config.url);

            return response(config);
        });

        await Promise.all([
            apiClient.get('/auth/csrf-cookie'),
            apiClient.get('/vehicles'),
        ]);

        expect(calls).toEqual(['/auth/csrf-cookie', '/vehicles']);
        await ensureCsrfToken();
        expect(calls).toEqual(['/auth/csrf-cookie', '/vehicles', '/auth/csrf-cookie']);
    });

    it('rejects all queued mutating requests when CSRF bootstrap fails', async () => {
        const csrfError = new AxiosError('CSRF bootstrap failed', 'ERR_NETWORK');
        let csrfCalls = 0;
        let mutationCalls = 0;

        apiClient.defaults.adapter = vi.fn(async (config) => {
            if (config.url === '/auth/csrf-cookie') {
                csrfCalls += 1;

                return Promise.reject(csrfError);
            }

            mutationCalls += 1;

            return response(config);
        });

        const results = await Promise.allSettled([
            apiClient.post('/vehicles', { nickname: 'Daily' }),
            apiClient.post('/vehicles/vehicle-1/archive'),
        ]);

        expect(results.every((result) => result.status === 'rejected')).toBe(true);
        expect(results.map((result) => result.reason)).toEqual([csrfError, csrfError]);
        expect(csrfCalls).toBe(1);
        expect(mutationCalls).toBe(0);
    });

    it('does not refresh excluded auth endpoints or network failures', async () => {
        const failureHandler = vi.fn();
        const refreshCalls = [];

        setAuthFailureHandler(failureHandler);
        apiClient.defaults.adapter = vi.fn(async (config) => {
            refreshCalls.push(config.url);

            if (config.url === '/auth/csrf-cookie') {
                return response(config);
            }

            if (config.url === '/auth/login') {
                return Promise.reject(unauthorized(config));
            }

            return Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));
        });

        await expect(apiClient.post('/auth/login')).rejects.toBeInstanceOf(AxiosError);
        await expect(apiClient.get('/vehicles')).rejects.toBeInstanceOf(AxiosError);

        expect(refreshCalls).toEqual(['/auth/csrf-cookie', '/auth/login', '/vehicles']);
        expect(failureHandler).not.toHaveBeenCalled();
    });

    it('clears auth once when a shared refresh fails', async () => {
        const failureHandler = vi.fn();

        setAuthFailureHandler(failureHandler);
        apiClient.defaults.adapter = vi.fn(async (config) => {
            if (config.url === '/auth/refresh') {
                return Promise.reject(unauthorized(config));
            }

            return Promise.reject(unauthorized(config));
        });

        await expect(Promise.allSettled([
            apiClient.get('/vehicles'),
            apiClient.get('/dashboard'),
        ])).resolves.toHaveLength(2);

        expect(failureHandler).toHaveBeenCalledTimes(1);
    });

    it('does not clear auth or redirect when refresh fails with a network error', async () => {
        const failureHandler = vi.fn();

        setAuthFailureHandler(failureHandler);
        apiClient.defaults.adapter = vi.fn(async (config) => {
            if (config.url === '/auth/csrf-cookie') {
                return response(config);
            }

            if (config.url === '/auth/refresh') {
                return Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));
            }

            return Promise.reject(unauthorized(config));
        });

        const result = await apiClient.get('/vehicles').catch((error) => error);

        expect(result).toBeInstanceOf(AxiosError);
        expect(result.code).toBe('ERR_NETWORK');
        expect(failureHandler).not.toHaveBeenCalled();
    });
});
