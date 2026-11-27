import { createPinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import { createMemoryHistory } from 'vue-router';

import apiClient from '../api/client';
import App from '../App.vue';
import { createAppRouter } from '../router';
import { useAuthStore } from '../stores/auth';

window.scrollTo = vi.fn();

describe('application shell', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('retries a failed session check at the original protected route', async () => {
        const pinia = createPinia();
        vi.spyOn(apiClient, 'get')
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce({ data: { name: 'Driver' } });
        const router = createAppRouter(createMemoryHistory(), pinia);
        await router.push('/vehicles/new');
        const wrapper = mount(App, { global: { plugins: [pinia, router] } });

        expect(wrapper.text()).toContain("We couldn't check your session");
        expect(wrapper.find('form').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/vehicles/new');
        expect(wrapper.get('h1').text()).toMatch(/add a vehicle/i);
        wrapper.unmount();
    });

    it('sends a guest to sign in after retrying a protected deep link', async () => {
        const pinia = createPinia();
        vi.spyOn(apiClient, 'get').mockRejectedValueOnce(new Error('offline')).mockRejectedValue({ response: { status: 401 } });
        const router = createAppRouter(createMemoryHistory(), pinia);
        await router.push('/vehicles/new');
        const wrapper = mount(App, { global: { plugins: [pinia, router] } });
        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/vehicles/new');
        wrapper.unmount();
    });

    it('keeps the requested destination across registration and password recovery', async () => {
        const pinia = createPinia();
        const auth = useAuthStore(pinia);
        auth.clearAuth();
        const destination = '/vehicles/1/fuel-entries?page=3';
        const router = createAppRouter(createMemoryHistory(), pinia);
        await router.push({ name: 'login', query: { redirect: destination } });
        const wrapper = mount(App, { attachTo: document.body, global: { plugins: [pinia, router] } });
        await wrapper.get('a[href^="/register"]').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.query.redirect).toBe(destination);
        expect(document.title).toContain('Create account');
        expect(document.activeElement).toBe(wrapper.get('h1').element);
        await wrapper.get('a[href^="/login"]').trigger('click');
        await flushPromises();
        await wrapper.get('a[href^="/forgot-password"]').trigger('click');
        await flushPromises();
        const requestReset = vi.spyOn(auth, 'requestPasswordReset').mockResolvedValue({});
        await wrapper.get('#email').setValue('driver@example.test');
        await wrapper.get('form').trigger('submit');
        expect(requestReset).toHaveBeenCalledWith('driver@example.test', destination);
        await router.push({ name: 'reset-password', query: { redirect: destination } });
        await flushPromises();
        expect(wrapper.text()).toContain('reset link is incomplete');
        expect(wrapper.find('form').exists()).toBe(false);
        await router.push({ name: 'reset-password', query: { token: 'test-token', redirect: destination } });
        await flushPromises();
        vi.spyOn(auth, 'resetPassword').mockResolvedValue({});
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({ reset: 'success', redirect: destination });
        wrapper.unmount();
    });

    it.each([false, true])('focuses a delayed heading unless the user moved focus (%s)', async (moveFocus) => {
        const pinia = createPinia();
        useAuthStore(pinia).clearAuth();
        const router = createAppRouter(createMemoryHistory(), pinia);
        const ready = ref(false);
        router.addRoute({
            path: '/loading-page',
            meta: { title: 'Vehicle overview' },
            component: defineComponent({
                setup: () => () => h('section', [
                    h('button', 'View history'),
                    ready.value ? h('h1', 'Daily driver') : h('p', 'Loading…'),
                ]),
            }),
        });

        await router.push('/login');
        const wrapper = mount(App, { attachTo: document.body, global: { plugins: [pinia, router] } });
        await router.push('/loading-page');
        await flushPromises();
        expect(document.activeElement).toBe(wrapper.get('main').element);
        if (moveFocus) {
            wrapper.get('main button').element.focus();
        }

        ready.value = true;
        await flushPromises();
        expect(document.activeElement).toBe(wrapper.get(moveFocus ? 'main button' : 'h1').element);
        wrapper.unmount();
    });

    it('shows the signed-in identity and reports an unsuccessful sign-out before retry', async () => {
        const pinia = createPinia();
        const auth = useAuthStore(pinia);
        auth.status = 'authenticated';
        auth.user = { name: 'Driver' };
        vi.spyOn(apiClient, 'post').mockRejectedValueOnce(new Error('offline')).mockResolvedValue({});
        const router = createAppRouter(createMemoryHistory(), pinia);
        await router.push('/vehicles/new');
        const wrapper = mount(App, { global: { plugins: [pinia, router] } });

        expect(wrapper.get('header').text()).toContain('Driver');
        await wrapper.get('header button').trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('sign you out');
        expect(auth.isAuthenticated).toBe(true);
        await wrapper.get('header button').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('login');
        expect(auth.isAuthenticated).toBe(false);
        wrapper.unmount();
    });

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
