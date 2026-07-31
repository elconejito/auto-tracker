<script setup>
import { computed, onMounted, ref } from 'vue';

import { archiveVehicle, fetchVehicles, restoreVehicle } from '../api/vehicles';

const vehicles = ref([]);
const status = ref('loading');
const errorMessage = ref('');
const archivedFilter = ref('without');
const pendingVehicleId = ref(null);

const isEmpty = computed(() => status.value === 'ready' && vehicles.value.length === 0);

function attributes(vehicle) {
    return vehicle.attributes ?? vehicle;
}

function formatOdometer(value) {
    return Number(value).toLocaleString('en-US');
}

async function loadVehicles() {
    status.value = 'loading';
    errorMessage.value = '';

    try {
        const response = await fetchVehicles({
            'filter[archived]': archivedFilter.value,
            sort: 'nickname',
        });
        vehicles.value = response.data ?? [];
        status.value = 'ready';
    } catch {
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

onMounted(loadVehicles);
</script>

<template>
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Your garage</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Vehicles</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">Keep each vehicle’s identity and current mileage in one dependable place.</p>
            </div>
            <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" to="/vehicles/new">
                Add vehicle
            </RouterLink>
        </div>

        <div class="flex flex-wrap items-center gap-2" aria-label="Vehicle status filter">
            <button v-for="option in [{ value: 'without', label: 'Active' }, { value: 'only', label: 'Archived' }, { value: 'with', label: 'All' }]" :key="option.value" :aria-pressed="archivedFilter === option.value" class="min-h-11 rounded-full border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :class="archivedFilter === option.value ? 'border-sky-700 bg-sky-50 text-sky-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'" type="button" @click="archivedFilter = option.value; loadVehicles()">
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

        <div v-else-if="isEmpty" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold text-slate-950">{{ archivedFilter === 'only' ? 'No archived vehicles' : 'Your garage is ready' }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ archivedFilter === 'only' ? 'Vehicles you archive will remain available here.' : 'Add your first vehicle to begin keeping its mileage and history together.' }}</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <article v-for="vehicle in vehicles" :key="vehicle.id" class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-950">{{ attributes(vehicle).nickname }}</h2>
                            <span v-if="attributes(vehicle).archived_at" class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">Archived</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ attributes(vehicle).year }} {{ attributes(vehicle).make }} {{ attributes(vehicle).model }}<template v-if="attributes(vehicle).trim"> · {{ attributes(vehicle).trim }}</template></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Odometer</p>
                        <p class="mt-1 font-semibold tabular-nums text-slate-950">{{ formatOdometer(attributes(vehicle).current_odometer) }} mi</p>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div><dt class="text-slate-500">Latest MPG</dt><dd class="mt-1 font-medium text-slate-700">Unavailable</dd></div>
                    <div><dt class="text-slate-500">Recent service</dt><dd class="mt-1 font-medium text-slate-700">Unavailable</dd></div>
                </dl>

                <p v-if="errorMessage" class="text-sm text-red-700" role="alert">{{ errorMessage }}</p>
                <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    <RouterLink v-if="!attributes(vehicle).archived_at" class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${vehicle.id}/edit`">Edit</RouterLink>
                    <button :disabled="pendingVehicleId === vehicle.id" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:opacity-50" type="button" @click="toggleArchived(vehicle)">{{ attributes(vehicle).archived_at ? 'Restore' : 'Archive' }}</button>
                </div>
            </article>
        </div>
    </section>
</template>
