import { createPinia } from 'pinia';
import { mount } from '@vue/test-utils';
import { createMemoryHistory } from 'vue-router';

import App from '../App.vue';
import { createAppRouter } from '../router';

window.scrollTo = vi.fn();

describe('application shell', () => {
    it('renders the foundation route', async () => {
        const router = createAppRouter(createMemoryHistory());

        await router.push('/');
        await router.isReady();

        const wrapper = mount(App, {
            global: {
                plugins: [createPinia(), router],
            },
        });

        expect(wrapper.get('h1').text()).toContain('Trustworthy vehicle records');
    });
});
