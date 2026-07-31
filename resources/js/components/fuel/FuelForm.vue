<script setup>
import { reactive, watch } from 'vue';

import AuthField from '../auth/AuthField.vue';
import { toAtomDateTime, toDateTimeLocal } from '../../utils/fuel';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    initialValue: { type: Object, default: () => ({}) },
    isSubmitting: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    cancelTo: { type: [String, Object], required: true },
});

const emit = defineEmits(['submit']);
const form = reactive({
    fueled_at: '',
    odometer: '',
    gallons: '',
    total_cost: '',
    price_per_gallon: '',
    station: '',
    fuel_grade: '',
    notes: '',
});

watch(
    () => props.initialValue,
    (value) => {
        form.fueled_at = toDateTimeLocal(value?.fueled_at);
        form.odometer = value?.odometer === null || value?.odometer === undefined ? '' : String(value.odometer);
        form.gallons = value?.gallons === null || value?.gallons === undefined ? '' : String(value.gallons);
        form.total_cost = value?.total_cost === null || value?.total_cost === undefined ? '' : String(value.total_cost);
        form.price_per_gallon = value?.price_per_gallon === null || value?.price_per_gallon === undefined ? '' : String(value.price_per_gallon);
        form.station = value?.station ?? '';
        form.fuel_grade = value?.fuel_grade ?? '';
        form.notes = value?.notes ?? '';
    },
    { immediate: true },
);

function submit() {
    emit('submit', {
        fueled_at: toAtomDateTime(form.fueled_at),
        odometer: Number(form.odometer),
        gallons: form.gallons,
        total_cost: form.total_cost || null,
        price_per_gallon: form.price_per_gallon || null,
        station: form.station || null,
        fuel_grade: form.fuel_grade || null,
        notes: form.notes || null,
    });
}
</script>

<template>
    <form class="grid gap-6" novalidate @submit.prevent="submit">
        <p class="rounded-xl border border-sky-100 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
            Every entry is a full fill-up. That lets AutoTracker calculate miles driven and MPG from the previous fill-up.
        </p>

        <div class="grid gap-5 sm:grid-cols-2">
            <AuthField id="fueled_at" v-model="form.fueled_at" :error="errors.fueled_at" label="Date and time" type="datetime-local" autocomplete="off" />
            <AuthField id="odometer" v-model="form.odometer" :error="errors.odometer" label="Odometer (miles)" type="number" autocomplete="off" />
            <AuthField id="gallons" v-model="form.gallons" :error="errors.gallons" label="Gallons" type="number" autocomplete="off" />
            <AuthField id="total_cost" v-model="form.total_cost" :error="errors.total_cost" label="Total cost (USD)" type="number" :required="false" autocomplete="off" />
            <AuthField id="price_per_gallon" v-model="form.price_per_gallon" :error="errors.price_per_gallon" label="Price per gallon (USD)" type="number" :required="false" autocomplete="off" />
            <AuthField id="station" v-model="form.station" :error="errors.station" label="Station" :required="false" autocomplete="organization" />
            <div class="grid gap-2">
                <label class="text-sm font-medium text-slate-800" for="fuel_grade">Fuel grade</label>
                <select id="fuel_grade" v-model="form.fuel_grade" :aria-describedby="errors.fuel_grade ? 'fuel_grade-error' : undefined" :aria-invalid="errors.fuel_grade ? 'true' : 'false'" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <option value="">Not specified</option>
                    <option value="87">87 octane</option>
                    <option value="89">89 octane</option>
                    <option value="91">91 octane</option>
                    <option value="93">93 octane</option>
                    <option value="diesel">Diesel</option>
                    <option value="other">Other</option>
                </select>
                <p v-if="errors.fuel_grade" id="fuel_grade-error" class="text-sm text-red-700" role="alert">{{ errors.fuel_grade }}</p>
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <label class="text-sm font-medium text-slate-800" for="notes">Notes <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="notes" v-model="form.notes" :aria-describedby="errors.notes ? 'notes-error' : undefined" :aria-invalid="errors.notes ? 'true' : 'false'" class="min-h-28 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-sky-700 focus:ring-4 focus:ring-sky-100" />
                <p v-if="errors.notes" id="notes-error" class="text-sm text-red-700" role="alert">{{ errors.notes }}</p>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="cancelTo">Cancel</RouterLink>
            <button :disabled="isSubmitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60" type="submit">
                {{ isSubmitting ? 'Saving fuel entry…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
