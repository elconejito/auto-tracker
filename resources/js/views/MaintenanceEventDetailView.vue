<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import { fetchMaintenanceEvent } from '../api/maintenance';
import { fetchVehicle } from '../api/vehicles';
import VehicleNav from '../components/VehicleNav.vue';
import { resourceAttributes } from '../utils/fuel';
import { formatMaintenanceDate, formatMaintenanceMoney, formatMaintenanceOdometer, POSITION_OPTIONS, serviceLabel, serviceResourceAttributes } from '../utils/maintenance';
import { returnPath } from '../utils/navigation';

const route = useRoute();
const event = ref(null);
const vehicle = ref(null);
const status = ref('loading');
const visit = computed(() => resourceAttributes(event.value));
const owner = computed(() => resourceAttributes(vehicle.value));
const destination = computed(() => returnPath(route.query.returnTo, `/vehicles/${vehicle.value.id}/maintenance-events`));
let latestLoad = 0;

async function load() {
    const request = ++latestLoad;
    status.value = 'loading';
    try {
        const record = await fetchMaintenanceEvent(route.params.maintenanceEvent);
        const ownerId = record.relationships?.vehicle?.data?.id ?? resourceAttributes(record).vehicle_id;
        const owner = await fetchVehicle(ownerId);
        if (request !== latestLoad) return;

        event.value = record;
        vehicle.value = owner;
        status.value = 'ready';
    } catch {
        if (request === latestLoad) status.value = 'error';
    }
}

function detailsFor(service) {
    const { type, details } = serviceResourceAttributes(service);
    return Object.entries(details)
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => {
            let label = key.replaceAll('_', ' ');
            if (type === 'oil_change' && key === 'quantity') {
                label = 'Oil quantity (qt)';
            }
            if (key === 'filter_replaced') {
                value = [true, 1, '1', 'true'].includes(value) ? 'Yes' : 'No';
            }
            if (key === 'position') {
                value = POSITION_OPTIONS.find(option => option.value === value)?.label ?? value;
            }
            return { label: label[0].toUpperCase() + label.slice(1), value };
        });
}

watch(() => route.params.maintenanceEvent, load, { immediate: true });
</script>

<template>
    <section class="mx-auto grid max-w-4xl gap-6 px-4 py-8 sm:px-6 sm:py-12">
        <p v-if="status === 'loading'" role="status">Loading maintenance visit…</p>
        <div v-else-if="status === 'error'" class="rounded-xl border border-amber-200 bg-amber-50 p-6" role="alert">
            We could not load this visit. <button class="underline" type="button" @click="load">Try again</button>
        </div>
        <template v-else>
            <VehicleNav :vehicle-id="vehicle.id" :nickname="owner.nickname" :archived="Boolean(owner.archived_at)" />
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Maintenance visit</h1>
                    <p class="mt-2 text-slate-600">{{ formatMaintenanceDate(visit.performed_at) }} · {{ formatMaintenanceOdometer(visit.odometer) }}</p>
                </div>
                <RouterLink v-if="!owner.archived_at" class="rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white" :to="{ path: `/maintenance-events/${event.id}/edit`, query: { garage: route.query.garage, returnTo: route.fullPath } }">Edit visit</RouterLink>
            </div>
            <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div v-if="visit.provider"><dt class="text-sm text-slate-600">Provider</dt><dd>{{ visit.provider }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Visit total</dt><dd>{{ formatMaintenanceMoney(visit.total_cost) }}</dd></div>
                    <div v-if="visit.notes" class="sm:col-span-2"><dt class="text-sm text-slate-600">Visit notes</dt><dd class="whitespace-pre-wrap">{{ visit.notes }}</dd></div>
                </dl>
            </div>
            <section class="grid gap-3" aria-labelledby="visit-services">
                <h2 id="visit-services" class="text-xl font-semibold">Services performed</h2>
                <article v-for="(service, index) in visit.services" :key="service.id ?? index" class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 class="font-semibold">{{ serviceLabel(serviceResourceAttributes(service).type) }}</h3>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-sm text-slate-600">Line cost</dt><dd>{{ formatMaintenanceMoney(serviceResourceAttributes(service).line_cost) }}</dd></div>
                        <div v-for="field in detailsFor(service)" :key="field.label"><dt class="text-sm text-slate-600">{{ field.label }}</dt><dd class="whitespace-pre-wrap break-words">{{ field.value }}</dd></div>
                    </dl>
                </article>
            </section>
            <RouterLink class="w-fit rounded-lg border border-slate-300 bg-white px-4 py-2 font-semibold" :to="destination">Back</RouterLink>
        </template>
    </section>
</template>
