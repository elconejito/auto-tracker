import axios, { AxiosError } from 'axios';

import apiClient, {
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

    it('does not refresh excluded auth endpoints or network failures', async () => {
        const failureHandler = vi.fn();
        const refreshCalls = [];

        setAuthFailureHandler(failureHandler);
        apiClient.defaults.adapter = vi.fn(async (config) => {
            refreshCalls.push(config.url);

            if (config.url === '/auth/login') {
                return Promise.reject(unauthorized(config));
            }

            return Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK', config));
        });

        await expect(apiClient.post('/auth/login')).rejects.toBeInstanceOf(AxiosError);
        await expect(apiClient.get('/vehicles')).rejects.toBeInstanceOf(AxiosError);

        expect(refreshCalls).toEqual(['/auth/login', '/vehicles']);
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
});
