<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { deleteMaintenanceEvent, fetchMaintenanceEvents } from '../api/maintenance';
import { fetchVehicle } from '../api/vehicles';
import VehicleNav from '../components/VehicleNav.vue';
import { paginationFrom, resourceAttributes } from '../utils/fuel';
import { formatMaintenanceDate, formatMaintenanceMoney, formatMaintenanceOdometer, serviceLabel, serviceResourceAttributes, serviceSummary } from '../utils/maintenance';

const route = useRoute();
const router = useRouter();
const vehicle = ref(null);
const heading = ref(null);
const dialog = ref(null);
const cancelButton = ref(null);
const pagination = ref({ lastPage: 1, total: 0 });
const page = computed(() => {
    const number = Number(route.query.page);
    return Number.isSafeInteger(number) && number > 0 ? number : 1;
});
let latestLoad = 0;
let deleteTrigger;
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

function pageLocation(number) {
    return { path: route.path, query: { ...route.query, page: number === 1 ? undefined : number } };
}

async function load() {
    const request = ++latestLoad;
    const vehicleId = route.params.vehicle;
    const currentPage = page.value;
    status.value = 'loading';
    errorMessage.value = '';

    try {
        const [owner, response] = await Promise.all([
            vehicle.value?.id === vehicleId ? vehicle.value : fetchVehicle(vehicleId),
            fetchMaintenanceEvents(vehicleId, { 'page[number]': currentPage }),
        ]);
        if (request !== latestLoad) return;

        vehicle.value = owner;
        pagination.value = paginationFrom(response);
        const lastPage = Math.max(1, pagination.value.lastPage);
        if (currentPage > lastPage) {
            await router.replace(pageLocation(lastPage));
            return;
        }

        events.value = response.data ?? [];
        status.value = 'ready';
    } catch {
        if (request !== latestLoad) return;
        status.value = 'error';
        errorMessage.value = 'We could not load this maintenance history. Check your connection and try again.';
    }
}

async function askToDelete(id, trigger) {
    errorMessage.value = '';
    deleteTrigger = trigger;
    pendingDeleteId.value = id;
    await nextTick();
    dialog.value.showModal();
    cancelButton.value.focus();
}

function cancelDelete() {
    if (deletingId.value) return;
    dialog.value.close();
    pendingDeleteId.value = null;
    deleteTrigger?.focus();
}

async function confirmDelete(id) {
    const request = latestLoad;
    deletingId.value = id;
    errorMessage.value = '';

    try {
        await deleteMaintenanceEvent(id);
        if (request !== latestLoad) return;
        dialog.value.close();
        pendingDeleteId.value = null;
        await load();
        await nextTick();
        heading.value?.focus();
    } catch {
        errorMessage.value = 'The maintenance event could not be deleted. Please try again.';
    } finally {
        deletingId.value = null;
    }
}

onBeforeUnmount(() => { latestLoad++; });
watch([() => route.params.vehicle, page], load, { immediate: true });
</script>

<template>
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <VehicleNav v-if="vehicle" :vehicle-id="vehicle.id" :nickname="vehicleAttributes.nickname" :archived="isArchived" />
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 ref="heading" tabindex="-1" class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Maintenance history</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">Chronological visits with the services, mileage, and costs recorded at each stop.</p>
            </div>
            <RouterLink v-if="vehicle && !isArchived" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="{ path: `/vehicles/${route.params.vehicle}/maintenance-events/new`, query: { garage: route.query.garage, returnTo: route.fullPath } }">Add maintenance</RouterLink>
        </div>

        <div v-if="status === 'loading'" class="rounded-2xl border border-slate-200 bg-white p-8" aria-busy="true" aria-live="polite" role="status">Loading maintenance history…</div>
        <div v-else-if="status === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
            <h2 class="font-semibold text-slate-950">Maintenance history is unavailable</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ errorMessage }}</p>
            <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="load">Try again</button>
        </div>
        <div v-else-if="isEmpty" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold text-slate-950">No maintenance visits yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">{{ isArchived ? 'No visits were recorded for this archived vehicle.' : 'Record your first service visit to begin this history.' }}</p>
            <RouterLink v-if="vehicle && !isArchived" class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="{ path: `/vehicles/${route.params.vehicle}/maintenance-events/new`, query: { garage: route.query.garage, returnTo: route.fullPath } }">Add maintenance</RouterLink>
        </div>
        <div v-else class="grid gap-4">
            <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ errorMessage }}</p>
            <p class="text-sm text-slate-600">{{ pagination.total || events.length }} {{ (pagination.total || events.length) === 1 ? 'visit' : 'visits' }}</p>
            <article v-for="event in events" :key="event.id" class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">{{ formatMaintenanceDate(eventAttributes(event).performed_at) }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950"><RouterLink class="text-sky-800 underline-offset-4 hover:underline" :to="{ path: `/maintenance-events/${event.id}`, query: { garage: route.query.garage, returnTo: route.fullPath } }">Maintenance visit<span class="sr-only"> on {{ formatMaintenanceDate(eventAttributes(event).performed_at) }}</span></RouterLink></h2>
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
                <div v-if="!isArchived" class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                    <RouterLink class="inline-flex min-h-11 items-center rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="{ path: `/maintenance-events/${event.id}/edit`, query: { garage: route.query.garage, returnTo: route.fullPath } }">Edit</RouterLink>
                    <button class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" @click="askToDelete(event.id, $event.currentTarget)">Delete</button>
                </div>
            </article>
            <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-3" aria-label="Maintenance history pagination">
                <RouterLink v-if="page > 1" class="rounded-lg border px-4 py-2" :to="pageLocation(page - 1)">Previous</RouterLink>
                <span class="text-sm text-slate-600">Page {{ page }} of {{ pagination.lastPage }}</span>
                <RouterLink v-if="page < pagination.lastPage" class="rounded-lg border px-4 py-2" :to="pageLocation(page + 1)">Next</RouterLink>
            </nav>
        </div>

        <dialog v-if="pendingDeleteId" ref="dialog" class="fixed inset-0 m-auto w-11/12 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl backdrop:bg-slate-950/40" role="alertdialog" @cancel.prevent="cancelDelete" aria-modal="true" aria-labelledby="delete-maintenance-title" aria-describedby="delete-maintenance-description">
            <h2 id="delete-maintenance-title" class="text-lg font-semibold text-slate-950">Delete this maintenance visit?</h2>
            <p id="delete-maintenance-description" class="mt-2 text-sm leading-6 text-slate-600">This removes the visit and its service lines from the vehicle history. This action cannot be undone.</p>
            <p v-if="errorMessage" class="mt-3 text-sm text-red-700" role="alert">{{ errorMessage }}</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button ref="cancelButton" :disabled="Boolean(deletingId)" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="cancelDelete">Cancel</button>
                <button :disabled="deletingId === pendingDeleteId" class="min-h-11 rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:opacity-60" type="button" @click="confirmDelete(pendingDeleteId)">{{ deletingId === pendingDeleteId ? 'Deleting…' : 'Delete visit' }}</button>
            </div>
        </dialog>
    </section>
</template>
