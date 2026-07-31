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
            { path: '/vehicles/new', component: VehicleFormView },
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

    it('submits a new vehicle and returns to the garage', async () => {
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
        expect(router.currentRoute.value.path).toBe('/');
    });
});
