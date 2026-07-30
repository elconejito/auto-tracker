<script setup>
import { RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center px-4 py-4 sm:px-6 lg:px-8">
                <RouterLink
                    class="rounded-sm text-lg font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                    to="/"
                >
                    AutoTracker
                </RouterLink>
            </div>
        </header>

        <main>
            <RouterView />
        </main>
    </div>
</template>
