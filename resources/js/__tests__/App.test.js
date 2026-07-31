import { createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { createMemoryHistory } from 'vue-router';

import App from '../App.vue';
import { createAppRouter } from '../router';
import { useAuthStore } from '../stores/auth';

window.scrollTo = vi.fn();

describe('application shell', () => {
    it('renders the vehicle route without a false empty state while loading', async () => {
        const pinia = createPinia();
        const auth = useAuthStore(pinia);
        auth.status = 'authenticated';
        auth.user = { id: 'user-1', name: 'Test User' };
        const router = createAppRouter(createMemoryHistory(), pinia);

        await router.push('/');
        await router.isReady();

        const wrapper = mount(App, {
            global: {
                plugins: [pinia, router],
            },
        });

        expect(wrapper.get('h1').text()).toBe('Vehicles');
        expect(wrapper.text()).toContain('Loading vehicles');
        expect(wrapper.text()).not.toContain('Your garage is ready');
    });
});
