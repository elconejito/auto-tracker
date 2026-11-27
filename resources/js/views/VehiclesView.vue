<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { archiveVehicle, fetchVehicles, restoreVehicle } from '../api/vehicles';
import { formatFuelDate, formatMpg, formatOdometer, paginationFrom, resourceAttributes as attributes } from '../utils/fuel';

const route = useRoute();
const router = useRouter();
const vehicles = ref([]);
const status = ref('loading');
const errorMessage = ref('');
const archivedFilter = computed(() => ['archived', 'all'].includes(route.query.status) ? route.query.status : 'active');
const page = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1));
const pagination = ref({ lastPage: 1, total: 0 });
const hasArchivedVehicles = ref(false);
const pendingVehicleId = ref(null);
let requestId = 0;
let disposed = false;

function garageLocation(filter, number = 1) {
    return { path: '/', query: { status: filter === 'active' ? undefined : filter, page: number > 1 ? number : undefined } };
}

function vehicleLocation(path) {
    return { path, query: { garage: route.fullPath === '/' ? undefined : route.fullPath } };
}

async function loadVehicles() {
    if (disposed || route.path !== '/') return;
    const id = ++requestId;
    status.value = 'loading';
    errorMessage.value = '';

    try {
        const filter = { active: 'without', archived: 'only', all: 'with' }[archivedFilter.value];
        const response = await fetchVehicles({ 'filter[archived]': filter, 'page[number]': page.value });
        if (id !== requestId) return;
        pagination.value = paginationFrom(response);
        if (page.value > pagination.value.lastPage) {
            await router.replace(garageLocation(archivedFilter.value, pagination.value.lastPage));
            return;
        }

        vehicles.value = response.data ?? [];
        if (!vehicles.value.length && filter === 'without') {
            const archived = await fetchVehicles({ 'filter[archived]': 'only', 'page[size]': 1 });
            if (id !== requestId) return;
            hasArchivedVehicles.value = Boolean(archived.data?.length);
        }
        status.value = 'ready';
    } catch {
        if (id !== requestId) return;
        status.value = 'error';
        errorMessage.value = 'We could not load your vehicles. Check your connection and try again.';
    }
}

async function toggleArchived(vehicle) {
    pendingVehicleId.value = vehicle.id;

    try {
        if (attributes(vehicle).archived_at) {
            await restoreVehicle(vehicle.id);
        } else {
            await archiveVehicle(vehicle.id);
        }

        await loadVehicles();
    } catch {
        errorMessage.value = 'The vehicle could not be updated. Please try again.';
    } finally {
        pendingVehicleId.value = null;
    }
}

watch([archivedFilter, page], loadVehicles, { immediate: true });
onBeforeUnmount(() => {
    disposed = true;
    requestId++;
});
</script>

<template>
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Your garage</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Vehicles</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">Keep each vehicle’s identity and current mileage in one dependable place.</p>
            </div>
            <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="{ path: '/vehicles/new', query: { returnTo: route.fullPath } }">
                Add vehicle
            </RouterLink>
        </div>

        <div class="flex flex-wrap items-center gap-2" aria-label="Vehicle status filter">
            <button v-for="option in [{ value: 'active', label: 'Active' }, { value: 'archived', label: 'Archived' }, { value: 'all', label: 'All' }]" :key="option.value" :aria-pressed="archivedFilter === option.value" class="min-h-11 rounded-full border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :class="archivedFilter === option.value ? 'border-sky-700 bg-sky-50 text-sky-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'" type="button" @click="router.push(garageLocation(option.value))">
                {{ option.label }}
            </button>
        </div>

        <div v-if="status === 'loading'" class="grid gap-4 sm:grid-cols-2" aria-live="polite" aria-busy="true" role="status">
            <span class="sr-only">Loading vehicles…</span>
            <div v-for="index in 2" :key="index" class="h-56 animate-pulse rounded-2xl border border-slate-200 bg-white" />
        </div>

        <div v-else-if="status === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <h2 class="font-semibold text-slate-950">Vehicles are unavailable</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ errorMessage }}</p>
            <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="loadVehicles">Try again</button>
        </div>

        <div v-else-if="!vehicles.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold text-slate-950">{{ archivedFilter === 'archived' ? 'No archived vehicles' : archivedFilter === 'active' && hasArchivedVehicles ? 'No active vehicles' : 'Your garage is ready' }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ archivedFilter === 'archived' ? 'Vehicles you archive will remain available here.' : archivedFilter === 'active' && hasArchivedVehicles ? 'Select Archived to view or restore a vehicle.' : 'Add your first vehicle to begin keeping its mileage and history together.' }}</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <p class="text-sm text-slate-600 sm:col-span-2" role="status">{{ pagination.total }} {{ pagination.total === 1 ? 'vehicle' : 'vehicles' }}</p>
            <p v-if="errorMessage" class="text-sm text-red-700 sm:col-span-2" role="alert">{{ errorMessage }}</p>
            <article v-for="vehicle in vehicles" :key="vehicle.id" class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-950"><RouterLink class="text-sky-800 underline-offset-4 hover:underline" :to="vehicleLocation(`/vehicles/${vehicle.id}`)">{{ attributes(vehicle).nickname }}</RouterLink></h2>
                            <span v-if="attributes(vehicle).archived_at" class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">Archived</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ attributes(vehicle).year }} {{ attributes(vehicle).make }} {{ attributes(vehicle).model }}<template v-if="attributes(vehicle).trim"> · {{ attributes(vehicle).trim }}</template></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Odometer</p>
                        <p class="mt-1 font-semibold tabular-nums text-slate-950">{{ formatOdometer(attributes(vehicle).current_odometer) }}</p>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div><dt class="text-slate-500">Latest MPG</dt><dd class="mt-1 font-medium text-slate-700">{{ formatMpg(attributes(vehicle).latest_mpg) }}</dd></div>
                    <div><dt class="text-slate-500">Average MPG</dt><dd class="mt-1 font-medium text-slate-700">{{ formatMpg(attributes(vehicle).average_mpg) }}</dd></div>
                    <div class="col-span-2"><dt class="text-slate-500">Most recent fill-up</dt><dd class="mt-1 font-medium text-slate-700">{{ formatFuelDate(attributes(vehicle).most_recent_fueled_at) }}</dd></div>
                </dl>

                <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    <RouterLink v-if="!attributes(vehicle).archived_at" class="inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800" :to="{ path: `/vehicles/${vehicle.id}/fuel-entries/new`, query: { returnTo: route.fullPath, garage: route.fullPath } }">Add fuel</RouterLink>
                    <RouterLink v-if="!attributes(vehicle).archived_at" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50" :to="{ path: `/vehicles/${vehicle.id}/maintenance-events/new`, query: { returnTo: route.fullPath, garage: route.fullPath } }">Add maintenance</RouterLink>
                    <RouterLink v-if="!attributes(vehicle).archived_at" class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="{ path: `/vehicles/${vehicle.id}/edit`, query: { returnTo: route.fullPath, garage: route.fullPath } }">Edit</RouterLink>
                    <button :disabled="Boolean(pendingVehicleId)" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:opacity-50" type="button" @click="toggleArchived(vehicle)">{{ attributes(vehicle).archived_at ? 'Restore' : 'Archive' }}</button>
                </div>
            </article>
        </div>
        <nav v-if="status === 'ready' && pagination.lastPage > 1" class="flex items-center justify-between gap-4" aria-label="Vehicle pages">
            <button class="min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-50" :disabled="page === 1" @click="router.push(garageLocation(archivedFilter, page - 1))">Previous</button>
            <span class="text-sm text-slate-600">Page {{ page }} of {{ pagination.lastPage }}</span>
            <button class="min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-50" :disabled="page >= pagination.lastPage" @click="router.push(garageLocation(archivedFilter, page + 1))">Next</button>
        </nav>
    </section>
</template>
