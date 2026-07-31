<script setup>
import { reactive, watch } from 'vue';

import AuthField from '../auth/AuthField.vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    initialValue: { type: Object, default: () => ({}) },
    isSubmitting: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
});

const emit = defineEmits(['submit']);
const currentYear = new Date().getFullYear();
const form = reactive({
    nickname: '',
    year: String(currentYear),
    make: '',
    model: '',
    trim: '',
    vin: '',
    license_plate: '',
    current_odometer: '',
});

watch(
    () => props.initialValue,
    (value) => {
        for (const key of Object.keys(form)) {
            form[key] = value?.[key] === null || value?.[key] === undefined ? '' : String(value[key]);
        }
    },
    { immediate: true },
);

function submit() {
    emit('submit', {
        ...form,
        year: Number(form.year),
        current_odometer: Number(form.current_odometer),
        trim: form.trim || null,
        vin: form.vin || null,
        license_plate: form.license_plate || null,
    });
}
</script>

<template>
    <form class="grid gap-6" novalidate @submit.prevent="submit">
        <div class="grid gap-5 sm:grid-cols-2">
            <AuthField id="nickname" v-model="form.nickname" :error="errors.nickname" label="Nickname" autocomplete="off" />
            <AuthField id="year" v-model="form.year" :error="errors.year" label="Year" type="number" autocomplete="off" />
            <AuthField id="make" v-model="form.make" :error="errors.make" label="Make" autocomplete="organization" />
            <AuthField id="model" v-model="form.model" :error="errors.model" label="Model" autocomplete="off" />
            <AuthField id="trim" v-model="form.trim" :error="errors.trim" label="Trim" :required="false" autocomplete="off" />
            <AuthField id="current_odometer" v-model="form.current_odometer" :error="errors.current_odometer" label="Current odometer" type="number" autocomplete="off" />
            <AuthField id="vin" v-model="form.vin" :error="errors.vin" label="VIN" :required="false" autocomplete="off" />
            <AuthField id="license_plate" v-model="form.license_plate" :error="errors.license_plate" label="License plate" :required="false" autocomplete="off" />
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" to="/">
                Cancel
            </RouterLink>
            <button :disabled="isSubmitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60" type="submit">
                {{ isSubmitting ? 'Saving vehicle…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
