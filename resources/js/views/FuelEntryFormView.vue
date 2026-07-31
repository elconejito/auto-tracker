<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { fetchFuelEntry, createFuelEntry, updateFuelEntry } from '../api/fuel';
import { fetchVehicle } from '../api/vehicles';
import { validationErrorsFrom } from '../api/errors';
import FuelForm from '../components/fuel/FuelForm.vue';
import { resourceAttributes } from '../utils/fuel';

const route = useRoute();
const router = useRouter();
const vehicle = ref({});
const entry = ref({});
const errors = ref({});
const status = ref('loading');
const isSubmitting = ref(false);
const pageError = ref('');

const isEditing = computed(() => Boolean(route.params.fuelEntry));
const vehicleAttributes = computed(() => resourceAttributes(vehicle.value));
const isArchived = computed(() => Boolean(vehicleAttributes.value.archived_at));
const vehicleId = computed(() => route.params.vehicle ?? route.query.vehicle ?? entry.value.vehicle_id ?? null);

async function load() {
    status.value = 'loading';
    pageError.value = '';

    try {
        if (isEditing.value) {
            const fuelResource = await fetchFuelEntry(route.params.fuelEntry);
            entry.value = resourceAttributes(fuelResource);
            const fuelVehicleId = entry.value.vehicle_id ?? fuelResource?.relationships?.vehicle?.data?.id ?? route.query.vehicle;

            if (fuelVehicleId) {
                vehicle.value = await fetchVehicle(fuelVehicleId);
            }
        } else {
            vehicle.value = await fetchVehicle(route.params.vehicle);
        }

        status.value = 'ready';
    } catch {
        status.value = 'error';
        pageError.value = 'We could not load this fuel entry. Please try again.';
    }
}

async function submit(attributes) {
    isSubmitting.value = true;
    errors.value = {};
    pageError.value = '';

    try {
        if (isEditing.value) {
            await updateFuelEntry(route.params.fuelEntry, attributes);
        } else {
            await createFuelEntry(route.params.vehicle, attributes);
        }

        await router.push({ name: 'fuel-entries.index', params: { vehicle: vehicleId.value } });
    } catch (error) {
        errors.value = validationErrorsFrom(error);

        if (Object.keys(errors.value).length === 0) {
            pageError.value = 'The fuel entry could not be saved. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Fuel tracking</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditing ? 'Edit fuel entry' : 'Add fuel' }}</h1>
        <p class="mt-2 text-base leading-7 text-slate-600">{{ vehicleAttributes.nickname || 'Vehicle' }} · Record a complete fill-up so your fuel economy stays trustworthy.</p>

        <div v-if="status === 'loading'" class="mt-8 rounded-2xl border border-slate-200 bg-white p-8" aria-live="polite" role="status">Loading fuel entry…</div>
        <div v-else-if="status === 'error'" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">{{ pageError }}</div>
        <div v-else-if="isArchived" class="mt-8 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Archived vehicle</h2>
            <p class="text-sm leading-6 text-slate-700">Fuel history remains available, but archived vehicles are read-only. Restore the vehicle before changing its records.</p>
            <RouterLink class="inline-flex min-h-11 w-fit items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${vehicleId}/fuel-entries`">Back to fuel history</RouterLink>
        </div>
        <div v-else class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <p v-if="pageError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ pageError }}</p>
            <FuelForm
                :cancel-to="`/vehicles/${vehicleId}/fuel-entries`"
                :errors="errors"
                :initial-value="entry"
                :is-submitting="isSubmitting"
                :submit-label="isEditing ? 'Save changes' : 'Add fuel entry'"
                @submit="submit"
            />
        </div>
    </section>
</template>
