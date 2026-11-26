<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { fetchVehicleDashboard, fetchVehicleTimeline } from '../api/dashboard';
import VehicleNav from '../components/VehicleNav.vue';
import { formatFuelDate, formatMoney, formatMpg, formatOdometer, paginationFrom, resourceAttributes } from '../utils/fuel';
import { POSITION_OPTIONS, serviceLabel } from '../utils/maintenance';

const route = useRoute();
const router = useRouter();
const page = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1));
const dashboard = ref(null);
const timeline = ref([]);
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 15, total: 0 });
const status = ref('loading');
const timelineStatus = ref('ready');
const errorMessage = ref('');
const timelineErrorMessage = ref('');

const dashboardAttributes = computed(() => resourceAttributes(dashboard.value));
const vehicle = computed(() => resourceAttributes(dashboardAttributes.value.vehicle));
const fuelSummary = computed(() => dashboardAttributes.value.fuel_summary ?? {});
const maintenanceSummary = computed(() => dashboardAttributes.value.maintenance_summary ?? {});
const serviceRecency = computed(() => dashboardAttributes.value.service_recency ?? []);
const isEmptyTimeline = computed(() => status.value === 'ready' && timeline.value.length === 0);
const canPrevious = computed(() => pagination.value.currentPage > 1);
const canNext = computed(() => pagination.value.currentPage < pagination.value.lastPage);

function recordLocation(item) {
    const section = item.kind === 'maintenance' ? 'maintenance-events' : 'fuel-entries';
    return { path: `/${section}/${item.id}`, query: { returnTo: route.fullPath, garage: route.query.garage } };
}

function formLocation(section) {
    return { path: `/vehicles/${route.params.vehicle}/${section}/new`, query: { returnTo: route.fullPath, garage: route.query.garage } };
}

function pageLocation(number) {
    return { path: route.path, query: { ...route.query, page: number > 1 ? number : undefined } };
}

function serviceRecencyItems(value) {
    if (Array.isArray(value)) {
        return value;
    }

    return Object.entries(value).map(([key, item]) => ({
        ...(typeof item === 'object' && item !== null ? item : { miles_since_service: item }),
        key,
    }));
}

function recencyLabel(item) {
    const normalized = item ?? {};
    const base = normalized.label ?? normalized.title ?? normalized.name ?? serviceLabel(normalized.service ?? normalized.key);
    const position = normalized.position ?? normalized.axle;
    const positionLabel = POSITION_OPTIONS.find((option) => option.value === position)?.label;

    return positionLabel && !base.toLowerCase().includes(positionLabel.toLowerCase()) ? `${positionLabel} ${base}` : base;
}

function milesSince(item) {
    const value = item?.miles_since_service ?? item?.miles_since ?? item?.miles;

    return value === null || value === undefined || value === '' ? 'Unavailable' : `${Number(value).toLocaleString('en-US')} miles ago`;
}

function timelineDetails(item) {
    const details = item.details ?? {};
    const services = Array.isArray(details.services) ? details.services.map((service) => service.label ?? service.type).filter(Boolean).join(' · ') : '';
    const values = [item.summary, services].filter(Boolean);

    return values.join(' · ');
}

let dashboardRequest = 0;
let timelineRequest = 0;

async function load() {
    const request = ++dashboardRequest;
    status.value = 'loading';
    try {
        const response = await fetchVehicleDashboard(route.params.vehicle);
        if (request !== dashboardRequest) return;
        dashboard.value = response;
        status.value = 'ready';
    } catch {
        if (request !== dashboardRequest) return;
        status.value = 'error';
        errorMessage.value = 'We could not load this dashboard. Check your connection and try again.';
    }
}

async function loadPage() {
    const request = ++timelineRequest;
    timelineStatus.value = 'loading';
    try {
        const response = await fetchVehicleTimeline(route.params.vehicle, page.value);
        if (request !== timelineRequest) return;
        pagination.value = paginationFrom(response);
        if (page.value > pagination.value.lastPage) {
            await router.replace(pageLocation(pagination.value.lastPage));
            return;
        }
        timeline.value = (response.data ?? []).map((record) => ({ ...resourceAttributes(record), id: record.id }));
        timelineStatus.value = 'ready';
    } catch {
        if (request !== timelineRequest) return;
        timelineStatus.value = 'error';
        timelineErrorMessage.value = 'We could not load this page of the timeline. Please try again.';
    }
}

watch(() => route.params.vehicle, load, { immediate: true });
watch([() => route.params.vehicle, page], loadPage, { immediate: true });
onBeforeUnmount(() => {
    dashboardRequest++;
    timelineRequest++;
});
</script>

<template>
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div v-if="status === 'loading'" class="grid gap-6" aria-busy="true" aria-live="polite" role="status">
            <span class="sr-only">Loading vehicle dashboard…</span>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="index in 5" :key="index" class="h-28 animate-pulse rounded-2xl border border-slate-200 bg-white" />
            </div>
            <div class="h-64 animate-pulse rounded-2xl border border-slate-200 bg-white" />
        </div>

        <div v-else-if="status === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
            <h1 class="font-semibold text-slate-950">Dashboard is unavailable</h1>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ errorMessage }}</p>
            <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="load">Try again</button>
        </div>

        <template v-else>
            <VehicleNav :vehicle-id="route.params.vehicle" :nickname="vehicle.nickname" :archived="Boolean(vehicle.archived_at)" />
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Overview</h1>
                <div v-if="!vehicle.archived_at" class="flex flex-wrap gap-2">
                    <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800" :to="formLocation('fuel-entries')">Add fuel</RouterLink>
                    <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50" :to="formLocation('maintenance-events')">Add maintenance</RouterLink>
                </div>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="card in [
                    { label: 'Odometer', value: formatOdometer(vehicle.current_odometer) },
                    { label: 'Latest MPG', value: formatMpg(fuelSummary.latest_mpg) },
                    { label: 'Average MPG', value: formatMpg(fuelSummary.average_mpg) },
                    { label: 'Most recent fuel', value: formatFuelDate(fuelSummary.most_recent_fueled_at) },
                    { label: 'Most recent maintenance', value: formatFuelDate(maintenanceSummary.most_recent_performed_at) },
                ]" :key="card.label" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ card.label }}</dt>
                    <dd class="font-semibold text-slate-950">{{ card.value }}</dd>
                </div>
            </dl>

            <section class="grid gap-4" aria-labelledby="service-recency-heading">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Service recency</p>
                    <h2 id="service-recency-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Miles since applicable services</h2>
                </div>
                <div v-if="serviceRecencyItems(serviceRecency).length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="(item, index) in serviceRecencyItems(serviceRecency)" :key="item.key ?? item.type ?? index" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-slate-950">{{ recencyLabel(item) }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ milesSince(item) }}</p>
                    </article>
                </div>
                <p v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm leading-6 text-slate-600">Service recency is unavailable until service history has been calculated.</p>
            </section>

            <section class="grid gap-4" aria-labelledby="timeline-heading">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Full history</p>
                    <h2 id="timeline-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Combined timeline</h2>
                </div>
                <div v-if="timelineStatus === 'loading'" class="rounded-2xl border border-slate-200 bg-white p-6" aria-busy="true" aria-live="polite" role="status">Loading timeline…</div>
                <div v-else-if="timelineStatus === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
                    <p class="text-sm leading-6 text-slate-700">{{ timelineErrorMessage }}</p>
                    <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="loadPage">Try again</button>
                </div>
                <p v-else-if="isEmptyTimeline" class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm leading-6 text-slate-600">No activity has been recorded for this vehicle yet.</p>
                <div v-else class="grid gap-3">
                    <article v-for="(item, index) in timeline" :key="item.id ?? index" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase">{{ item.kind === 'maintenance' ? 'Maintenance' : 'Fuel' }}</p>
                            <h3 class="mt-1 font-semibold text-slate-950"><RouterLink class="text-sky-800 underline underline-offset-4 hover:text-sky-950" :to="recordLocation(item)">{{ item.title }}</RouterLink></h3>
                            <p v-if="timelineDetails(item)" class="mt-1 text-sm text-slate-600">{{ timelineDetails(item) }}</p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-sm font-medium text-slate-800">{{ formatFuelDate(item.occurred_at) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ formatOdometer(item.odometer) }} · {{ formatMoney(item.cost) }}</p>
                        </div>
                    </article>
                    <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-4" aria-label="Combined timeline pagination">
                        <button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canPrevious" @click="router.push(pageLocation(page - 1))">Previous</button>
                        <p class="text-sm text-slate-600">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</p>
                        <button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canNext" @click="router.push(pageLocation(page + 1))">Next</button>
                    </nav>
                </div>
            </section>
        </template>
    </section>
</template>
