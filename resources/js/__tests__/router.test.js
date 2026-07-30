import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';

import apiClient from '../api/client';
import { createAppRouter, intendedPath } from '../router';

describe('application router authentication', () => {
    afterEach(() => {
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
});
