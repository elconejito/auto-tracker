import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import FuelEntryFormView from '../views/FuelEntryFormView.vue';
import FuelHistoryView from '../views/FuelHistoryView.vue';
import LoginView from '../views/LoginView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import RegisterView from '../views/RegisterView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import VehicleFormView from '../views/VehicleFormView.vue';
import VehiclesView from '../views/VehiclesView.vue';

const routes = [
    {
        path: '/',
        name: 'vehicles',
        component: VehiclesView,
        meta: { requiresAuth: true },
    },
    {
        path: '/vehicles/new',
        name: 'vehicles.create',
        component: VehicleFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/edit',
        name: 'vehicles.edit',
        component: VehicleFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/fuel-entries',
        name: 'fuel-entries.index',
        component: FuelHistoryView,
        meta: { requiresAuth: true },
    },
    {
        path: '/vehicles/:vehicle/fuel-entries/new',
        name: 'fuel-entries.create',
        component: FuelEntryFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/fuel-entries/:fuelEntry/edit',
        name: 'fuel-entries.edit',
        component: FuelEntryFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: RegisterView,
        meta: { guestOnly: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPasswordView,
        meta: { guestOnly: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: ResetPasswordView,
        meta: { guestOnly: true },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFoundView,
    },
];

export function intendedPath(value) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) {
        return '/';
    }

    return value;
}

export function createAppRouter(history = createWebHistory(), pinia = null) {
    const router = createRouter({
        history,
        routes,
        scrollBehavior: () => ({ top: 0 }),
    });

    router.beforeEach(async (to) => {
        const auth = pinia ? useAuthStore(pinia) : useAuthStore();

        if (to.meta.requiresAuth || to.meta.guestOnly) {
            await auth.initialize();
        }

        if (to.meta.requiresAuth) {
            if (auth.isAuthenticated) {
                return true;
            }

            if (auth.status === 'error') {
                return false;
            }

            return {
                name: 'login',
                query: { redirect: to.fullPath },
            };
        }

        if (to.meta.guestOnly && auth.isAuthenticated) {
            return { path: intendedPath(to.query.redirect) };
        }

        return true;
    });

    return router;
}
