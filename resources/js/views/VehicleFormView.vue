<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { createVehicle, fetchVehicle, updateVehicle } from '../api/vehicles';
import { validationErrorsFrom } from '../api/errors';
import VehicleForm from '../components/vehicles/VehicleForm.vue';

const route = useRoute();
const router = useRouter();
const vehicle = ref({});
const errors = ref({});
const status = ref(route.params.vehicle ? 'loading' : 'ready');
const isSubmitting = ref(false);
const pageError = ref('');
const isEditing = computed(() => Boolean(route.params.vehicle));

async function loadVehicle() {
    try {
        const response = await fetchVehicle(route.params.vehicle);
        vehicle.value = response.attributes ?? response;
        status.value = 'ready';
    } catch {
        status.value = 'error';
        pageError.value = 'We could not load this vehicle.';
    }
}

async function submit(attributes) {
    isSubmitting.value = true;
    errors.value = {};
    pageError.value = '';

    try {
        if (isEditing.value) {
            await updateVehicle(route.params.vehicle, attributes);
        } else {
            await createVehicle(attributes);
        }

        await router.push('/');
    } catch (error) {
        errors.value = validationErrorsFrom(error);

        if (Object.keys(errors.value).length === 0) {
            pageError.value = 'The vehicle could not be saved. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(() => {
    if (isEditing.value) {
        loadVehicle();
    }
});
</script>

<template>
    <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">Your garage</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditing ? 'Edit vehicle' : 'Add a vehicle' }}</h1>
        <p class="mt-2 text-base leading-7 text-slate-600">Enter the facts you know now. Optional identifiers can be added or changed later.</p>

        <div v-if="status === 'loading'" class="mt-8 rounded-2xl border border-slate-200 bg-white p-8" aria-live="polite" role="status">Loading vehicle…</div>
        <div v-else-if="status === 'error'" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">{{ pageError }}</div>
        <div v-else class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <p v-if="pageError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ pageError }}</p>
            <VehicleForm :errors="errors" :initial-value="vehicle" :is-submitting="isSubmitting" :submit-label="isEditing ? 'Save changes' : 'Add vehicle'" @submit="submit" />
        </div>
    </section>
</template>
