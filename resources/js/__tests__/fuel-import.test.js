import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as fuelApi from '../api/fuel';
import apiClient from '../api/client';
import FuelHistoryView from '../views/FuelHistoryView.vue';
import * as vehicleApi from '../api/vehicles';
import FuelImportView from '../views/FuelImportView.vue';

async function render() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/vehicles/:vehicle/fuel-entries/import', component: FuelImportView },
            { path: '/vehicles/:vehicle/fuel-entries', component: { template: '<p>History</p>' } },
        ],
    });
    await router.push('/vehicles/vehicle-1/fuel-entries/import');
    const wrapper = mount(FuelImportView, { global: { plugins: [router] } });
    await flushPromises();
    return wrapper;
}

async function selectFile(wrapper) {
    const file = new File(['model,odometer,gallons,price,fuelup_date'], 'history.csv', { type: 'text/csv' });
    Object.defineProperty(wrapper.get('input').element, 'files', { value: [file], configurable: true });
    await wrapper.get('input').trigger('change');
    return file;
}

describe('fuel CSV import', () => {
    beforeEach(() => {
        vi.spyOn(vehicleApi, 'fetchVehicle').mockResolvedValue({ id: 'vehicle-1', attributes: { nickname: 'Daily', model: 'Civic', archived_at: null } });
    });
    afterEach(() => vi.restoreAllMocks());

    it('uploads multipart data to the selected vehicle endpoint', async () => {
        const file = new File(['csv'], 'history.csv', { type: 'text/csv' });
        const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { data: { status: 'success', imported: 2, failed: 0, failures: [] } } });
        expect(await fuelApi.importFuelEntries('vehicle-1', file)).toEqual({ status: 'success', imported: 2, failed: 0, failures: [] });
        expect(post).toHaveBeenCalledWith('/vehicles/vehicle-1/fuel-imports', expect.any(FormData));
        expect(post.mock.calls[0][1].get('file')).toBe(file);
    });

    it('links from fuel history to the selected vehicle import screen', async () => {
        vi.spyOn(fuelApi, 'fetchFuelEntries').mockResolvedValue({ data: [] });
        const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/vehicles/:vehicle/fuel-entries', component: FuelHistoryView }] });
        await router.push('/vehicles/vehicle-1/fuel-entries');
        const wrapper = mount(FuelHistoryView, { global: { plugins: [router] } });
        await flushPromises();
        expect(wrapper.findAll('a').find((link) => link.text() === 'Import CSV').attributes('href')).toBe('/vehicles/vehicle-1/fuel-entries/import');
    });

    it('shows the selected vehicle and submits its file with pending and success states', async () => {
        let resolve;
        const upload = vi.spyOn(fuelApi, 'importFuelEntries').mockReturnValue(new Promise((done) => { resolve = done; }));
        const wrapper = await render();
        expect(wrapper.text()).toContain('Importing into Daily');
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
        const file = await selectFile(wrapper);
        await wrapper.get('form').trigger('submit');
        expect(upload).toHaveBeenCalledWith('vehicle-1', file);
        expect(wrapper.text()).toContain('Importing…');
        expect(wrapper.get('input').element.disabled).toBe(true);
        resolve({ status: 'success', imported: 3, failed: 0, failures: [] });
        await flushPromises();
        expect(wrapper.text()).toContain('Success');
        expect(wrapper.text()).toContain('3 imported · 0 not imported');
        expect(wrapper.find('form').exists()).toBe(true);
        expect(wrapper.get('input').element.value).toBe('');
        expect(wrapper.findAll('a').some((link) => link.attributes('href') === '/vehicles/vehicle-1/fuel-entries')).toBe(true);
    });

    it('shows row errors and permits retrying', async () => {
        vi.spyOn(fuelApi, 'importFuelEntries').mockRejectedValue({ response: { status: 422, data: { errors: [{ detail: 'Row 3: invalid gallons.' }] } } });
        const wrapper = await render();
        await selectFile(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Row 3: invalid gallons.');
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });

    it.each([
        ['partial_success', 2, 'Partial success'],
        ['failure', 0, 'Total failure'],
    ])('reports %s with failed rows and supports a corrected upload', async (status, imported, title) => {
        const upload = vi.spyOn(fuelApi, 'importFuelEntries').mockResolvedValueOnce({
            status, imported, failed: 1,
            failures: [{ row: 4, values: { fuelup_date: '2025-02-01', odometer: '1300' }, errors: ['Gallons must be greater than zero.'] }],
        }).mockResolvedValueOnce({ status: 'success', imported: 1, failed: 0, failures: [] });
        const wrapper = await render();
        await selectFile(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="status"]').text()).toContain(title);
        expect(wrapper.get('tbody').text()).toContain('4');
        expect(wrapper.get('tbody').text()).toContain('2025-02-01');
        expect(wrapper.get('tbody').text()).toContain('1300');
        expect(wrapper.get('tbody').text()).toContain('Gallons must be greater than zero.');
        expect(wrapper.text()).toContain('Upload another CSV');
        const manual = wrapper.findAll('a').find((link) => link.text().includes('Add missing fill-up manually'));
        expect(manual.attributes('href')).toBe('/vehicles/vehicle-1/fuel-entries/new');
        expect(manual.attributes('target')).toBe('_blank');
        await wrapper.get('form').trigger('submit');
        expect(upload).toHaveBeenCalledTimes(1);
        // A newly selected file is required before retrying.
        const file = new File(['corrected'], 'fixed.csv', { type: 'text/csv' });
        Object.defineProperty(wrapper.get('input').element, 'files', { value: [file], configurable: true });
        await wrapper.get('input').trigger('change');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(upload).toHaveBeenLastCalledWith('vehicle-1', file);
        expect(wrapper.get('[role="status"]').text()).toContain('Success');
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('does not claim total failure when a connection error leaves the outcome unknown', async () => {
        vi.spyOn(fuelApi, 'importFuelEntries').mockRejectedValue(new Error('offline'));
        const wrapper = await render();
        await selectFile(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Import status unavailable');
        expect(wrapper.text()).toContain('Check fuel history before retrying');
    });

    it('blocks archived vehicles and handles load errors', async () => {
        vehicleApi.fetchVehicle.mockResolvedValueOnce({ id: 'vehicle-1', attributes: { archived_at: '2025-01-01' } });
        const archived = await render();
        expect(archived.text()).toContain('Restore this vehicle');
        expect(archived.find('form').exists()).toBe(false);
        vehicleApi.fetchVehicle.mockRejectedValueOnce(new Error('offline'));
        const failed = await render();
        expect(failed.get('[role="alert"]').text()).toContain('We could not load this vehicle');
        await failed.get('button').trigger('click');
        await flushPromises();
        expect(failed.find('form').exists()).toBe(true);
    });
});
