<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';

import { deleteFuelEntry, fetchFuelEntries } from '../api/fuel';
import { fetchVehicle } from '../api/vehicles';
import { formatDateTime, formatGallons, formatMoney, formatMpg, formatOdometer, paginationFrom, resourceAttributes } from '../utils/fuel';

const route = useRoute();
const vehicle = ref({});
const entries = ref([]);
const status = ref('loading');
const errorMessage = ref('');
const deletingId = ref(null);
const pendingDeleteId = ref(null);
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 10, total: 0 });
const filters = reactive({
    fueled_from: '',
    fueled_to: '',
    odometer_min: '',
    odometer_max: '',
    mpg_min: '',
    mpg_max: '',
    sort: '-fueled_at',
});

const vehicleAttributes = computed(() => resourceAttributes(vehicle.value));
const isArchived = computed(() => Boolean(vehicleAttributes.value.archived_at));
const isEmpty = computed(() => status.value === 'ready' && entries.value.length === 0);
const canPrevious = computed(() => pagination.value.currentPage > 1);
const canNext = computed(() => pagination.value.currentPage < pagination.value.lastPage);

function entryAttributes(entry) {
    return resourceAttributes(entry);
}

function requestParams(page = 1) {
    const params = { sort: filters.sort, 'page[number]': page, 'page[size]': filters.perPage || 10 };

    for (const key of ['fueled_from', 'fueled_to', 'odometer_min', 'odometer_max', 'mpg_min', 'mpg_max']) {
        if (filters[key] !== '') {
            params[`filter[${key}]`] = filters[key];
        }
    }

    return params;
}

async function loadEntries(page = 1) {
    status.value = 'loading';
    errorMessage.value = '';

    try {
        const response = await fetchFuelEntries(route.params.vehicle, requestParams(page));
        entries.value = response.data ?? [];
        pagination.value = { ...pagination.value, ...paginationFrom(response), currentPage: page };
        status.value = 'ready';
    } catch {
        status.value = 'error';
        errorMessage.value = 'We could not load this fuel history. Check your connection and try again.';
    }
}

async function load() {
    status.value = 'loading';

    try {
        vehicle.value = await fetchVehicle(route.params.vehicle);
        await loadEntries(1);
    } catch {
        status.value = 'error';
        errorMessage.value = 'We could not load this vehicle. Please try again.';
    }
}

function applyFilters() {
    loadEntries(1);
}

function clearFilters() {
    Object.assign(filters, { fueled_from: '', fueled_to: '', odometer_min: '', odometer_max: '', mpg_min: '', mpg_max: '', sort: '-fueled_at' });
    loadEntries(1);
}

function requestPage(page) {
    if (page >= 1 && page <= pagination.value.lastPage) {
        loadEntries(page);
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
        await deleteFuelEntry(id);
        pendingDeleteId.value = null;
        await loadEntries(pagination.value.currentPage);
    } catch {
        errorMessage.value = 'The fuel entry could not be deleted. Please try again.';
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
                <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Fuel tracking</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ vehicleAttributes.nickname || 'Fuel history' }}</h1>
                <p class="mt-2 max-w-2xl text-base leading-7 text-slate-600">Full fill-ups, odometer readings, and calculated fuel economy in chronological context.</p>
            </div>
            <RouterLink v-if="!isArchived" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/fuel-entries/new`">Add fuel</RouterLink>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form class="grid gap-5" @submit.prevent="applyFilters">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-950">Filter history</h2>
                    <button class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="clearFilters">Clear filters</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="fueled_from">From date<input id="fueled_from" v-model="filters.fueled_from" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="date" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="fueled_to">To date<input id="fueled_to" v-model="filters.fueled_to" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="date" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="odometer_min">Minimum odometer<input id="odometer_min" v-model="filters.odometer_min" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="number" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="odometer_max">Maximum odometer<input id="odometer_max" v-model="filters.odometer_max" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="number" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="mpg_min">Minimum MPG<input id="mpg_min" v-model="filters.mpg_min" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="number" step="0.1" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800" for="mpg_max">Maximum MPG<input id="mpg_max" v-model="filters.mpg_max" class="min-h-11 rounded-lg border border-slate-300 px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" type="number" step="0.1" /></label>
                    <label class="grid gap-2 text-sm font-medium text-slate-800 sm:col-span-2" for="fuel_sort">Sort by<select id="fuel_sort" v-model="filters.sort" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 font-normal text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100"><option value="-fueled_at">Newest first</option><option value="fueled_at">Oldest first</option><option value="-odometer">Highest odometer</option><option value="odometer">Lowest odometer</option><option value="-calculated_mpg">Highest MPG</option><option value="calculated_mpg">Lowest MPG</option></select></label>
                </div>
                <button class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="submit">Apply filters</button>
            </form>
        </div>

        <div v-if="status === 'loading'" class="rounded-2xl border border-slate-200 bg-white p-8" aria-busy="true" aria-live="polite" role="status">Loading fuel history…</div>
        <div v-else-if="status === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert"><h2 class="font-semibold text-slate-950">Fuel history is unavailable</h2><p class="mt-2 text-sm leading-6 text-slate-700">{{ errorMessage }}</p><button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="load">Try again</button></div>
        <div v-else-if="isEmpty" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><h2 class="text-lg font-semibold text-slate-950">No fuel entries match</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">Add a full fill-up or clear the filters to see more history.</p></div>
        <div v-else class="grid gap-4">
            <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ errorMessage }}</p>

            <div class="grid gap-4 sm:hidden">
                <article v-for="entry in entries" :key="entry.id" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-slate-950">{{ formatDateTime(entryAttributes(entry).fueled_at) }}</h2><p class="mt-1 text-sm text-slate-600">{{ formatOdometer(entryAttributes(entry).odometer) }}</p></div><span class="font-semibold text-slate-950">{{ formatMpg(entryAttributes(entry).calculated_mpg) }}</span></div>
                    <dl class="grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Gallons</dt><dd class="mt-1 font-medium text-slate-800">{{ formatGallons(entryAttributes(entry).gallons) }}</dd></div><div><dt class="text-slate-500">Miles driven</dt><dd class="mt-1 font-medium text-slate-800">{{ entryAttributes(entry).miles_driven ?? 'Unavailable' }}</dd></div><div><dt class="text-slate-500">Total cost</dt><dd class="mt-1 font-medium text-slate-800">{{ formatMoney(entryAttributes(entry).total_cost) }}</dd></div><div><dt class="text-slate-500">Price / gallon</dt><dd class="mt-1 font-medium text-slate-800">{{ formatMoney(entryAttributes(entry).price_per_gallon) }}</dd></div></dl>
                    <p v-if="entryAttributes(entry).miles_driven === null || entryAttributes(entry).miles_driven === undefined" class="rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-700">MPG unavailable — a prior full fill-up is required.</p>
                    <p v-if="entryAttributes(entry).station || entryAttributes(entry).fuel_grade" class="text-sm text-slate-600">{{ entryAttributes(entry).station || 'Station unavailable' }}<template v-if="entryAttributes(entry).fuel_grade"> · {{ entryAttributes(entry).fuel_grade }}</template></p>
                    <p v-if="entryAttributes(entry).notes" class="text-sm leading-6 text-slate-600">{{ entryAttributes(entry).notes }}</p>
                    <div v-if="!isArchived" class="flex flex-wrap gap-2 border-t border-slate-200 pt-3"><RouterLink class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/fuel-entries/${entry.id}/edit?vehicle=${route.params.vehicle}`">Edit</RouterLink><button class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="askToDelete(entry.id)">Delete</button></div>
                    <div v-if="pendingDeleteId === entry.id" class="grid gap-3 rounded-lg border border-red-200 bg-red-50 p-4" role="alertdialog" aria-label="Confirm fuel entry deletion"><p class="text-sm text-red-900">Delete this fuel entry? Later MPG calculations will be recalculated.</p><div class="flex flex-wrap gap-2"><button class="min-h-11 rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" :disabled="deletingId === entry.id" @click="confirmDelete(entry.id)">Confirm delete</button><button class="min-h-11 rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" @click="cancelDelete">Cancel</button></div></div>
                </article>
            </div>

            <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm sm:block"><table class="min-w-full text-left text-sm"><caption class="sr-only">Fuel history</caption><thead class="border-b border-slate-200 bg-slate-50 text-xs tracking-wide text-slate-500 uppercase"><tr><th class="px-5 py-4">Date</th><th class="px-5 py-4">Odometer</th><th class="px-5 py-4">Gallons</th><th class="px-5 py-4">Miles / MPG</th><th class="px-5 py-4">Cost</th><th class="px-5 py-4">Details</th><th class="px-5 py-4"><span class="sr-only">Actions</span></th></tr></thead><tbody class="divide-y divide-slate-200"><tr v-for="entry in entries" :key="entry.id" class="align-top"><td class="px-5 py-4 font-medium text-slate-950">{{ formatDateTime(entryAttributes(entry).fueled_at) }}</td><td class="px-5 py-4 text-slate-700">{{ formatOdometer(entryAttributes(entry).odometer) }}</td><td class="px-5 py-4 text-slate-700">{{ formatGallons(entryAttributes(entry).gallons) }}</td><td class="px-5 py-4 text-slate-700"><div>{{ entryAttributes(entry).miles_driven ?? 'Unavailable' }} mi</div><div class="mt-1 font-semibold">{{ formatMpg(entryAttributes(entry).calculated_mpg) }}</div><div v-if="entryAttributes(entry).miles_driven === null || entryAttributes(entry).miles_driven === undefined" class="mt-1 max-w-40 text-xs leading-5 text-slate-500">Prior full fill-up required</div></td><td class="px-5 py-4 text-slate-700"><div>{{ formatMoney(entryAttributes(entry).total_cost) }}</div><div class="mt-1 text-xs">{{ formatMoney(entryAttributes(entry).price_per_gallon) }} / gal</div></td><td class="max-w-48 px-5 py-4 text-slate-700"><div>{{ entryAttributes(entry).station || 'Station unavailable' }}</div><div v-if="entryAttributes(entry).fuel_grade" class="mt-1 text-xs">{{ entryAttributes(entry).fuel_grade }}</div><div v-if="entryAttributes(entry).notes" class="mt-1 text-xs leading-5">{{ entryAttributes(entry).notes }}</div></td><td class="px-5 py-4"><div v-if="!isArchived" class="flex flex-col items-end gap-2"><RouterLink class="rounded-lg px-2 py-1 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/fuel-entries/${entry.id}/edit?vehicle=${route.params.vehicle}`">Edit</RouterLink><button class="rounded-lg px-2 py-1 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" @click="askToDelete(entry.id)">Delete</button></div><div v-if="pendingDeleteId === entry.id" class="mt-2 grid min-w-52 gap-2 rounded-lg border border-red-200 bg-red-50 p-3" role="alertdialog" aria-label="Confirm fuel entry deletion"><p class="text-xs leading-5 text-red-900">Delete this entry?</p><button class="rounded-lg bg-red-700 px-2 py-1 text-xs font-semibold text-white" type="button" :disabled="deletingId === entry.id" @click="confirmDelete(entry.id)">Confirm</button><button class="rounded-lg border border-red-300 px-2 py-1 text-xs font-semibold text-red-800" type="button" @click="cancelDelete">Cancel</button></div></td></tr></tbody></table></div>

            <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-4" aria-label="Fuel history pagination"><button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canPrevious" @click="requestPage(pagination.currentPage - 1)">Previous</button><p class="text-sm text-slate-600">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</p><button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canNext" @click="requestPage(pagination.currentPage + 1)">Next</button></nav>
        </div>
    </section>
</template>
