<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { fetchVehicleDashboard, fetchVehicleTimeline } from '../api/dashboard';
import { formatDateTime, formatMoney, formatMpg, formatOdometer, paginationFrom, resourceAttributes } from '../utils/fuel';
import { POSITION_OPTIONS, serviceLabel } from '../utils/maintenance';

const route = useRoute();
const dashboard = ref(null);
const timeline = ref([]);
const recentTimeline = ref([]);
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

function normalizeTimelineResource(item) {
    return resourceAttributes(item);
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

function load() {
    status.value = 'loading';
    timelineStatus.value = 'loading';
    errorMessage.value = '';
    timelineErrorMessage.value = '';

    return Promise.all([
        fetchVehicleDashboard(route.params.vehicle),
        fetchVehicleTimeline(route.params.vehicle, 1),
    ]).then(([dashboardResponse, timelineResponse]) => {
        dashboard.value = dashboardResponse;
        recentTimeline.value = (dashboardAttributes.value.recent_timeline ?? []).map(normalizeTimelineResource);
        timeline.value = timelineResponse.data ?? [];
        pagination.value = { ...pagination.value, ...paginationFrom(timelineResponse), currentPage: 1 };
        status.value = 'ready';
        timelineStatus.value = 'ready';
    }).catch(() => {
        status.value = 'error';
        timelineStatus.value = 'error';
        errorMessage.value = 'We could not load this dashboard. Check your connection and try again.';
    });
}

async function loadPage(page, force = false) {
    if (page < 1 || page > pagination.value.lastPage || (page === pagination.value.currentPage && !force)) {
        return;
    }

    timelineStatus.value = 'loading';
    timelineErrorMessage.value = '';

    try {
        const response = await fetchVehicleTimeline(route.params.vehicle, page);
        timeline.value = response.data ?? [];
        pagination.value = { ...pagination.value, ...paginationFrom(response), currentPage: page };
        timelineStatus.value = 'ready';
    } catch {
        timelineStatus.value = 'error';
        timelineErrorMessage.value = 'We could not load this page of the timeline. Please try again.';
    }
}

onMounted(load);
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
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <RouterLink class="text-sm font-semibold text-sky-700 hover:text-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" to="/">← Vehicles</RouterLink>
                    <p class="mt-5 text-sm font-semibold tracking-wide text-sky-700 uppercase">Vehicle dashboard</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">{{ vehicle.nickname || 'Vehicle dashboard' }}</h1>
                    <p class="mt-2 text-base leading-7 text-slate-600">A clear view of the vehicle’s mileage, fuel economy, service recency, and activity.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/fuel-entries/new`">Add fuel</RouterLink>
                    <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/maintenance-events/new`">Add maintenance</RouterLink>
                </div>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="card in [
                    { label: 'Odometer', value: formatOdometer(vehicle.current_odometer) },
                    { label: 'Latest MPG', value: formatMpg(fuelSummary.latest_mpg) },
                    { label: 'Average MPG', value: formatMpg(fuelSummary.average_mpg) },
                    { label: 'Most recent fuel', value: formatDateTime(fuelSummary.most_recent_fueled_at) },
                    { label: 'Most recent maintenance', value: formatDateTime(maintenanceSummary.most_recent_performed_at) },
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

            <section class="grid gap-4" aria-labelledby="recent-timeline-heading">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Recent activity</p>
                    <h2 id="recent-timeline-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Latest fuel and maintenance</h2>
                </div>
                <div v-if="recentTimeline.length" class="grid gap-3">
                    <article v-for="(item, index) in recentTimeline" :key="item.id ?? index" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase">{{ item.kind === 'maintenance' ? 'Maintenance' : 'Fuel' }}</p>
                            <h3 class="mt-1 font-semibold text-slate-950">{{ item.title }}</h3>
                            <p v-if="timelineDetails(normalizeTimelineResource(item))" class="mt-1 text-sm text-slate-600">{{ timelineDetails(normalizeTimelineResource(item)) }}</p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-sm font-medium text-slate-800">{{ formatDateTime(item.occurred_at) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ formatOdometer(item.odometer) }} · {{ formatMoney(item.cost) }}</p>
                        </div>
                    </article>
                </div>
                <p v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm leading-6 text-slate-600">No recent activity yet. Add a fuel-up or maintenance visit to start the timeline.</p>
            </section>

            <section class="grid gap-4" aria-labelledby="timeline-heading">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Full history</p>
                    <h2 id="timeline-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Combined timeline</h2>
                </div>
                <div v-if="timelineStatus === 'loading'" class="rounded-2xl border border-slate-200 bg-white p-6" aria-busy="true" aria-live="polite" role="status">Loading timeline…</div>
                <div v-else-if="timelineStatus === 'error'" class="rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
                    <p class="text-sm leading-6 text-slate-700">{{ timelineErrorMessage }}</p>
                    <button class="mt-4 min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="loadPage(pagination.currentPage, true)">Try again</button>
                </div>
                <p v-else-if="isEmptyTimeline" class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm leading-6 text-slate-600">No activity has been recorded for this vehicle yet.</p>
                <div v-else class="grid gap-3">
                    <article v-for="(item, index) in timeline" :key="item.id ?? index" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase">{{ normalizeTimelineResource(item).kind === 'maintenance' ? 'Maintenance' : 'Fuel' }}</p>
                            <h3 class="mt-1 font-semibold text-slate-950">{{ normalizeTimelineResource(item).title }}</h3>
                            <p v-if="timelineDetails(normalizeTimelineResource(item))" class="mt-1 text-sm text-slate-600">{{ timelineDetails(normalizeTimelineResource(item)) }}</p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-sm font-medium text-slate-800">{{ formatDateTime(normalizeTimelineResource(item).occurred_at) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ formatOdometer(normalizeTimelineResource(item).odometer) }} · {{ formatMoney(normalizeTimelineResource(item).cost) }}</p>
                        </div>
                    </article>
                    <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-4" aria-label="Combined timeline pagination">
                        <button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canPrevious" @click="loadPage(pagination.currentPage - 1)">Previous</button>
                        <p class="text-sm text-slate-600">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</p>
                        <button class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="!canNext" @click="loadPage(pagination.currentPage + 1)">Next</button>
                    </nav>
                </div>
            </section>
        </template>
    </section>
</template>
