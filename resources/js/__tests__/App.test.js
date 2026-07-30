import { createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { createMemoryHistory } from 'vue-router';

import App from '../App.vue';
import { createAppRouter } from '../router';
import { useAuthStore } from '../stores/auth';

window.scrollTo = vi.fn();

describe('application shell', () => {
    it('renders the foundation route', async () => {
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

        expect(wrapper.get('h1').text()).toContain('Trustworthy vehicle records');
    });
});
