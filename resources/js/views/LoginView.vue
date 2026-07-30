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
const form = reactive({ email: '', password: '' });
const errors = ref({});
const formError = ref('');
const pending = ref(false);
const resetSuccess = route.query.reset === 'success';

async function submit() {
    errors.value = {};
    formError.value = '';
    pending.value = true;

    try {
        await auth.login(form);
        await router.replace(intendedPath(route.query.redirect));
    } catch (error) {
        errors.value = validationErrorsFrom(error);
        formError.value = messageFrom(error, 'The email or password was not accepted.');
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <AuthLayout
        description="Sign in to keep your vehicles, fuel, and maintenance history in one place."
        eyebrow="Welcome back"
        title="Sign in to AutoTracker"
    >
        <div
            v-if="resetSuccess"
            class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
        >
            Your password was updated. You can sign in with your new password.
        </div>

        <form class="grid gap-5" novalidate @submit.prevent="submit">
            <AuthError v-if="formError && !Object.keys(errors).length" :message="formError" />

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
                autocomplete="current-password"
                :error="errors.password"
                label="Password"
                type="password"
            />

            <div class="flex justify-end">
                <RouterLink
                    class="text-sm font-medium text-sky-800 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                    to="/forgot-password"
                >
                    Forgot your password?
                </RouterLink>
            </div>

            <button
                :aria-busy="pending"
                class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="pending"
                type="submit"
            >
                {{ pending ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>

        <template #footer>
            Don't have an account?
            <RouterLink
                class="font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                to="/register"
            >
                Create one
            </RouterLink>
        </template>
    </AuthLayout>
</template>
