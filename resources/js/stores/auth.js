import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import apiClient, {
    resetAuthFailureHandling,
    setAuthFailureHandler,
} from '../api/client';

function userFromPayload(payload) {
    const resource = payload?.data ?? payload;

    return resource?.attributes
        ? { id: resource.id, ...resource.attributes }
        : resource ?? null;
}

function isAuthenticationFailure(error) {
    return [401, 403].includes(error.response?.status);
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const status = ref('unknown');
    const initializationError = ref(null);
    let initializationPromise = null;

    const isAuthenticated = computed(() => status.value === 'authenticated');
    const isReady = computed(() => !['unknown', 'loading'].includes(status.value));

    function setAuthenticated(nextUser) {
        user.value = nextUser;
        status.value = 'authenticated';
        initializationError.value = null;
        resetAuthFailureHandling();
    }

    function clearAuth() {
        user.value = null;
        status.value = 'guest';
        initializationError.value = null;
    }

    async function initialize({ force = false } = {}) {
        if (!force && ['authenticated', 'guest'].includes(status.value)) {
            return user.value;
        }

        if (initializationPromise) {
            return initializationPromise;
        }

        status.value = 'loading';
        initializationError.value = null;

        initializationPromise = apiClient.get('/auth/user')
            .then(({ data }) => {
                setAuthenticated(userFromPayload(data));

                return user.value;
            })
            .catch((error) => {
                if (isAuthenticationFailure(error)) {
                    clearAuth();

                    return null;
                }

                status.value = 'error';
                initializationError.value = error;

                return null;
            })
            .finally(() => {
                initializationPromise = null;
            });

        return initializationPromise;
    }

    async function authenticate(endpoint, credentials) {
        const { data } = await apiClient.post(endpoint, credentials);
        const nextUser = userFromPayload(data);

        if (nextUser) {
            setAuthenticated(nextUser);
        } else {
            await initialize({ force: true });
        }

        return user.value;
    }

    async function login(credentials) {
        return authenticate('/auth/login', credentials);
    }

    async function register(credentials) {
        return authenticate('/auth/register', credentials);
    }

    async function logout() {
        try {
            await apiClient.post('/auth/logout');
        } finally {
            clearAuth();
            resetAuthFailureHandling();
        }
    }

    async function requestPasswordReset(email) {
        return apiClient.post('/auth/forgot-password', { email });
    }

    async function resetPassword(credentials) {
        return apiClient.post('/auth/reset-password', credentials);
    }

    function bindAuthFailureHandler(router) {
        setAuthFailureHandler(async () => {
            clearAuth();

            const currentRoute = router.currentRoute.value;

            if (currentRoute.name !== 'login') {
                await router.replace({
                    name: 'login',
                    query: { redirect: currentRoute.fullPath },
                });
            }
        });
    }

    return {
        user,
        status,
        initializationError,
        isAuthenticated,
        isReady,
        initialize,
        login,
        register,
        logout,
        requestPasswordReset,
        resetPassword,
        clearAuth,
        bindAuthFailureHandler,
    };
});
