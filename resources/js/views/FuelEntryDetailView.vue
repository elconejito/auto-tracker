<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { messageFrom } from '../api/errors';
import { fetchFuelEntry } from '../api/fuel';
import { fetchVehicle } from '../api/vehicles';
import VehicleNav from '../components/VehicleNav.vue';
import { formatFuelDate, formatGallons, formatMoney, formatMpg, formatOdometer, resourceAttributes } from '../utils/fuel';
import { returnPath } from '../utils/navigation';

const route = useRoute();
const vehicle = ref(null);
const entry = ref(null);
const errorMessage = ref('');
const loading = ref(true);
const vehicleAttributes = computed(() => resourceAttributes(vehicle.value));
const archived = computed(() => Boolean(vehicleAttributes.value.archived_at));
const attributes = computed(() => resourceAttributes(entry.value));
const backTo = computed(() => returnPath(route.query.returnTo, vehicle.value ? `/vehicles/${vehicle.value.id}/fuel-entries` : '/'));
const fields = computed(() => [
    ['Odometer', formatOdometer(attributes.value.odometer)],
    ['Gallons', formatGallons(attributes.value.gallons)],
    ['Total cost', formatMoney(attributes.value.total_cost)],
    ['Price per gallon', attributes.value.price_per_gallon == null ? 'Unavailable' : `$${attributes.value.price_per_gallon}`],
    ['Miles driven', formatOdometer(attributes.value.miles_driven)],
    ['Fuel economy', formatMpg(attributes.value.calculated_mpg)],
    ['Station', attributes.value.station || 'Unspecified'],
    ['Fuel grade', attributes.value.fuel_grade || 'Unspecified'],
]);

async function load() {
    loading.value = true;
    errorMessage.value = '';
    try {
        entry.value = await fetchFuelEntry(route.params.fuelEntry);
        vehicle.value = await fetchVehicle(entry.value.relationships?.vehicle?.data?.id ?? attributes.value.vehicle_id);
    } catch (error) {
        errorMessage.value = messageFrom(error, 'We could not load this fill-up.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="mx-auto grid max-w-4xl gap-6 px-4 py-8 sm:px-6 sm:py-12">
        <VehicleNav v-if="vehicle" :vehicle-id="vehicle.id" :nickname="vehicleAttributes.nickname" :archived="archived" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Fuel fill-up</h1>
                <p v-if="entry" class="mt-2 text-slate-600">{{ formatFuelDate(attributes.fueled_at) }}</p>
            </div>
            <RouterLink v-if="vehicle && !archived" :to="{ path: `/fuel-entries/${entry.id}/edit`, query: { returnTo: route.fullPath, garage: route.query.garage } }" class="rounded-lg bg-sky-700 px-4 py-3 text-sm font-semibold text-white">Edit fill-up</RouterLink>
        </div>
        <p v-if="loading" role="status">Loading fill-up…</p>
        <div v-else-if="errorMessage" role="alert" class="rounded-lg bg-amber-50 p-5">
            <p>{{ errorMessage }}</p>
            <button type="button" class="mt-2 min-h-11 font-semibold text-sky-800" @click="load">Try again</button>
        </div>
        <dl v-else class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
            <div v-for="[label, value] in fields" :key="label">
                <dt class="text-sm text-slate-600">{{ label }}</dt>
                <dd class="mt-1 font-medium">{{ value }}</dd>
            </div>
            <div v-if="attributes.notes" class="sm:col-span-2"><dt class="text-sm text-slate-600">Notes</dt><dd class="mt-1 whitespace-pre-wrap">{{ attributes.notes }}</dd></div>
        </dl>
        <RouterLink :to="backTo" class="w-fit rounded-lg px-3 py-2 text-sm font-semibold text-sky-800">Back</RouterLink>
    </section>
</template>
