import { createRouter, createWebHistory } from 'vue-router';

import FoundationView from '../views/FoundationView.vue';
import NotFoundView from '../views/NotFoundView.vue';

const routes = [
    {
        path: '/',
        name: 'foundation',
        component: FoundationView,
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFoundView,
    },
];

export function createAppRouter(history = createWebHistory()) {
    return createRouter({
        history,
        routes,
        scrollBehavior: () => ({ top: 0 }),
    });
}
