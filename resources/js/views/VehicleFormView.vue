<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { createVehicle, fetchVehicle, updateVehicle } from '../api/vehicles';
import { validationErrorsFrom } from '../api/errors';
import { returnPath } from '../utils/navigation';
import VehicleNav from '../components/VehicleNav.vue';
import VehicleForm from '../components/vehicles/VehicleForm.vue';

const route = useRoute();
const router = useRouter();
const vehicle = ref({});
const errors = ref({});
const destination = computed(() => returnPath(route.query.returnTo, '/'));
const status = ref(route.params.vehicle ? 'loading' : 'ready');
const isSubmitting = ref(false);
const pageError = ref('');
const isEditing = computed(() => Boolean(route.params.vehicle));

let vehicleRequest = 0;

async function loadVehicle(id) {
    const request = ++vehicleRequest;
    vehicle.value = {};
    pageError.value = '';
    errors.value = {};
    if (!id) {
        status.value = 'ready';
        return;
    }

    status.value = 'loading';
    try {
        const response = await fetchVehicle(id);
        if (request !== vehicleRequest) return;
        vehicle.value = response.attributes ?? response;
        status.value = 'ready';
    } catch {
        if (request !== vehicleRequest) return;
        status.value = 'error';
        pageError.value = 'We could not load this vehicle.';
    }
}

async function submit(attributes) {
    isSubmitting.value = true;
    errors.value = {};
    pageError.value = '';

    const originalRoute = router.currentRoute.value;
    const origin = destination.value;
    let next = origin;

    try {
        if (isEditing.value) {
            await updateVehicle(route.params.vehicle, attributes);
        } else {
            const created = await createVehicle(attributes);
            next = { path: `/vehicles/${created.id}`, query: { garage: origin === '/' ? undefined : origin } };
        }
    } catch (error) {
        if (router.currentRoute.value !== originalRoute) return;
        errors.value = validationErrorsFrom(error);

        if (Object.keys(errors.value).length === 0) {
            pageError.value = 'The vehicle could not be saved. Please try again.';
        }
        return;
    } finally {
        isSubmitting.value = false;
    }

    if (router.currentRoute.value === originalRoute) {
        await router.push(next);
    }
}

watch(() => route.params.vehicle, loadVehicle, { immediate: true });
</script>

<template>
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <VehicleNav v-if="isEditing && status === 'ready'" :vehicle-id="route.params.vehicle" :nickname="vehicle.nickname" :archived="Boolean(vehicle.archived_at)" />
        <p v-else class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Your garage</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditing ? 'Edit vehicle' : 'Add a vehicle' }}</h1>
        <p v-if="!vehicle.archived_at" class="mt-2 text-base leading-7 text-slate-600">Enter the facts you know now. Optional identifiers can be added or changed later.</p>

        <div v-if="status === 'loading'" class="mt-8 rounded-2xl border border-slate-200 bg-white p-8" aria-live="polite" role="status">Loading vehicle…</div>
        <div v-else-if="status === 'error'" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">
            <p>{{ pageError }}</p>
            <button class="mt-3 min-h-11 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white" @click="loadVehicle(route.params.vehicle)">Try again</button>
        </div>
        <div v-else-if="vehicle.archived_at" class="mt-8 rounded-2xl border border-slate-200 bg-white p-5">
            <p>Restore this vehicle in the garage to edit its details.</p>
            <RouterLink class="mt-3 inline-flex min-h-11 items-center font-semibold text-sky-800 underline" to="/?status=archived">View archived vehicles</RouterLink>
        </div>
        <div v-else class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <p v-if="pageError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ pageError }}</p>
            <VehicleForm :cancel-to="destination" :errors="errors" :initial-value="vehicle" :is-submitting="isSubmitting" :submit-label="isEditing ? 'Save changes' : 'Add vehicle'" @submit="submit" />
        </div>
    </section>
</template>
