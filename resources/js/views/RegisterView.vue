<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AuthError from '../components/auth/AuthError.vue';
import AuthField from '../components/auth/AuthField.vue';
import AuthLayout from '../components/auth/AuthLayout.vue';
import { messageFrom, validationErrorsFrom } from '../api/errors';
import { intendedPath } from '../router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const formError = ref('');
const pending = ref(false);

async function submit() {
    errors.value = {};
    formError.value = '';
    pending.value = true;

    try {
        await auth.register(form);
        await router.replace(intendedPath(route.query.redirect));
    } catch (error) {
        errors.value = validationErrorsFrom(error);
        formError.value = messageFrom(error, 'We could not create your account.');
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <AuthLayout
        description="Create a private account for your vehicle records."
        eyebrow="Get started"
        title="Create your account"
    >
        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <AuthError v-if="formError && !Object.keys(errors).length" :message="formError" />

            <AuthField
                id="name"
                v-model="form.name"
                autocomplete="name"
                :error="errors.name"
                label="Name"
            />
            <AuthField
                id="email"
                v-model="form.email"
                autocomplete="email"
                :error="errors.email"
                label="Email address"
                type="email"
            />
            <AuthField
                id="password"
                v-model="form.password"
                autocomplete="new-password"
                :error="errors.password"
                label="Password"
                type="password"
            />
            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                autocomplete="new-password"
                :error="errors.password_confirmation"
                label="Confirm password"
                type="password"
            />

            <button
                :aria-busy="pending"
                class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="pending"
                type="submit"
            >
                {{ pending ? 'Creating account…' : 'Create account' }}
            </button>
        </form>

        <template #footer>
            Already have an account?
            <RouterLink
                class="font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                to="/login"
            >
                Sign in
            </RouterLink>
        </template>
    </AuthLayout>
</template>
