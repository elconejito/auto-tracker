<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';

import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const showApplicationHeader = computed(() => !route.meta.guestOnly);

onMounted(() => {
    auth.initialize();
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <header v-if="showApplicationHeader" class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center px-4 py-4 sm:px-6 lg:px-8">
                <RouterLink
                    class="rounded-sm text-lg font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                    to="/"
                >
                    AutoTracker
                </RouterLink>
            </div>
        </header>

        <main v-if="auth.isReady && !auth.initializationError">
            <RouterView />
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
                    @click="auth.initialize({ force: true })"
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
