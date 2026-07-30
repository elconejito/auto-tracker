<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: 'Welcome back',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="flex min-h-[calc(100vh-1px)] items-center justify-center px-4 py-12 sm:px-6">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <RouterLink
                    class="text-xl font-semibold tracking-tight text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                    to="/"
                >
                    AutoTracker
                </RouterLink>
                <p class="mt-3 text-sm text-slate-600">
                    Private vehicle records you can trust.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-semibold tracking-wide text-sky-700 uppercase">
                    {{ eyebrow }}
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
                    {{ title }}
                </h1>
                <p v-if="description" class="mt-3 text-sm leading-6 text-slate-600">
                    {{ description }}
                </p>

                <div class="mt-8">
                    <slot />
                </div>
            </div>

            <div v-if="$slots.footer" class="mt-6 text-center text-sm text-slate-600">
                <slot name="footer" />
            </div>
        </div>
    </section>
</template>
