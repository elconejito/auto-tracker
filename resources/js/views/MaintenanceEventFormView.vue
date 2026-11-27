<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { createMaintenanceEvent, fetchMaintenanceEvent, updateMaintenanceEvent } from '../api/maintenance';
import { messageFrom, validationErrorsFrom } from '../api/errors';
import { fetchVehicle } from '../api/vehicles';
import VehicleNav from '../components/VehicleNav.vue';
import { returnPath } from '../utils/navigation';
import MaintenanceForm from '../components/maintenance/MaintenanceForm.vue';
import { resourceAttributes } from '../utils/fuel';

const route = useRoute();
const router = useRouter();
const vehicle = ref({});
const event = ref({});
const errors = ref({});
const status = ref('loading');
const isSubmitting = ref(false);
const pageError = ref('');

const isEditing = computed(() => Boolean(route.params.maintenanceEvent));
const vehicleAttributes = computed(() => resourceAttributes(vehicle.value));
const isArchived = computed(() => Boolean(vehicleAttributes.value.archived_at));
const vehicleId = computed(() => vehicle.value.id ?? route.params.vehicle);

const destination = computed(() => returnPath(route.query.returnTo, `/vehicles/${vehicleId.value}/maintenance-events`));

async function load() {
    status.value = 'loading';
    pageError.value = '';

    try {
        if (isEditing.value) {
            const eventResource = await fetchMaintenanceEvent(route.params.maintenanceEvent);
            event.value = eventResource;
            const attributes = resourceAttributes(eventResource);
            const eventVehicleId = attributes.vehicle_id ?? eventResource?.relationships?.vehicle?.data?.id ?? route.query.vehicle;

            if (!eventVehicleId) {
                throw new Error('Maintenance event is missing its vehicle.');
            }

            vehicle.value = await fetchVehicle(eventVehicleId);
        } else {
            vehicle.value = await fetchVehicle(route.params.vehicle);
        }

        status.value = 'ready';
    } catch {
        status.value = 'error';
        pageError.value = 'We could not load this maintenance event. Please try again.';
    }
}

async function submit(attributes) {
    isSubmitting.value = true;
    errors.value = {};
    pageError.value = '';

    const originalPath = route.fullPath;
    const afterSave = destination.value;

    try {
        if (isEditing.value) {
            await updateMaintenanceEvent(route.params.maintenanceEvent, attributes);
        } else {
            await createMaintenanceEvent(route.params.vehicle, attributes);
        }
    } catch (error) {
        errors.value = validationErrorsFrom(error);

        if (Object.keys(errors.value).length === 0) {
            pageError.value = messageFrom(error, 'The maintenance event could not be saved. Please try again.');
        }
        isSubmitting.value = false;

        return;
    }

    try {
        if (route.fullPath === originalPath) {
            await router.push(afterSave);
        }
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <VehicleNav v-if="status === 'ready'" class="mb-6" :vehicle-id="vehicleId" :nickname="vehicleAttributes.nickname" :archived="isArchived" />
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditing ? 'Edit maintenance visit' : 'Add maintenance visit' }}</h1>

        <div v-if="status === 'loading'" class="mt-8 rounded-2xl border border-slate-200 bg-white p-8" aria-live="polite" role="status">Loading maintenance event…</div>
        <div v-else-if="status === 'error'" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6" role="alert">{{ pageError }} <button type="button" class="ml-2 underline" @click="load">Try again</button></div>
        <div v-else-if="isArchived" class="mt-8 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Archived vehicle</h2>
            <p class="text-sm leading-6 text-slate-700">Maintenance history remains available, but archived vehicles are read-only. Restore the vehicle before changing its records.</p>
            <RouterLink v-if="isEditing" class="w-fit rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-sky-800" :to="{ path: `/maintenance-events/${event.id}`, query: { garage: route.query.garage, returnTo: destination } }">View visit</RouterLink>
            <RouterLink class="w-fit rounded-lg px-4 py-2.5 text-sm font-semibold text-sky-800" :to="destination">Back</RouterLink>
        </div>
        <div v-else class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <p v-if="pageError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ pageError }}</p>
            <MaintenanceForm
                :cancel-to="destination"
                :errors="errors"
                :initial-value="event"
                :is-submitting="isSubmitting"
                :submit-label="isEditing ? 'Save changes' : 'Add maintenance visit'"
                @submit="submit"
            />
        </div>
    </section>
</template>
