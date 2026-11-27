import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as vehicleApi from '../api/vehicles';
import VehicleFormView from '../views/VehicleFormView.vue';
import VehiclesView from '../views/VehiclesView.vue';

function routerFor(component, path = '/') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component },
            { path: '/vehicles/:vehicle', component: VehiclesView },
            { path: '/vehicles/new', component: VehicleFormView },
            { path: '/vehicles/:vehicle/edit', component: VehicleFormView },
        ],
    });
    router.push(path);

    return router;
}

describe('vehicle experience', () => {
    afterEach(() => vi.restoreAllMocks());

    it('renders populated vehicle cards and unavailable future summaries honestly', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({
            data: [{
                id: '01VEHICLE',
                attributes: {
                    nickname: 'Daily',
                    year: 2022,
                    make: 'Honda',
                    model: 'Civic',
                    trim: null,
                    current_odometer: 24500,
                    archived_at: null,
                },
            }],
        });
        const router = routerFor(VehiclesView);
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Daily');
        expect(wrapper.text()).toContain('24,500 mi');
        expect(wrapper.text()).toContain('Unavailable');
    });

    it('shows an accessible empty state after loading completes', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({ data: [] });
        const router = routerFor(VehiclesView);
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Your garage is ready');
    });

    it('opens an archived vehicle from the saved garage page and links back to the filter', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({
            data: [{ id: 'v16', attributes: { nickname: 'Old Civic', archived_at: '2026-07-01' } }],
            meta: { pagination: { current_page: 2, total_pages: 2, total: 16 } },
        });
        const router = routerFor(VehiclesView, '/?status=archived&page=2');
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });
        await flushPromises();

        expect(vehicleApi.fetchVehicles).toHaveBeenCalledWith(expect.objectContaining({ 'filter[archived]': 'only', 'page[number]': 2 }));
        expect(wrapper.text()).toContain('Page 2 of 2');
        expect(wrapper.text()).toContain('16 vehicles');
        const target = router.resolve(wrapper.get('h2 a').attributes('href'));
        expect(target.path).toBe('/vehicles/v16');
        expect(target.query.garage).toBe('/?status=archived&page=2');
        expect(wrapper.text()).not.toContain('Add fuel');
    });

    it('clamps an empty last page without navigating back after the user leaves', async () => {
        let resolve;
        vi.spyOn(vehicleApi, 'fetchVehicles').mockImplementationOnce(() => new Promise((done) => { resolve = done; }))
            .mockResolvedValue({ data: [{ id: 'v1', attributes: { nickname: 'Daily' } }], meta: { pagination: { total_pages: 1, total: 1 } } });
        const router = routerFor(VehiclesView, '/?page=2');
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });
        wrapper.unmount();
        await router.push('/vehicles/new');
        resolve({ data: [], meta: { pagination: { total_pages: 1, total: 1 } } });
        await flushPromises();
        expect(router.currentRoute.value.path).toBe('/vehicles/new');

        await router.push('/?page=2');
        mount(VehiclesView, { global: { plugins: [router] } });
        await flushPromises();
        expect(router.currentRoute.value.fullPath).toBe('/');
    });

    it('tells an all-archived garage where to find its vehicles', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValueOnce({ data: [] }).mockResolvedValueOnce({ data: [{ id: 'archived' }] });
        const router = routerFor(VehiclesView);
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });
        await flushPromises();

        expect(wrapper.text()).toContain('No active vehicles');
        expect(wrapper.text()).not.toContain('Add your first vehicle');
    });

    it('keeps missing mileage distinct from zero and retains the originating garage view', async () => {
        vi.spyOn(vehicleApi, 'createVehicle').mockResolvedValue({ id: 'vehicle' });
        const router = routerFor(VehicleFormView, '/vehicles/new?returnTo=%2F%3Fstatus%3Dall');
        await router.isReady();
        const wrapper = mount(VehicleFormView, { global: { plugins: [router] } });

        expect(wrapper.get('a').attributes('href')).toBe('/?status=all');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(vehicleApi.createVehicle).toHaveBeenCalledWith(expect.objectContaining({ current_odometer: null }));
        expect(router.currentRoute.value.path).toBe('/vehicles/vehicle');
        expect(router.currentRoute.value.query.garage).toBe('/?status=all');

        await router.push('/vehicles/new');
        await wrapper.get('#current_odometer').setValue('0');
        await wrapper.get('form').trigger('submit');
        expect(vehicleApi.createVehicle).toHaveBeenLastCalledWith(expect.objectContaining({ current_odometer: 0 }));
    });

    it('explains how to restore an archived vehicle opened by its edit URL', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ attributes: { nickname: 'Daily', archived_at: '2026-07-01' } });
        const router = routerFor(VehicleFormView, '/vehicles/vehicle/edit');
        await router.isReady();
        const wrapper = mount(VehicleFormView, { global: { plugins: [router] } });
        await flushPromises();

        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('Restore this vehicle');
        expect(wrapper.get('a[href="/?status=archived"]').text()).toBe('View archived vehicles');
    });

    it('stays on the vehicle overview when a pending edit finishes after navigation', async () => {
        let finish;
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ attributes: { nickname: 'Daily' } });
        vi.spyOn(vehicleApi, 'updateVehicle').mockReturnValue(new Promise((resolve) => { finish = resolve; }));
        const router = routerFor(VehicleFormView, '/vehicles/daily/edit');
        await router.isReady();
        const wrapper = mount(VehicleFormView, { global: { plugins: [router] } });
        await flushPromises();

        await wrapper.get('form').trigger('submit');
        await wrapper.get('a[href="/vehicles/daily"]').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.path).toBe('/vehicles/daily');
        finish({});
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/vehicles/daily');
    });

    it('opens a newly created vehicle directly', async () => {
        vi.spyOn(vehicleApi, 'createVehicle').mockResolvedValue({ id: '01VEHICLE' });
        const router = routerFor(VehicleFormView, '/vehicles/new');
        await router.isReady();
        const wrapper = mount(VehicleFormView, { global: { plugins: [router] } });

        await wrapper.get('#nickname').setValue('Daily');
        await wrapper.get('#make').setValue('Honda');
        await wrapper.get('#model').setValue('Civic');
        await wrapper.get('#current_odometer').setValue('24500');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(vehicleApi.createVehicle).toHaveBeenCalledWith(expect.objectContaining({
            nickname: 'Daily',
            current_odometer: 24500,
        }));
        expect(router.currentRoute.value.path).toBe('/vehicles/01VEHICLE');
    });
});
