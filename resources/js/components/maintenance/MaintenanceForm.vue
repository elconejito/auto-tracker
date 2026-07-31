<script setup>
import { reactive, watch } from 'vue';

import AuthField from '../auth/AuthField.vue';
import { resourceAttributes } from '../../utils/fuel';
import { POSITION_OPTIONS, SERVICE_LABELS, SERVICE_TYPES, serviceResourceAttributes } from '../../utils/maintenance';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    initialValue: { type: Object, default: () => ({}) },
    isSubmitting: { type: Boolean, default: false },
    submitLabel: { type: String, required: true },
    cancelTo: { type: [String, Object], required: true },
});

const emit = defineEmits(['submit']);

const form = reactive({
    performed_at: '',
    odometer: '',
    provider: '',
    total_cost: '',
    notes: '',
    services: [emptyService()],
});

function emptyService(type = 'oil_change') {
    return { type, line_cost: '', details: {} };
}

function toDateInput(value) {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
}

function hydrateService(service) {
    const normalized = serviceResourceAttributes(service);

    return {
        type: normalized.type || 'oil_change',
        line_cost: normalized.line_cost === null || normalized.line_cost === undefined ? '' : String(normalized.line_cost),
        details: { ...(normalized.details ?? {}) },
    };
}

watch(
    () => props.initialValue,
    (value) => {
        const attributes = resourceAttributes(value);

        form.performed_at = toDateInput(attributes.performed_at);
        form.odometer = attributes.odometer === null || attributes.odometer === undefined ? '' : String(attributes.odometer);
        form.provider = attributes.provider ?? '';
        form.total_cost = attributes.total_cost === null || attributes.total_cost === undefined ? '' : String(attributes.total_cost);
        form.notes = attributes.notes ?? '';
        form.services = Array.isArray(attributes.services) && attributes.services.length
            ? attributes.services.map(hydrateService)
            : [emptyService()];
    },
    { immediate: true },
);

function fieldError(index, field) {
    return props.errors[`services.${index}.details.${field}`]
        ?? props.errors[`services.${index}.${field}`]
        ?? '';
}

function setServiceType(index, type) {
    form.services[index].type = type;
    form.services[index].details = {};
}

function addService() {
    form.services.push(emptyService());
}

function removeService(index) {
    if (form.services.length > 1) {
        form.services.splice(index, 1);
    }
}

function submit() {
    emit('submit', {
        performed_at: form.performed_at || null,
        odometer: Number(form.odometer),
        provider: form.provider || null,
        total_cost: form.total_cost || null,
        notes: form.notes || null,
        services: form.services.map((service) => ({
            type: service.type,
            line_cost: service.line_cost || null,
            details: { ...service.details },
        })),
    });
}
</script>

<template>
    <form class="grid gap-8" novalidate @submit.prevent="submit">
        <div class="grid gap-5 sm:grid-cols-2">
            <AuthField id="performed_at" v-model="form.performed_at" :error="errors.performed_at" label="Date performed" type="date" autocomplete="off" />
            <AuthField id="odometer" v-model="form.odometer" :error="errors.odometer" label="Odometer (miles)" type="number" autocomplete="off" />
            <AuthField id="provider" v-model="form.provider" :error="errors.provider" label="Service provider" :required="false" autocomplete="organization" />
            <AuthField id="total_cost" v-model="form.total_cost" :error="errors.total_cost" label="Total cost (USD)" type="number" :required="false" autocomplete="off" />
            <div class="grid gap-2 sm:col-span-2">
                <label class="text-sm font-medium text-slate-800" for="notes">Visit notes <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="notes" v-model="form.notes" :aria-describedby="errors.notes ? 'notes-error' : undefined" :aria-invalid="errors.notes ? 'true' : 'false'" class="min-h-28 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-sky-700 focus:ring-4 focus:ring-sky-100" />
                <p v-if="errors.notes" id="notes-error" class="text-sm text-red-700" role="alert">{{ errors.notes }}</p>
            </div>
        </div>

        <fieldset class="grid gap-5 border-t border-slate-200 pt-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <legend class="text-lg font-semibold text-slate-950">Services performed</legend>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Add every service from this visit so the history stays complete.</p>
                </div>
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-sky-200 px-4 py-2.5 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" type="button" @click="addService">Add service</button>
            </div>

            <div class="grid gap-5">
                <article v-for="(service, index) in form.services" :key="index" class="grid gap-5 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="text-base font-semibold text-slate-950">Service {{ index + 1 }}</h3>
                        <button v-if="form.services.length > 1" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" @click="removeService(index)">Remove</button>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium text-slate-800" :for="`service-${index}-type`">Service type</label>
                            <select :id="`service-${index}-type`" v-model="service.type" :aria-describedby="errors[`services.${index}.type`] ? `service-${index}-type-error` : undefined" :aria-invalid="errors[`services.${index}.type`] ? 'true' : 'false'" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition focus:border-sky-700 focus:ring-4 focus:ring-sky-100" @change="setServiceType(index, service.type)">
                                <option v-for="type in SERVICE_TYPES" :key="type" :value="type">{{ SERVICE_LABELS[type] }}</option>
                            </select>
                            <p v-if="errors[`services.${index}.type`]" :id="`service-${index}-type-error`" class="text-sm text-red-700" role="alert">{{ errors[`services.${index}.type`] }}</p>
                        </div>
                        <AuthField :id="`service-${index}-line-cost`" v-model="service.line_cost" :error="errors[`services.${index}.line_cost`]" label="Line cost (USD)" type="number" :required="false" autocomplete="off" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <template v-if="service.type === 'oil_change'">
                            <AuthField :id="`service-${index}-viscosity`" v-model="service.details.viscosity" :error="fieldError(index, 'viscosity')" label="Oil viscosity" :required="false" placeholder="5W-30" />
                            <AuthField :id="`service-${index}-quantity`" v-model="service.details.quantity" :error="fieldError(index, 'quantity')" label="Oil quantity (qt)" type="number" step="0.1" :required="false" />
                            <AuthField :id="`service-${index}-filter-part-number`" v-model="service.details.filter_part_number" :error="fieldError(index, 'filter_part_number')" label="Filter part number" :required="false" />
                            <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-slate-800"><input v-model="service.details.filter_replaced" class="size-5 rounded border-slate-300 text-sky-700 focus:ring-sky-500" type="checkbox" /> Filter replaced</label>
                        </template>

                        <template v-else-if="service.type === 'tire_replacement'">
                            <div class="grid gap-2"><label class="text-sm font-medium text-slate-800" :for="`service-${index}-position`">Position</label><select :id="`service-${index}-position`" v-model="service.details.position" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100"><option value="">Not specified</option><option v-for="option in POSITION_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="fieldError(index, 'position')" class="text-sm text-red-700" role="alert">{{ fieldError(index, 'position') }}</p></div>
                            <AuthField :id="`service-${index}-manufacturer`" v-model="service.details.manufacturer" :error="fieldError(index, 'manufacturer')" label="Manufacturer" :required="false" />
                            <AuthField :id="`service-${index}-model`" v-model="service.details.model" :error="fieldError(index, 'model')" label="Model" :required="false" />
                            <AuthField :id="`service-${index}-size`" v-model="service.details.size" :error="fieldError(index, 'size')" label="Tire size" :required="false" placeholder="225/65R17" />
                            <AuthField :id="`service-${index}-quantity`" v-model="service.details.quantity" :error="fieldError(index, 'quantity')" label="Quantity" type="number" :required="false" />
                        </template>

                        <template v-else-if="['brake_pad_replacement', 'brake_rotor_replacement'].includes(service.type)">
                            <div class="grid gap-2"><label class="text-sm font-medium text-slate-800" :for="`service-${index}-position`">Position</label><select :id="`service-${index}-position`" v-model="service.details.position" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100"><option value="">Not specified</option><option v-for="option in POSITION_OPTIONS.slice(0, 3)" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="fieldError(index, 'position')" class="text-sm text-red-700" role="alert">{{ fieldError(index, 'position') }}</p></div>
                            <AuthField :id="`service-${index}-manufacturer`" v-model="service.details.manufacturer" :error="fieldError(index, 'manufacturer')" label="Manufacturer" :required="false" />
                            <AuthField :id="`service-${index}-part-number`" v-model="service.details.part_number" :error="fieldError(index, 'part_number')" label="Part number" :required="false" />
                        </template>

                        <template v-else-if="service.type === 'tire_rotation'">
                            <AuthField :id="`service-${index}-pattern`" v-model="service.details.pattern" :error="fieldError(index, 'pattern')" label="Rotation pattern" :required="false" placeholder="Front to rear" />
                        </template>

                        <template v-else-if="['battery_replacement', 'timing_belt_replacement', 'serpentine_belt_replacement'].includes(service.type)">
                            <AuthField :id="`service-${index}-manufacturer`" v-model="service.details.manufacturer" :error="fieldError(index, 'manufacturer')" label="Manufacturer" :required="false" />
                            <AuthField :id="`service-${index}-part-number`" v-model="service.details.part_number" :error="fieldError(index, 'part_number')" label="Part number" :required="false" />
                        </template>

                        <template v-else-if="service.type === 'generic_service'">
                            <AuthField :id="`service-${index}-name`" v-model="service.details.name" :error="fieldError(index, 'name')" label="Service name" />
                            <div class="grid gap-2 sm:col-span-2"><label class="text-sm font-medium text-slate-800" :for="`service-${index}-description`">Description <span class="font-normal text-slate-500">(optional)</span></label><textarea :id="`service-${index}-description`" v-model="service.details.description" :aria-describedby="fieldError(index, 'description') ? `service-${index}-description-error` : undefined" class="min-h-24 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" /><p v-if="fieldError(index, 'description')" :id="`service-${index}-description-error`" class="text-sm text-red-700" role="alert">{{ fieldError(index, 'description') }}</p></div>
                        </template>

                        <template v-else>
                            <div class="grid gap-2 sm:col-span-2"><label class="text-sm font-medium text-slate-800" :for="`service-${index}-notes`">Service notes <span class="font-normal text-slate-500">(optional)</span></label><textarea :id="`service-${index}-notes`" v-model="service.details.notes" :aria-describedby="fieldError(index, 'notes') ? `service-${index}-notes-error` : undefined" class="min-h-24 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100" /><p v-if="fieldError(index, 'notes')" :id="`service-${index}-notes-error`" class="text-sm text-red-700" role="alert">{{ fieldError(index, 'notes') }}</p></div>
                        </template>
                    </div>
                </article>
            </div>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <RouterLink class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="cancelTo">Cancel</RouterLink>
            <button :disabled="isSubmitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60" type="submit">{{ isSubmitting ? 'Saving maintenance event…' : submitLabel }}</button>
        </div>
    </form>
</template>
