import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as fuelApi from '../api/fuel';
import * as vehicleApi from '../api/vehicles';
import FuelEntryFormView from '../views/FuelEntryFormView.vue';
import FuelHistoryView from '../views/FuelHistoryView.vue';
import VehiclesView from '../views/VehiclesView.vue';

function routerFor(component, path) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: VehiclesView },
            { path: '/vehicles/:vehicle/fuel-entries', component: FuelHistoryView },
            { path: '/vehicles/:vehicle/fuel-entries/new', component: FuelEntryFormView },
            { path: '/fuel-entries/:fuelEntry/edit', component: FuelEntryFormView },
            { path: '/vehicles/:vehicle/fuel-entries/test', component },
        ],
    });

    router.push(path);

    return router;
}

const activeVehicle = { id: 'vehicle-1', attributes: { nickname: 'Daily', archived_at: null } };

describe('fuel tracking experience', () => {
    afterEach(() => vi.restoreAllMocks());

    it('submits a fuel form and maps server validation errors', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'createFuelEntry').mockRejectedValue({
            response: {
                data: {
                    errors: [{ detail: 'The odometer must be greater than zero.', source: { pointer: '/data/attributes/odometer' } }],
                },
            },
        });
        const router = routerFor(FuelEntryFormView, '/vehicles/vehicle-1/fuel-entries/new');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });

        await flushPromises();
        await wrapper.get('#fueled_at').setValue('2026-07-31T15:00');
        await wrapper.get('#odometer').setValue('24500');
        await wrapper.get('#gallons').setValue('12.5');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(fuelApi.createFuelEntry).toHaveBeenCalledWith('vehicle-1', expect.objectContaining({ gallons: '12.5', odometer: 24500 }));
        expect(wrapper.text()).toContain('The odometer must be greater than zero.');
    });

    it('keeps loading separate from an empty history state', async () => {
        let resolveEntries;
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockReturnValue(new Promise((resolve) => { resolveEntries = resolve; }));
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });

        expect(wrapper.text()).toContain('Loading fuel history');
        expect(wrapper.text()).not.toContain('No fuel entries match');

        resolveEntries({ data: [] });
        await flushPromises();

        expect(wrapper.text()).toContain('No fuel entries match');
    });

    it('formats populated entries and explains unavailable MPG for the first fill-up', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({
            data: [{
                id: 'fuel-1',
                attributes: {
                    fueled_at: '2026-07-31T15:00:00Z',
                    odometer: 24500,
                    gallons: '12.500',
                    total_cost: '45.00',
                    price_per_gallon: '3.600',
                    miles_driven: null,
                    calculated_mpg: null,
                    station: 'Main Street Fuel',
                    fuel_grade: '87',
                    notes: 'Full tank',
                },
            }],
            meta: { pagination: { current_page: 1, total_pages: 1, total: 1, per_page: 10 } },
        });
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('24,500 mi');
        expect(wrapper.text()).toContain('12.5 gal');
        expect(wrapper.text()).toContain('$45.00');
        expect(wrapper.text()).toContain('Main Street Fuel');
        expect(wrapper.text()).toContain('MPG unavailable');
    });

    it('passes filters, sorting, and pagination parameters to the API', async () => {
        const fetchEntries = vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({
            data: [{ id: 'fuel-1', attributes: { fueled_at: '2026-07-31T15:00:00Z', odometer: 24500, gallons: '12.5', miles_driven: 350, calculated_mpg: '28.0' } }],
            meta: { pagination: { current_page: 1, total_pages: 2, total: 11, per_page: 10 } },
        });
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });

        await flushPromises();
        await wrapper.get('#fueled_from').setValue('2026-07-01');
        await wrapper.get('#odometer_min').setValue('20000');
        await wrapper.get('#mpg_max').setValue('35');
        await wrapper.get('#fuel_sort').setValue('odometer');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(fetchEntries).toHaveBeenLastCalledWith('vehicle-1', expect.objectContaining({
            'filter[fueled_from]': '2026-07-01',
            'filter[odometer_min]': 20000,
            'filter[mpg_max]': 35,
            sort: 'odometer',
            'page[number]': 1,
        }));

        await wrapper.get('nav[aria-label="Fuel history pagination"] button:last-child').trigger('click');
        await flushPromises();
        expect(fetchEntries).toHaveBeenLastCalledWith('vehicle-1', expect.objectContaining({ 'page[number]': 2 }));
    });

    it('supports edit loading and accessible delete confirmation', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntry').mockResolvedValue({ id: 'fuel-1', attributes: { vehicle_id: 'vehicle-1', fueled_at: '2026-07-31T15:00:00Z', odometer: 24500, gallons: '12.5' } });
        const editRouter = routerFor(FuelEntryFormView, '/fuel-entries/fuel-1/edit');
        await editRouter.isReady();
        const editWrapper = mount(FuelEntryFormView, { global: { plugins: [editRouter] } });

        await flushPromises();

        expect(editWrapper.get('#odometer').element.value).toBe('24500');

        const deleteEntry = vi.spyOn(fuelApi, 'deleteFuelEntry').mockResolvedValue(undefined);
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [{ id: 'fuel-1', attributes: { fueled_at: '2026-07-31T15:00:00Z', odometer: 24500, gallons: '12.5' } }] });
        const historyRouter = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await historyRouter.isReady();
        const historyWrapper = mount(FuelHistoryView, { global: { plugins: [historyRouter] } });

        await flushPromises();
        const deleteButton = historyWrapper.findAll('button').find((button) => button.text() === 'Delete');
        await deleteButton.trigger('click');
        expect(historyWrapper.text()).toContain('Delete this fuel entry?');
        await historyWrapper.get('[role="alertdialog"] button').trigger('click');
        await flushPromises();

        expect(deleteEntry).toHaveBeenCalledWith('fuel-1');
    });

    it('shows vehicle fuel actions and nullable summaries', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({ data: [{ id: 'vehicle-1', attributes: { nickname: 'Daily', year: 2022, make: 'Honda', model: 'Civic', current_odometer: 24500, archived_at: null, latest_mpg: '28.4', average_mpg: '27.9', most_recent_fueled_at: '2026-07-31T15:00:00Z' } }] });
        const router = routerFor(VehiclesView, '/');
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('28.4 MPG');
        expect(wrapper.text()).toContain('27.9 MPG');
        expect(wrapper.find('a[href="/vehicles/vehicle-1/fuel-entries"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/vehicles/vehicle-1/fuel-entries/new"]').exists()).toBe(true);
    });

    it('keeps archived vehicles read-only and hides mutation links', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ id: 'vehicle-1', attributes: { ...activeVehicle.attributes, archived_at: '2026-07-31T15:00:00Z' } });
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [] });
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).not.toContain('Add fuel');
        expect(wrapper.text()).toContain('No fuel entries match');
    });

    it('does not render the fuel form for an archived vehicle', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ id: 'vehicle-1', attributes: { ...activeVehicle.attributes, archived_at: '2026-07-31T15:00:00Z' } });
        const router = routerFor(FuelEntryFormView, '/vehicles/vehicle-1/fuel-entries/new');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Archived vehicle');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
