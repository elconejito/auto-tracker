<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { messageFrom } from '../api/errors';
import { importFuelEntries } from '../api/fuel';
import { fetchVehicle } from '../api/vehicles';

const route = useRoute();
const vehicle = ref(null);
const file = ref(null);
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref('');
const importErrorTitle = ref('');
const result = ref(null);
const uploadInput = ref(null);
const resultTitle = computed(() => ({ success: 'Success', partial_success: 'Partial success', failure: 'Total failure' })[result.value?.status]);
const attributes = computed(() => vehicle.value?.attributes ?? {});
const canImport = computed(() => vehicle.value && !attributes.value.archived_at && !submitting.value);

async function load() {
    loading.value = true;
    errorMessage.value = '';
    try {
        vehicle.value = await fetchVehicle(route.params.vehicle);
    } catch (error) {
        errorMessage.value = messageFrom(error, 'We could not load this vehicle. Please try again.');
    } finally {
        loading.value = false;
    }
}

function chooseFile(event) {
    file.value = event.target.files?.[0] ?? null;
    errorMessage.value = '';
}

async function submit() {
    if (!canImport.value || !file.value) {
        return;
    }
    submitting.value = true;
    errorMessage.value = '';
    importErrorTitle.value = '';
    try {
        result.value = await importFuelEntries(route.params.vehicle, file.value);
        file.value = null;
        uploadInput.value.value = '';
    } catch (error) {
        result.value = null;
        importErrorTitle.value = error.response?.status >= 400 && error.response?.status < 500 ? 'Total failure' : 'Import status unavailable';
        errorMessage.value = messageFrom(error, 'We could not confirm the import result. Check fuel history before retrying.');
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="mx-auto grid max-w-3xl gap-6 px-4 py-8 sm:px-6 sm:py-12">
        <RouterLink class="w-fit rounded text-sm font-semibold text-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/fuel-entries`">Back to fuel history</RouterLink>
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Import fuel fill-ups</h1>
            <p v-if="vehicle" class="mt-2 text-slate-600">Importing into {{ attributes.nickname }} · {{ attributes.year }} {{ attributes.make }} {{ attributes.model }}</p>
        </div>
        <p v-if="loading" role="status" class="text-slate-600">Loading vehicle…</p>
        <div v-if="errorMessage" class="grid gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <h2 v-if="importErrorTitle" class="font-semibold">{{ importErrorTitle }}</h2>
            <p>{{ errorMessage }}</p>
            <button v-if="!vehicle && !loading" type="button" class="min-h-11 w-fit rounded-lg px-3 font-semibold focus-visible:outline-2" @click="load">Try again</button>
        </div>
        <p v-if="vehicle && attributes.archived_at" class="rounded-lg bg-amber-50 p-4 text-amber-900" role="alert">Restore this vehicle before importing fuel fill-ups.</p>
        <div v-if="result" class="grid gap-4 rounded-2xl border p-6" :class="result.status === 'success' ? 'border-green-200 bg-green-50' : result.status === 'partial_success' ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50'" role="status">
            <h2 class="text-lg font-semibold text-slate-950">{{ resultTitle }}</h2>
            <p class="text-sm text-slate-800">{{ result.imported }} imported · {{ result.failed }} not imported.<span v-if="result.imported"> Fuel economy has been recalculated.</span></p>
            <p v-if="result.failed" class="text-sm leading-6 text-slate-700">Correct the rows below and upload another CSV, or add the missing fill-ups manually. Uploading only the failed rows makes the next result easier to review.</p>
            <div v-if="result.failures.length" class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Rows that did not import. Row 1 is the CSV header.</caption>
                    <thead class="bg-slate-50 text-slate-700"><tr><th class="p-3">CSV row</th><th class="p-3">Date</th><th class="p-3">Odometer</th><th class="p-3">Reason</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="failure in result.failures" :key="failure.row" class="align-top">
                            <th scope="row" class="p-3">{{ failure.row }}</th>
                            <td class="p-3">{{ failure.values.fuelup_date || 'Unavailable' }}</td>
                            <td class="p-3">{{ failure.values.odometer || 'Unavailable' }}</td>
                            <td class="min-w-56 p-3"><ul class="grid list-disc gap-1 pl-4"><li v-for="reason in failure.errors" :key="reason">{{ reason }}</li></ul></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <RouterLink class="w-fit rounded-lg px-3 py-2 text-sm font-semibold text-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/fuel-entries`">View fuel history</RouterLink>
        </div>
        <form v-if="vehicle && !attributes.archived_at" class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" :aria-busy="submitting" @submit.prevent="submit">
            <div class="grid gap-3 text-sm leading-6 text-slate-600" id="csv-help">
                <p>Include these headers: <code class="break-words text-slate-950">model,odometer,gallons,price,fuelup_date</code>. Extra columns are ignored.</p>
                <p>Price is the price per gallon. Use dates such as <code>2025-06-15</code>, with an optional time. All rows go into this vehicle; the model column does not select a vehicle. Each row represents a full fill-up.</p>
                <p>Maximum 2 MB and 5,000 rows. Valid rows are saved. Invalid, duplicate, or conflicting rows are listed for correction. Existing fill-ups stay in place.</p>
            </div>
            <label class="grid gap-2 text-sm font-medium text-slate-800" for="fuel-csv">{{ result || errorMessage ? 'Upload another CSV' : 'CSV file' }}
                <input id="fuel-csv" ref="uploadInput" class="min-h-11 w-full rounded-lg border border-slate-300 p-3 text-slate-950 file:mr-3 file:rounded file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:font-semibold file:text-sky-800 focus-visible:outline-2 focus-visible:outline-sky-700" type="file" accept=".csv,text/csv" required aria-describedby="csv-help" :disabled="submitting" @change="chooseFile" />
            </label>
            <button class="min-h-11 w-fit rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-50" type="submit" :disabled="!file || !canImport">{{ submitting ? 'Importing…' : 'Import fill-ups' }}</button>
        </form>
        <RouterLink v-if="vehicle && !attributes.archived_at && (result || errorMessage)" class="min-h-11 w-fit rounded-lg border border-sky-200 px-4 py-3 text-sm font-semibold text-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700" :to="`/vehicles/${route.params.vehicle}/fuel-entries/new`" target="_blank" rel="noopener">Add missing fill-up manually (opens a new tab)</RouterLink>
    </section>
</template>
