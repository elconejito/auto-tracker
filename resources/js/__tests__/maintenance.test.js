import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as maintenanceApi from '../api/maintenance';
import * as vehicleApi from '../api/vehicles';
import MaintenanceEventFormView from '../views/MaintenanceEventFormView.vue';
import MaintenanceHistoryView from '../views/MaintenanceHistoryView.vue';
import MaintenanceForm from '../components/maintenance/MaintenanceForm.vue';

function routerFor(component, path, routes = []) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/', component }, ...routes],
    });

    router.push(path);

    return router;
}

const activeVehicle = { id: 'vehicle-1', attributes: { nickname: 'Daily', archived_at: null } };

describe('maintenance experience', () => {
    afterEach(() => vi.restoreAllMocks());

    it('renders dynamic fields and submits an event with several services', async () => {
        const wrapper = mount(MaintenanceForm, {
            props: {
                cancelTo: '/',
                initialValue: {
                    performed_at: '2026-07-30',
                    odometer: 24500,
                    services: [
                        { type: 'oil_change', line_cost: '54.00', details: { viscosity: '5W-30' } },
                        { type: 'generic_service', details: { name: 'Alignment', description: 'Four-wheel alignment' } },
                    ],
                },
                submitLabel: 'Save visit',
            },
            global: { stubs: { RouterLink: true } },
        });

        expect(wrapper.get('#service-0-viscosity').element.value).toBe('5W-30');
        expect(wrapper.get('#service-1-name').element.value).toBe('Alignment');

        await wrapper.get('#service-0-type').setValue('tire_replacement');
        expect(wrapper.find('#service-0-position').exists()).toBe(true);
        expect(wrapper.find('#service-0-viscosity').exists()).toBe(false);
        await wrapper.get('#service-0-position').setValue('front');
        await wrapper.get('#service-0-manufacturer').setValue('Michelin');
        await wrapper.get('form').trigger('submit');

        const payload = wrapper.emitted('submit')[0][0];
        expect(payload.services).toHaveLength(2);
        expect(payload.services[0]).toEqual(expect.objectContaining({
            type: 'tire_replacement',
            line_cost: '54.00',
            details: expect.objectContaining({ position: 'front', manufacturer: 'Michelin' }),
        }));
        expect(payload.services[1]).toEqual(expect.objectContaining({
            type: 'generic_service',
            details: expect.objectContaining({ name: 'Alignment' }),
        }));
    });

    it('submits a new maintenance visit through the vehicle route', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        const createEvent = vi.spyOn(maintenanceApi, 'createMaintenanceEvent').mockResolvedValue({ id: 'event-1' });
        const router = routerFor(MaintenanceEventFormView, '/vehicles/vehicle-1/maintenance-events/new', [
            { path: '/vehicles/:vehicle/maintenance-events/new', component: MaintenanceEventFormView },
            { path: '/vehicles/:vehicle/maintenance-events', component: MaintenanceHistoryView, name: 'maintenance-events.index' },
        ]);
        await router.isReady();
        const wrapper = mount(MaintenanceEventFormView, { global: { plugins: [router] } });

        await flushPromises();
        await wrapper.get('#performed_at').setValue('2026-07-30');
        await wrapper.get('#odometer').setValue('24500');
        await wrapper.get('#service-0-viscosity').setValue('0W-20');
        await wrapper.get('#service-0-type').setValue('oil_change');
        await wrapper.get('button').trigger('click');
        await wrapper.get('#service-1-type').setValue('generic_service');
        await wrapper.get('#service-1-name').setValue('Alignment');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(createEvent).toHaveBeenCalledWith('vehicle-1', expect.objectContaining({
            performed_at: '2026-07-30',
            odometer: 24500,
            services: expect.arrayContaining([
                expect.objectContaining({ type: 'oil_change' }),
                expect.objectContaining({ type: 'generic_service', details: expect.objectContaining({ name: 'Alignment' }) }),
            ]),
        }));
    });

    it('renders chronological service summaries and confirms deletion', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(maintenanceApi, 'fetchMaintenanceEvents').mockResolvedValue({
            data: [{
                id: 'event-1',
                attributes: {
                    performed_at: '2026-07-30',
                    odometer: 24500,
                    total_cost: '100.00',
                    services: [
                        { type: 'oil_change', line_cost: '60.00', details: { viscosity: '5W-30' } },
                        { type: 'generic_service', details: { name: 'Alignment' } },
                    ],
                },
            }],
        });
        const deleteEvent = vi.spyOn(maintenanceApi, 'deleteMaintenanceEvent').mockResolvedValue(undefined);
        const router = routerFor(MaintenanceHistoryView, '/vehicles/vehicle-1/maintenance-events');
        await router.isReady();
        const wrapper = mount(MaintenanceHistoryView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Oil change');
        expect(wrapper.text()).toContain('5W-30');
        expect(wrapper.text()).toContain('Alignment');
        await wrapper.get('button').trigger('click');
        expect(wrapper.text()).toContain('Delete this maintenance visit?');
        await wrapper.get('[role="alertdialog"] button:last-child').trigger('click');
        await flushPromises();

        expect(deleteEvent).toHaveBeenCalledWith('event-1');
    });
});
