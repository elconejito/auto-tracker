<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { deleteMaintenanceEvent, fetchMaintenanceEvents } from '../api/maintenance';
import { fetchVehicle } from '../api/vehicles';
import { resourceAttributes } from '../utils/fuel';
import { formatMaintenanceDate, formatMaintenanceMoney, formatMaintenanceOdometer, serviceLabel, serviceResourceAttributes, serviceSummary } from '../utils/maintenance';

const route = useRoute();
const vehicle = ref({});
const events = ref([]);
const status = ref('loading');
const errorMessage = ref('');
const deletingId = ref(null);
const pendingDeleteId = ref(null);

const vehicleAttributes = computed(() => resourceAttributes(vehicle.value));
const isArchived = computed(() => Boolean(vehicleAttributes.value.archived_at));
const isEmpty = computed(() => status.value === 'ready' && events.value.length === 0);

function eventAttributes(event) {
    return resourceAttributes(event);
}

function servicesFor(event) {
    const attributes = eventAttributes(event);

    return attributes.services ?? attributes.service_lines ?? event.relationships?.services?.data ?? [];
}

function serviceDetails(service) {
    return serviceResourceAttributes(service).details ?? {};
}

function loadEvents() {
    status.value = 'loading';
    errorMessage.value = '';

    return fetchMaintenanceEvents(route.params.vehicle, { sort: '-performed_at' })
        .then((response) => {
            events.value = response.data ?? [];
            status.value = 'ready';
        })
        .catch(() => {
            status.value = 'error';
            errorMessage.value = 'We could not load this maintenance history. Check your connection and try again.';
        });
}

async function load() {
    status.value = 'loading';
    errorMessage.value = '';

    try {
        vehicle.value = await fetchVehicle(route.params.vehicle);
        await loadEvents();
    } catch {
        status.value = 'error';
        errorMessage.value = 'We could not load this vehicle. Please try again.';
    }
}

function askToDelete(id) {
    pendingDeleteId.value = id;
}

function cancelDelete() {
    pendingDeleteId.value = null;
}

async function confirmDelete(id) {
    deletingId.value = id;
    errorMessage.value = '';

    try {
        await deleteMaintenanceEvent(id);
        pendingDeleteId.value = null;
        await loadEvents();
    } catch {
        errorMessage.value = 'The maintenance event could not be deleted. Please try again.';
    } finally {
        deletingId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Maintenance tracking</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ vehicleAttributes.nickname || 'Maintenance history' }}</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">Chronological visits with the services, mileage, and costs recorded at each stop.</p>
            </div>
            <RouterLink v-if="!isArchived" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/maintenance-events/new`">Add maintenance</RouterLink>
        </div>

        <div v-if="status === 'loading'" class="rounded-2xl border border-slate-200 bg-white p-8" aria-busy="true" aria-live="polite" role="status">Loading maintenance history…</div>
        <div v-else-if="status === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
            <h2 class="font-semibold text-slate-950">Maintenance history is unavailable</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ errorMessage }}</p>
            <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="load">Try again</button>
        </div>
        <div v-else-if="isEmpty" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold text-slate-950">No maintenance visits yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">Record your first service visit to keep this vehicle’s maintenance history together.</p>
            <RouterLink v-if="!isArchived" class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/maintenance-events/new`">Add maintenance</RouterLink>
        </div>
        <div v-else class="grid gap-4">
            <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ errorMessage }}</p>
            <article v-for="event in events" :key="event.id" class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">{{ formatMaintenanceDate(eventAttributes(event).performed_at) }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">Maintenance visit</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ formatMaintenanceOdometer(eventAttributes(event).odometer) }}<template v-if="eventAttributes(event).provider"> · {{ eventAttributes(event).provider }}</template></p>
                    </div>
                    <dl class="text-left sm:text-right"><dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Visit total</dt><dd class="mt-1 font-semibold text-slate-950">{{ formatMaintenanceMoney(eventAttributes(event).total_cost) }}</dd></dl>
                </div>

                <ul class="grid gap-3" :aria-label="`Services performed on ${formatMaintenanceDate(eventAttributes(event).performed_at)}`">
                    <li v-for="(service, index) in servicesFor(event)" :key="service.id ?? `${event.id}-${index}`" class="flex flex-col gap-1 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="font-medium text-slate-950">{{ serviceLabel(serviceResourceAttributes(service).type) }}</p><p v-if="serviceSummary(service)" class="mt-1 text-sm text-slate-600">{{ serviceSummary(service) }}</p><p v-if="serviceDetails(service).description || serviceDetails(service).notes" class="mt-1 text-sm leading-6 text-slate-600">{{ serviceDetails(service).description || serviceDetails(service).notes }}</p></div>
                        <span class="text-sm font-medium text-slate-700">{{ formatMaintenanceMoney(serviceResourceAttributes(service).line_cost) }}</span>
                    </li>
                </ul>

                <p v-if="eventAttributes(event).notes" class="text-sm leading-6 text-slate-600">{{ eventAttributes(event).notes }}</p>
                <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    <RouterLink v-if="!isArchived" class="inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/maintenance-events/${event.id}/edit?vehicle=${route.params.vehicle}`">Edit</RouterLink>
                    <button v-if="!isArchived" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" @click="askToDelete(event.id)">Delete</button>
                </div>
            </article>
        </div>

        <div v-if="pendingDeleteId" class="fixed inset-0 z-10 grid place-items-center bg-slate-950/40 px-4" role="presentation">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl" role="alertdialog" aria-modal="true" aria-labelledby="delete-maintenance-title" aria-describedby="delete-maintenance-description">
                <h2 id="delete-maintenance-title" class="text-lg font-semibold text-slate-950">Delete this maintenance visit?</h2>
                <p id="delete-maintenance-description" class="mt-2 text-sm leading-6 text-slate-600">This removes the visit and its service lines from the vehicle history. This action cannot be undone.</p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="cancelDelete">Cancel</button>
                    <button :disabled="deletingId === pendingDeleteId" class="min-h-11 rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-60" type="button" @click="confirmDelete(pendingDeleteId)">{{ deletingId === pendingDeleteId ? 'Deleting…' : 'Delete visit' }}</button>
                </div>
            </div>
        </div>
    </section>
</template>
