import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';

import App from '../App.vue';
import * as dashboardApi from '../api/dashboard';
import * as fuelApi from '../api/fuel';
import * as maintenanceApi from '../api/maintenance';
import * as vehicleApi from '../api/vehicles';
import { createAppRouter } from '../router';
import { useAuthStore } from '../stores/auth';

const vehicle = { id: 'car', attributes: { nickname: 'Family wagon', year: 2021, make: 'Subaru', model: 'Outback', current_odometer: 45000 } };
const relationship = { vehicle: { data: { type: 'vehicles', id: vehicle.id } } };
const pagination = { current_page: 2, total_pages: 2, per_page: 15, total: 16 };

async function visit(path) {
    const pinia = createPinia();
    const auth = useAuthStore(pinia);
    auth.status = 'authenticated';
    auth.user = { id: 'driver', name: 'Driver' };
    const router = createAppRouter(createMemoryHistory(), pinia);
    await router.push(path);
    const wrapper = mount(App, { global: { plugins: [pinia, router] } });
    await flushPromises();

    return { router, wrapper };
}

async function follow(wrapper, selector) {
    await wrapper.get(selector).trigger('click');
    await flushPromises();
}

describe('vehicle navigation across screens', () => {
    beforeEach(() => {
        vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    });

    afterEach(() => vi.restoreAllMocks());

    it('opens an archived visit from the dashboard and returns to the original garage page', async () => {
        const archived = { ...vehicle, attributes: { ...vehicle.attributes, archived_at: '2026-06-01' } };
        const garage = '/?status=archived&page=2';
        const service = { type: 'oil_change', label: 'Oil change', details: { filter_part_number: 'OIL-123' } };
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({ data: [archived], meta: { pagination } });
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(archived);
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [] });
        vi.spyOn(dashboardApi, 'fetchVehicleDashboard').mockResolvedValue({ attributes: { vehicle: { id: archived.id, ...archived.attributes } } });
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockResolvedValue({
            data: [{ id: 'visit', attributes: { kind: 'maintenance', title: 'Maintenance visit', occurred_at: '2026-05-01', odometer: 42000 } }],
        });
        vi.spyOn(maintenanceApi, 'fetchMaintenanceEvent').mockResolvedValue({
            id: 'visit', relationships: relationship, attributes: { performed_at: '2026-05-01', odometer: 42000, services: [service] },
        });

        const { wrapper, router } = await visit(garage);
        await follow(wrapper, 'h2 a');
        expect(router.currentRoute.value.name).toBe('vehicles.dashboard');
        expect(wrapper.get('nav[aria-label="Vehicle sections"] [aria-current="page"]').text()).toBe('Overview');
        expect(wrapper.find('a[href*="/maintenance-events/new"]').exists()).toBe(false);

        await follow(wrapper, 'a[href^="/maintenance-events/visit"]');
        expect(router.currentRoute.value.name).toBe('maintenance-events.show');
        expect(wrapper.get('nav[aria-label="Vehicle sections"] [aria-current="location"]').text()).toBe('Maintenance history');
        expect(wrapper.text()).toContain('OIL-123');
        expect(wrapper.find('a[href*="/edit"]').exists()).toBe(false);

        await follow(wrapper, 'nav[aria-label="Vehicle sections"] a[href^="/vehicles/car/fuel-entries"]');
        expect(router.currentRoute.value.name).toBe('fuel-entries.index');
        expect(wrapper.get('nav[aria-label="Vehicle sections"] [aria-current="page"]').text()).toBe('Fuel history');
        expect(wrapper.find('a[href*="/fuel-entries/new"]').exists()).toBe(false);
        await follow(wrapper, 'a[href^="/?status=archived"]');
        expect(router.currentRoute.value.fullPath).toBe(garage);
        expect(wrapper.text()).toContain('Page 2 of 2');
        wrapper.unmount();
    });

    it('opens an entry on a later history page and saves back to the same list', async () => {
        const entry = {
            id: 'fill', relationships: relationship,
            attributes: { fueled_at: '2026-11-20T16:45:00+00:00', odometer: 44000, gallons: '10', total_cost: '30', notes: 'Original note' },
        };
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(vehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [entry], meta: { pagination } });
        vi.spyOn(fuelApi, 'fetchFuelEntry').mockResolvedValue(entry);
        const update = vi.spyOn(fuelApi, 'updateFuelEntry').mockResolvedValue(entry);
        const history = '/vehicles/car/fuel-entries?sort=odometer&odometer_min=35000&page=2';
        const { wrapper, router } = await visit(history);
        await follow(wrapper, 'a[href^="/fuel-entries/fill/edit"]');
        expect(router.currentRoute.value.name).toBe('fuel-entries.edit');
        await wrapper.get('#notes').setValue('Corrected note');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(update).toHaveBeenCalledWith('fill', expect.objectContaining({
            notes: 'Corrected note',
            fueled_at: entry.attributes.fueled_at,
        }));
        expect(fuelApi.fetchFuelEntries).toHaveBeenLastCalledWith('car', expect.objectContaining({
            sort: 'odometer',
            'filter[odometer_min]': '35000',
            'page[number]': 2,
        }));
        expect(router.currentRoute.value.fullPath).toBe(history);
        expect(wrapper.text()).toContain('Page 2 of 2');
        wrapper.unmount();
    });
});
