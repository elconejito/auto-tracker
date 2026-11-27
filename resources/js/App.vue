<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';

import { intendedPath } from './router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const main = ref(null);
const signingOut = ref(false);
const signOutError = ref('');
const canRenderRoute = computed(() => auth.isReady && !auth.initializationError && (!route.meta.requiresAuth || auth.isAuthenticated));
const showApplicationHeader = computed(() => !route.meta.guestOnly);

async function retrySession() {
    await auth.initialize({ force: true });

    if (auth.initializationError) {
        return;
    }

    if (route.meta.requiresAuth && !auth.isAuthenticated) {
        await router.replace({ name: 'login', query: { redirect: route.fullPath } });
    } else if (route.meta.guestOnly && auth.isAuthenticated) {
        await router.replace(intendedPath(route.query.redirect));
    }
}

async function signOut() {
    signingOut.value = true;
    signOutError.value = '';

    try {
        await auth.logout();
        await router.replace({ name: 'login' });
    } catch {
        signOutError.value = 'We couldn’t sign you out. Check your connection and try again.';
    } finally {
        signingOut.value = false;
    }
}

watch([() => route.path, canRenderRoute], (_current, _previous, onCleanup) => {
    const region = main.value;

    if (!region || !canRenderRoute.value) {
        return;
    }

    function focusHeading(heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
    }

    const heading = region.querySelector('h1');
    if (heading) {
        focusHeading(heading);
        return;
    }

    region.focus({ preventScroll: true });
    const observer = new MutationObserver(() => {
        const loadedHeading = region.querySelector('h1');

        if (loadedHeading && document.activeElement === region) {
            focusHeading(loadedHeading);
        }
    });

    function stopWaiting() {
        observer.disconnect();
        region.removeEventListener('blur', stopWaiting);
    }

    observer.observe(region, { childList: true, subtree: true });
    region.addEventListener('blur', stopWaiting, { once: true });
    onCleanup(stopWaiting);
}, { flush: 'post' });

onMounted(() => {
    if (auth.status === 'unknown') {
        auth.initialize();
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <header v-if="showApplicationHeader" class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-4 sm:px-6 lg:px-8">
                <RouterLink
                    class="rounded-sm text-lg font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                    to="/"
                >
                    AutoTracker
                </RouterLink>
                <div v-if="auth.isAuthenticated" class="flex min-w-0 items-center gap-2">
                    <span class="max-w-36 truncate text-sm text-slate-600 sm:max-w-64" :title="auth.user?.email">{{ auth.user?.name || auth.user?.email }}</span>
                    <button class="min-h-11 shrink-0 rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-sky-700" :disabled="signingOut" type="button" @click="signOut">
                        {{ signingOut ? 'Signing out…' : 'Sign out' }}
                    </button>
                </div>
                <p v-if="signOutError" class="w-full text-sm text-red-700" role="alert">{{ signOutError }}</p>
            </div>
        </header>

        <main v-if="canRenderRoute" ref="main" :aria-label="route.meta.title" tabindex="-1">
            <RouterView :key="route.path" />
        </main>

        <main v-else class="flex min-h-screen items-center justify-center px-4 py-12">
            <div v-if="auth.initializationError" class="w-full max-w-md rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
                <h1 class="text-lg font-semibold text-slate-950">We couldn't check your session</h1>
                <p class="mt-2 text-sm leading-6 text-slate-700">
                    Check your connection and try again. Your sign-in status has not been changed.
                </p>
                <button
                    class="mt-6 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                    type="button"
                    @click="retrySession"
                >
                    Try again
                </button>
            </div>
            <p v-else aria-live="polite" class="text-sm text-slate-600" role="status">
                Checking your session…
            </p>
        </main>
    </div>
</template>
