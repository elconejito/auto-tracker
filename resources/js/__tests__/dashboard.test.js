import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

import * as dashboardApi from '../api/dashboard';
import DashboardView from '../views/DashboardView.vue';

function routerFor() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/vehicles/:vehicle', component: DashboardView }],
    });
    router.push('/vehicles/vehicle-1');

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
        expect(wrapper.text()).toContain('Shell');
        expect(wrapper.text()).toContain('Oil change');
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
