<template>
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="max-w-xl">
            <p class="text-sm font-semibold text-sky-700">404</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
                Page not found
            </h1>
            <p class="mt-4 text-slate-600">
                The page you requested does not exist.
            </p>
            <RouterLink
                class="mt-8 inline-flex rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                to="/"
            >
                Return home
            </RouterLink>
        </div>
    </section>
</template>
