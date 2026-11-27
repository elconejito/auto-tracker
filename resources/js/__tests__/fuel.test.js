import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as fuelApi from '../api/fuel';
import * as vehicleApi from '../api/vehicles';
import FuelEntryDetailView from '../views/FuelEntryDetailView.vue';
import FuelEntryFormView from '../views/FuelEntryFormView.vue';
import FuelHistoryView from '../views/FuelHistoryView.vue';
import VehiclesView from '../views/VehiclesView.vue';
import { toDateInput, formatFuelDate } from '../utils/fuel';

function routerFor(component, path) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: VehiclesView },
            { path: '/vehicles/:vehicle/fuel-entries', component: FuelHistoryView },
            { path: '/vehicles/:vehicle/fuel-entries/new', component: FuelEntryFormView },
            { path: '/fuel-entries/:fuelEntry/edit', component: FuelEntryFormView },
            { path: '/fuel-entries/:fuelEntry', component: FuelEntryDetailView },
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
        await wrapper.get('#fueled_at').setValue('2026-07-31');
        await wrapper.get('#odometer').setValue('24500');
        await wrapper.get('#gallons').setValue('12.5');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(fuelApi.createFuelEntry).toHaveBeenCalledWith('vehicle-1', expect.objectContaining({ fueled_at: '2026-07-31', gallons: '12.5', odometer: 24500, station: null }));
        expect(wrapper.text()).toContain('The odometer must be greater than zero.');
    });

    it('uses date-only entry and places the optional station directly before notes', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        const router = routerFor(FuelEntryFormView, '/vehicles/vehicle-1/fuel-entries/new');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });
        await flushPromises();

        expect(wrapper.get('#fueled_at').attributes('type')).toBe('date');
        expect(wrapper.findAll('input, select, textarea').map((field) => field.attributes('id'))).toEqual([
            'fueled_at', 'odometer', 'gallons', 'price_per_gallon', 'total_cost', 'fuel_grade', 'station', 'notes',
        ]);
        expect(wrapper.get('#station').element.required).toBe(false);
        expect(wrapper.get('label[for="station"]').text()).toContain('optional');
    });

    it('preserves the recorded calendar date without timezone shifts', () => {
        expect(toDateInput('2026-07-31T00:00:00Z')).toBe('2026-07-31');
        expect(toDateInput('2026-07-31')).toBe('2026-07-31');
        expect(toDateInput(null)).toBe('');
        expect(formatFuelDate('2026-07-31T00:00:00Z')).toBe('Jul 31, 2026');
        expect(formatFuelDate(null)).toBe('Unavailable');
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

        await flushPromises();
        resolveEntries({ data: [] });
        await flushPromises();

        expect(wrapper.text()).toContain('No fill-ups recorded');
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
            date_timezone: 'UTC',
            'filter[odometer_min]': '20000',
            'filter[mpg_max]': '35',
            sort: 'odometer',
            'page[number]': 1,
        }));

        await wrapper.get('nav[aria-label="Fuel history pagination"] button:last-child').trigger('click');
        await flushPromises();
        expect(fetchEntries).toHaveBeenLastCalledWith('vehicle-1', expect.objectContaining({ 'page[number]': 2 }));
        expect(router.currentRoute.value.query).toMatchObject({ page: '2', sort: 'odometer', fueled_from: '2026-07-01' });
        const edit = wrapper.findAll('a').find((link) => link.text() === 'Edit');
        expect(router.resolve(edit.attributes('href')).query.returnTo).toBe(router.currentRoute.value.fullPath);
        expect(wrapper.get('[aria-controls="fuel-filters"]').attributes('aria-expanded')).toBe('false');
        expect(wrapper.text()).toContain('Minimum odometer: 20000');
    });

    it('supports edit loading and accessible delete confirmation', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntry').mockResolvedValue({ id: 'fuel-1', attributes: { vehicle_id: 'vehicle-1', fueled_at: '2026-07-31T15:00:00Z', odometer: 24500, gallons: '12.5' } });
        const editRouter = routerFor(FuelEntryFormView, '/fuel-entries/fuel-1/edit');
        await editRouter.isReady();
        const editWrapper = mount(FuelEntryFormView, { global: { plugins: [editRouter] } });

        await flushPromises();

        expect(editWrapper.get('#odometer').element.value).toBe('24500');
        expect(editWrapper.get('#fueled_at').element.value).toBe('2026-07-31');

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

    it('returns direct edits to the owning vehicle and preserves an imported time during a notes edit', async () => {
        const timestamp = '2026-07-31T15:45:12+00:00';
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'fetchFuelEntry').mockResolvedValue({
            id: 'fuel-1',
            attributes: { fueled_at: timestamp, gallons: '12.5', odometer: 100 },
            relationships: { vehicle: { data: { id: activeVehicle.id } } },
        });
        const update = vi.spyOn(fuelApi, 'updateFuelEntry').mockResolvedValue({});
        const router = routerFor(FuelEntryFormView, '/fuel-entries/fuel-1/edit');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });
        await flushPromises();
        expect(wrapper.findAll('a').find((link) => link.text() === 'Cancel').attributes('href')).toBe('/vehicles/vehicle-1/fuel-entries');
        await wrapper.get('#notes').setValue('Receipt updated');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(update).toHaveBeenCalledWith('fuel-1', expect.objectContaining({ fueled_at: timestamp }));
        expect(router.currentRoute.value.path).toBe('/vehicles/vehicle-1/fuel-entries');
    });

    it('retries a failed form load and keeps navigation intact after leaving during save', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle')
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue(activeVehicle);
        let finish;
        vi.spyOn(fuelApi, 'createFuelEntry').mockImplementation(() => new Promise((resolve) => { finish = resolve; }));
        const router = routerFor(FuelEntryFormView, '/vehicles/vehicle-1/fuel-entries/new');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });
        await flushPromises();
        await wrapper.get('[role="alert"] button').trigger('click');
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        wrapper.unmount();
        await router.push('/');
        finish({});
        await flushPromises();
        expect(router.currentRoute.value.fullPath).toBe('/');
    });

    it('reports the corrective server message and submits a missing odometer as blank', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        const create = vi.spyOn(fuelApi, 'createFuelEntry').mockRejectedValue({
            response: { data: { errors: [{ detail: 'Odometer readings must increase.' }] } },
        });
        const router = routerFor(FuelEntryFormView, '/vehicles/vehicle-1/fuel-entries/new?returnTo=/vehicles/vehicle-1');
        await router.isReady();
        const wrapper = mount(FuelEntryFormView, { global: { plugins: [router] } });
        await flushPromises();
        expect(wrapper.findAll('a').find((link) => link.text() === 'Cancel').attributes('href')).toBe('/vehicles/vehicle-1');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(create).toHaveBeenCalledWith('vehicle-1', expect.objectContaining({ odometer: null }));
        expect(wrapper.get('[role="alert"]').text()).toContain('Odometer readings must increase.');
    });

    it('shows archived fill-up details and returns to its originating filtered page', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ id: activeVehicle.id, attributes: { nickname: 'Daily', archived_at: '2026-11-25' } });
        vi.spyOn(fuelApi, 'fetchFuelEntry').mockResolvedValue({
            id: 'fuel-1', relationships: { vehicle: { data: { id: activeVehicle.id } } },
            attributes: { fueled_at: '2026-07-31', station: 'Main Street', price_per_gallon: '3.689', notes: 'Receipt attached' },
        });
        const source = '/vehicles/vehicle-1/fuel-entries?sort=odometer&page=2';
        const router = routerFor(FuelEntryDetailView, { path: '/fuel-entries/fuel-1', query: { returnTo: source } });
        await router.isReady();
        const wrapper = mount(FuelEntryDetailView, { global: { plugins: [router] } });
        await flushPromises();
        expect(wrapper.text()).toContain('Main Street');
        expect(wrapper.text()).toContain('$3.689');
        expect(wrapper.text()).toContain('Receipt attached');
        expect(wrapper.text()).not.toContain('Edit fill-up');
        expect(wrapper.findAll('a').find((link) => link.text() === 'Back').attributes('href')).toBe(source);
    });

    it('returns to the last available page after deleting its only fill-up', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        vi.spyOn(fuelApi, 'deleteFuelEntry').mockResolvedValue();
        const row = { id: 'fuel-1', attributes: { fueled_at: '2026-07-31' } };
        const entries = vi.spyOn(fuelApi, 'fetchFuelEntries')
            .mockResolvedValueOnce({ data: [row], meta: { pagination: { current_page: 2, total_pages: 2 } } })
            .mockResolvedValueOnce({ data: [], meta: { pagination: { current_page: 2, total_pages: 1 } } })
            .mockResolvedValueOnce({ data: [row], meta: { pagination: { current_page: 1, total_pages: 1 } } });
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries?page=2&odometer_min=25');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Delete').trigger('click');
        await wrapper.get('[role="alertdialog"] button').trigger('click');
        await flushPromises();
        expect(entries).toHaveBeenLastCalledWith('vehicle-1', expect.objectContaining({ 'page[number]': 1, 'filter[odometer_min]': '25' }));
        expect(router.currentRoute.value.query).toEqual({ odometer_min: '25' });
        expect(wrapper.text()).not.toContain('No fuel entries match');
    });

    it('ignores stale page-clamp responses after leaving the history screen', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue(activeVehicle);
        let finish;
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockImplementation(() => new Promise((resolve) => { finish = resolve; }));
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries?page=3');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });
        await flushPromises();
        wrapper.unmount();
        await router.push('/?page=2');
        finish({ data: [], meta: { pagination: { total_pages: 1 } } });
        await flushPromises();
        expect(router.currentRoute.value.fullPath).toBe('/?page=2');
    });

    it('shows vehicle fuel actions and nullable summaries', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicles').mockResolvedValue({ data: [{ id: 'vehicle-1', attributes: { nickname: 'Daily', year: 2022, make: 'Honda', model: 'Civic', current_odometer: 24500, archived_at: null, latest_mpg: '28.4', average_mpg: '27.9', most_recent_fueled_at: '2026-07-31T15:00:00Z' } }] });
        const router = routerFor(VehiclesView, '/');
        await router.isReady();
        const wrapper = mount(VehiclesView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('28.4 MPG');
        expect(wrapper.text()).toContain('27.9 MPG');
        const overview = wrapper.findAll('a').find((link) => link.text() === 'Daily');
        expect(router.resolve(overview.attributes('href')).path).toBe('/vehicles/vehicle-1');
        const add = wrapper.findAll('a').find((link) => link.text() === 'Add fuel');
        expect(router.resolve(add.attributes('href')).query.returnTo).toBe('/');
    });

    it('keeps archived vehicles read-only and hides mutation links', async () => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ id: 'vehicle-1', attributes: { ...activeVehicle.attributes, archived_at: '2026-07-31T15:00:00Z' } });
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [] });
        const router = routerFor(FuelHistoryView, '/vehicles/vehicle-1/fuel-entries');
        await router.isReady();
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).not.toContain('Add fuel');
        expect(wrapper.text()).toContain('No fill-ups recorded');
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
