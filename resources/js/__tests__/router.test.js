import { AxiosError } from 'axios';
import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';

import apiClient, {
    resetAuthFailureHandling,
    setAuthFailureHandler,
} from '../api/client';
import { createAppRouter, intendedPath } from '../router';
import { useAuthStore } from '../stores/auth';

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

function response(config) {
    return {
        config,
        data: null,
        headers: {},
        status: 204,
        statusText: 'No Content',
    };
}

describe('application router authentication', () => {
    const originalAdapter = apiClient.defaults.adapter;

    afterEach(() => {
        apiClient.defaults.adapter = originalAdapter;
        setAuthFailureHandler(null);
        resetAuthFailureHandling();
        vi.restoreAllMocks();
    });

    it('redirects unauthenticated users to login with their intended route', async () => {
        vi.spyOn(apiClient, 'get').mockRejectedValue({ response: { status: 401 } });
        const router = createAppRouter(createMemoryHistory(), createPinia());

        await router.push('/');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/');
    });

    it('does not allow an external redirect value', () => {
        expect(intendedPath('//example.com')).toBe('/');
        expect(intendedPath('https://example.com')).toBe('/');
        expect(intendedPath('/vehicles/1?tab=history')).toBe('/vehicles/1?tab=history');
    });

    it('keeps unauthenticated users on the password reset route when refresh fails', async () => {
        apiClient.defaults.adapter = vi.fn(async (config) => {
            if (config.url === '/auth/csrf-cookie') {
                return response(config);
            }

            return Promise.reject(unauthorized(config));
        });

        const pinia = createPinia();
        const router = createAppRouter(createMemoryHistory(), pinia);
        const auth = useAuthStore(pinia);

        auth.bindAuthFailureHandler(router);

        await router.push('/reset-password?token=reset-token&email=driver%40example.test');
        await router.isReady();

        expect(router.currentRoute.value.name).toBe('reset-password');
        expect(router.currentRoute.value.query).toEqual({
            email: 'driver@example.test',
            token: 'reset-token',
        });
        expect(auth.status).toBe('guest');
    });
});
