import { createRouter, createWebHistory } from 'vue-router';

import { returnPath } from '../utils/navigation';
import { useAuthStore } from '../stores/auth';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import FuelEntryDetailView from '../views/FuelEntryDetailView.vue';
import FuelEntryFormView from '../views/FuelEntryFormView.vue';
import FuelHistoryView from '../views/FuelHistoryView.vue';
import FuelImportView from '../views/FuelImportView.vue';
import LoginView from '../views/LoginView.vue';
import MaintenanceEventDetailView from '../views/MaintenanceEventDetailView.vue';
import MaintenanceEventFormView from '../views/MaintenanceEventFormView.vue';
import MaintenanceHistoryView from '../views/MaintenanceHistoryView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import RegisterView from '../views/RegisterView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import DashboardView from '../views/DashboardView.vue';
import VehicleFormView from '../views/VehicleFormView.vue';
import VehiclesView from '../views/VehiclesView.vue';

const routes = [
    {
        path: '/',
        name: 'vehicles',
        component: VehiclesView,
        meta: { title: 'Vehicles', requiresAuth: true },
    },
    {
        path: '/vehicles/new',
        name: 'vehicles.create',
        component: VehicleFormView,
        meta: { title: 'Add vehicle', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle',
        name: 'vehicles.dashboard',
        component: DashboardView,
        meta: { title: 'Vehicle overview', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/edit',
        name: 'vehicles.edit',
        component: VehicleFormView,
        meta: { title: 'Edit vehicle', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/fuel-entries',
        name: 'fuel-entries.index',
        component: FuelHistoryView,
        meta: { title: 'Fuel history', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/fuel-entries/import',
        name: 'fuel-entries.import',
        component: FuelImportView,
        meta: { title: 'Import fuel fill-ups', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/fuel-entries/new',
        name: 'fuel-entries.create',
        component: FuelEntryFormView,
        meta: { title: 'Add fuel', requiresAuth: true },
    },
    {
        path: '/fuel-entries/:fuelEntry',
        name: 'fuel-entries.show',
        component: FuelEntryDetailView,
        meta: { title: 'Fuel entry', requiresAuth: true },
    },
    {
        path: '/fuel-entries/:fuelEntry/edit',
        name: 'fuel-entries.edit',
        component: FuelEntryFormView,
        meta: { title: 'Edit fuel entry', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/maintenance-events',
        name: 'maintenance-events.index',
        component: MaintenanceHistoryView,
        meta: { title: 'Maintenance history', requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/maintenance-events/new',
        name: 'maintenance-events.create',
        component: MaintenanceEventFormView,
        meta: { title: 'Add maintenance', requiresAuth: true },
    },
    {
        path: '/maintenance-events/:maintenanceEvent',
        name: 'maintenance-events.show',
        component: MaintenanceEventDetailView,
        meta: { title: 'Maintenance visit', requiresAuth: true },
    },
    {
        path: '/maintenance-events/:maintenanceEvent/edit',
        name: 'maintenance-events.edit',
        component: MaintenanceEventFormView,
        meta: { title: 'Edit maintenance', requiresAuth: true },
    },
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { title: 'Sign in', guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: RegisterView,
        meta: { title: 'Create account', guestOnly: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPasswordView,
        meta: { title: 'Request password reset', guestOnly: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: ResetPasswordView,
        meta: { title: 'Reset password', guestOnly: true },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFoundView,
        meta: { title: 'Page not found' },
    },
];

export function intendedPath(value) {
    return returnPath(value, '/');
}

export function redirectQuery(value) {
    const redirect = intendedPath(value);

    return redirect === '/' ? {} : { redirect };
}

export function createAppRouter(history = createWebHistory(), pinia = null) {
    const router = createRouter({
        history,
        routes,
        scrollBehavior(to, from, savedPosition) {
            return savedPosition ?? (to.path !== from.path ? { top: 0 } : false);
        },
    });

    router.beforeEach(async (to) => {
        const auth = pinia ? useAuthStore(pinia) : useAuthStore();

        if (to.meta.requiresAuth || to.meta.guestOnly) {
            await auth.initialize();
        }

        if (to.meta.requiresAuth) {
            if (auth.isAuthenticated || auth.initializationError) {
                return true;
            }

            return {
                name: 'login',
                query: { redirect: to.fullPath },
            };
        }

        if (to.meta.guestOnly && auth.isAuthenticated) {
            const target = router.resolve(intendedPath(to.query.redirect));

            return target.meta.requiresAuth ? target.fullPath : '/';
        }

        return true;
    });

    router.afterEach((to, from, failure) => {
        if (!failure) {
            document.title = `${to.meta.title} · AutoTracker`;
        }
    });

    return router;
}
