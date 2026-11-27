import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as maintenanceApi from '../api/maintenance';
import * as vehicleApi from '../api/vehicles';
import MaintenanceEventDetailView from '../views/MaintenanceEventDetailView.vue';
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
        // jsdom does not implement the native dialog methods.
        Object.defineProperties(HTMLDialogElement.prototype, {
            showModal: { configurable: true, value() { this.setAttribute('open', ''); } },
            close: { configurable: true, value() { this.removeAttribute('open'); } },
        });
        const deleteEvent = vi.spyOn(maintenanceApi, 'deleteMaintenanceEvent').mockResolvedValue(undefined);
        const router = routerFor(MaintenanceHistoryView, '/vehicles/vehicle-1/maintenance-events', [
            { path: '/vehicles/:vehicle/maintenance-events', component: MaintenanceHistoryView },
        ]);
        await router.isReady();
        const wrapper = mount(MaintenanceHistoryView, { attachTo: document.body, global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Oil change');
        expect(wrapper.text()).toContain('5W-30');
        expect(wrapper.text()).toContain('Alignment');
        const trigger = wrapper.get('button');
        await trigger.trigger('click');
        expect(wrapper.text()).toContain('Delete this maintenance visit?');
        expect(wrapper.get('dialog').attributes('open')).toBeDefined();
        expect(document.activeElement.textContent).toBe('Cancel');
        await wrapper.get('dialog').trigger('cancel');
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        await wrapper.get('button').trigger('click');
        await wrapper.get('[role="alertdialog"] button:last-child').trigger('click');
        await flushPromises();

        expect(deleteEvent).toHaveBeenCalledWith('event-1');
        expect(document.activeElement).toBe(wrapper.get('h1').element);
        wrapper.unmount();
        delete HTMLDialogElement.prototype.showModal;
        delete HTMLDialogElement.prototype.close;
    });

    it('keeps the original history page on inspection and corrects a stale page', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        const response = { meta: { pagination: { total_pages: 2, total: 16 } } };
        const fetchHistory = vi.spyOn(maintenanceApi, 'fetchMaintenanceEvents')
            .mockResolvedValueOnce({ data: [], ...response })
            .mockResolvedValue({ data: [{ id: 'older', attributes: {} }], ...response });
        const router = routerFor(MaintenanceHistoryView, '/vehicles/vehicle-1/maintenance-events?page=3', [
            { path: '/vehicles/:vehicle/maintenance-events', component: MaintenanceHistoryView },
        ]);
        await router.isReady();
        const wrapper = mount(MaintenanceHistoryView, { global: { plugins: [router] } });
        await flushPromises();
        expect(fetchHistory).toHaveBeenLastCalledWith('vehicle-1', { 'page[number]': 2 });
        expect(router.currentRoute.value.query.page).toBe('2');
        expect(wrapper.text()).toContain('16 visits');
        const link = new URL(wrapper.get('article h2 a').attributes('href'), 'http://localhost');
        expect(link.searchParams.get('returnTo')).toBe(router.currentRoute.value.fullPath);
    });

    it('shows all saved service details on an archived visit without edit actions', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({
            ...activeVehicle, attributes: { ...activeVehicle.attributes, archived_at: '2026-11-27' },
        });
        vi.spyOn(maintenanceApi, 'fetchMaintenanceEvent').mockResolvedValue({
            id: 'record', relationships: { vehicle: { data: { id: activeVehicle.id } } },
            attributes: { performed_at: '2026-11-26T00:00:00Z', services: [{
                type: 'oil_change', line_cost: '22.50', details: { quantity: '4.50', filter_part_number: 'ABC-12', filter_replaced: false },
            }] },
        });
        const router = routerFor(MaintenanceEventDetailView, '/maintenance-events/record', [
            { path: '/maintenance-events/:maintenanceEvent', component: MaintenanceEventDetailView },
        ]);
        await router.isReady();
        const wrapper = mount(MaintenanceEventDetailView, { global: { plugins: [router] } });
        await flushPromises();
        for (const text of ['Nov 26, 2026', '4.50', 'ABC-12', 'Filter replaced', 'No', '$22.50', 'Archived']) {
            expect(wrapper.text()).toContain(text);
        }
        expect(wrapper.text()).not.toContain('Edit visit');
    });

    it('uses the actual owner for standalone edits and shows a correctable chronology error', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(maintenanceApi, 'fetchMaintenanceEvent').mockResolvedValue({
            id: 'record', attributes: { performed_at: '2026-11-26' },
            relationships: { vehicle: { data: { id: activeVehicle.id } } },
        });
        const update = vi.spyOn(maintenanceApi, 'updateMaintenanceEvent')
            .mockRejectedValueOnce({ response: { data: { errors: [{ detail: 'Mileage decreases after this visit.' }] } } })
            .mockResolvedValue({});
        const router = routerFor(MaintenanceEventFormView, '/maintenance-events/record/edit', [
            { path: '/maintenance-events/:maintenanceEvent/edit', component: MaintenanceEventFormView },
            { path: '/vehicles/:vehicle/maintenance-events', component: MaintenanceHistoryView },
        ]);
        await router.isReady();
        const wrapper = mount(MaintenanceEventFormView, { global: { plugins: [router] } });
        await flushPromises();
        expect(wrapper.get('form a').attributes('href')).toBe('/vehicles/vehicle-1/maintenance-events');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(update).toHaveBeenCalledWith('record', expect.objectContaining({ odometer: null }));
        expect(wrapper.text()).toContain('Mileage decreases after this visit.');
        await wrapper.get('#odometer').setValue('0');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(update).toHaveBeenLastCalledWith('record', expect.objectContaining({ odometer: 0 }));
        expect(router.currentRoute.value.path).toBe('/vehicles/vehicle-1/maintenance-events');
    });
});
