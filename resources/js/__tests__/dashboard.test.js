import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as dashboardApi from '../api/dashboard';
import DashboardView from '../views/DashboardView.vue';

function routerFor(path = '/vehicles/vehicle-1') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/vehicles/:vehicle', component: DashboardView }],
    });
    router.push(path);

    return router;
}

function dashboardResponse(overrides = {}) {
    return {
        id: 'vehicle-1',
        attributes: {
            vehicle: { id: 'vehicle-1', nickname: 'Daily', current_odometer: 24500 },
            fuel_summary: { latest_mpg: '31.2', average_mpg: '30.4', most_recent_fueled_at: '2026-07-30T12:00:00Z' },
            maintenance_summary: { most_recent_performed_at: '2026-07-20T12:00:00Z' },
            service_recency: [{ key: 'oil_change', label: 'Oil change', miles_since_service: 1200 }],
            recent_timeline: [],
            ...overrides,
        },
    };
}

function timelineResponse(data = []) {
    return { data, meta: { pagination: { current_page: 1, total_pages: 1, per_page: 15, total: data.length } } };
}

describe('vehicle dashboard', () => {
    afterEach(() => vi.restoreAllMocks());

    it('does not show false zero summaries while requests are pending', async () => {
        let resolveDashboard;
        const dashboardRequest = new Promise((resolve) => { resolveDashboard = resolve; });
        vi.spyOn(dashboardApi, 'fetchVehicleDashboard').mockReturnValue(dashboardRequest);
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockReturnValue(new Promise(() => {}));
        const router = routerFor();
        await router.isReady();
        const wrapper = mount(DashboardView, { global: { plugins: [router] } });

        expect(wrapper.text()).toContain('Loading vehicle dashboard');
        expect(wrapper.text()).not.toContain('0 MPG');
        expect(wrapper.text()).not.toContain('0 miles ago');

        resolveDashboard(dashboardResponse());
    });

    it('shows unavailable when service recency is missing', async () => {
        vi.spyOn(dashboardApi, 'fetchVehicleDashboard').mockResolvedValue(dashboardResponse({ service_recency: [{ key: 'oil_change', label: 'Oil change', miles_since_service: null }] }));
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockResolvedValue(timelineResponse());
        const router = routerFor();
        await router.isReady();
        const wrapper = mount(DashboardView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Oil change');
        expect(wrapper.text()).toContain('Unavailable');
        expect(wrapper.text()).not.toContain('0 miles ago');
    });

    it('renders populated fuel and maintenance timeline items', async () => {
        const mixedTimeline = [
            { id: 'fuel-1', attributes: { occurred_at: '2026-07-30T12:00:00Z', odometer: 24500, kind: 'fuel', title: 'Fuel fill-up', summary: 'Shell', cost: '45.00', details: { gallons: '12.5' } } },
            { id: 'maintenance-1', attributes: { occurred_at: '2026-07-20T12:00:00Z', odometer: 24000, kind: 'maintenance', title: 'Maintenance visit', summary: 'Garage', cost: '100.00', details: { services: [{ label: 'Oil change' }] } } },
        ];
        vi.spyOn(dashboardApi, 'fetchVehicleDashboard').mockResolvedValue(dashboardResponse({ recent_timeline: mixedTimeline }));
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockResolvedValue(timelineResponse(mixedTimeline));
        const router = routerFor();
        await router.isReady();
        const wrapper = mount(DashboardView, { global: { plugins: [router] } });

        await flushPromises();

        expect(wrapper.text()).toContain('Daily');
        expect(wrapper.text()).toContain('Fuel fill-up');
        expect(wrapper.text()).toContain('Maintenance visit');
        const activity = wrapper.get('[aria-labelledby="timeline-heading"]');
        expect(activity.findAll('article')).toHaveLength(2);
        const links = activity.findAll('h3 a').map((link) => router.resolve(link.attributes('href')));
        expect(links.map((link) => link.path)).toEqual(['/fuel-entries/fuel-1', '/maintenance-events/maintenance-1']);
        expect(links.every((link) => link.query.returnTo === '/vehicles/vehicle-1')).toBe(true);
        expect(wrapper.text()).toContain('Shell');
        expect(wrapper.text()).toContain('Oil change');
    });

    it('restores a timeline page from the URL and keeps an archived dashboard read-only', async () => {
        vi.spyOn(dashboardApi, 'fetchVehicleDashboard').mockResolvedValue(dashboardResponse({
            vehicle: { id: 'vehicle-1', nickname: 'Daily', archived_at: '2026-07-01' },
        }));
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockResolvedValue({
            data: [{ id: 'entry', attributes: { kind: 'fuel', title: 'Fuel fill-up', occurred_at: '2026-11-26T00:00:00Z' } }],
            meta: { pagination: { current_page: 2, total_pages: 2, total: 16 } },
        });
        const router = routerFor('/vehicles/vehicle-1?page=2');
        await router.isReady();
        const wrapper = mount(DashboardView, { global: { plugins: [router] } });
        await flushPromises();

        expect(dashboardApi.fetchVehicleTimeline).toHaveBeenCalledWith('vehicle-1', 2);
        expect(wrapper.text()).toContain('Page 2 of 2');
        expect(wrapper.text()).toContain('Nov 26, 2026');
        expect(wrapper.text()).toContain('Archived');
        expect(wrapper.text()).not.toContain('Add fuel');
        expect(wrapper.text()).not.toContain('Add maintenance');
        expect(router.resolve(wrapper.get('h3 a').attributes('href')).query.returnTo).toBe('/vehicles/vehicle-1?page=2');
    });

    it('offers retry after dashboard loading fails', async () => {
        const fetchDashboard = vi.spyOn(dashboardApi, 'fetchVehicleDashboard')
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue(dashboardResponse());
        vi.spyOn(dashboardApi, 'fetchVehicleTimeline').mockResolvedValue(timelineResponse());
        const router = routerFor();
        await router.isReady();
        const wrapper = mount(DashboardView, { global: { plugins: [router] } });

        await flushPromises();
        expect(wrapper.text()).toContain('Dashboard is unavailable');
        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(fetchDashboard).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Daily');
    });
});
