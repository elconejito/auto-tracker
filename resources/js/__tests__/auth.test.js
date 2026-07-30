import { createPinia } from 'pinia';

import apiClient from '../api/client';
import { useAuthStore } from '../stores/auth';

describe('auth store', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('initializes a JSON:API-like user resource', async () => {
        vi.spyOn(apiClient, 'get').mockResolvedValue({
            data: {
                data: {
                    attributes: { email: 'driver@example.com', name: 'Driver' },
                    id: 'user-1',
                    type: 'users',
                },
            },
        });

        const auth = useAuthStore(createPinia());

        await auth.initialize();

        expect(auth.isAuthenticated).toBe(true);
        expect(auth.user).toEqual({
            email: 'driver@example.com',
            id: 'user-1',
            name: 'Driver',
        });
    });

    it('keeps network failures distinct from an unauthenticated session', async () => {
        vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('offline'));

        const auth = useAuthStore(createPinia());

        await auth.initialize();

        expect(auth.status).toBe('error');
        expect(auth.isAuthenticated).toBe(false);
        expect(auth.initializationError).toEqual(expect.any(Error));
    });
});
