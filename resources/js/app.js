import '../css/app.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { createWebHistory } from 'vue-router';

import App from './App.vue';
import { createAppRouter } from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();
const router = createAppRouter(createWebHistory(), pinia);
const auth = useAuthStore(pinia);

auth.bindAuthFailureHandler(router);
app.use(pinia);
app.use(router);
app.mount('#app');
