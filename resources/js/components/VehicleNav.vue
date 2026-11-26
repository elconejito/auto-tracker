<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { returnPath } from '../utils/navigation';

const props = defineProps({
    vehicleId: { type: [String, Number], required: true },
    nickname: { type: String, default: '' },
    archived: { type: Boolean, default: false },
});

const route = useRoute();
const garage = computed(() => {
    const path = returnPath(route.query.garage, '/');

    return path === '/' || path.startsWith('/?') ? path : '/';
});
const sections = computed(() => {
    const base = `/vehicles/${encodeURIComponent(props.vehicleId)}`;

    return [
        { label: 'Overview', path: base, active: route.path === base },
        { label: 'Fuel history', path: `${base}/fuel-entries`, active: route.path.startsWith(`${base}/fuel-entries`) || route.path.startsWith('/fuel-entries/') },
        { label: 'Maintenance history', path: `${base}/maintenance-events`, active: route.path.startsWith(`${base}/maintenance-events`) || route.path.startsWith('/maintenance-events/') },
    ];
});
</script>

<template>
    <div class="grid gap-3 border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <RouterLink :to="garage" class="rounded font-semibold text-sky-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Vehicles</RouterLink>
            <span aria-hidden="true" class="text-slate-400">/</span>
            <span class="font-semibold text-slate-900">{{ nickname || 'Vehicle' }}</span>
            <span v-if="archived" class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700">Archived</span>
        </div>
        <nav aria-label="Vehicle sections" class="flex flex-wrap gap-1">
            <RouterLink
                v-for="section in sections"
                :key="section.path"
                :to="{ path: section.path, query: garage === '/' ? {} : { garage } }"
                :aria-current="section.active ? (route.path === section.path ? 'page' : 'location') : undefined"
                :class="section.active ? 'bg-sky-100 text-sky-900' : 'text-slate-700 hover:bg-slate-100'"
                class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
            >{{ section.label }}</RouterLink>
        </nav>
    </div>
</template>
