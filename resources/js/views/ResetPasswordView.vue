<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AuthError from '../components/auth/AuthError.vue';
import AuthField from '../components/auth/AuthField.vue';
import AuthLayout from '../components/auth/AuthLayout.vue';
import { messageFrom, validationErrorsFrom } from '../api/errors';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const form = reactive({
    email: typeof route.query.email === 'string' ? route.query.email : '',
    password: '',
    password_confirmation: '',
    token: typeof route.query.token === 'string' ? route.query.token : '',
});
const errors = ref({});
const formError = ref('');
const pending = ref(false);

async function submit() {
    errors.value = {};
    formError.value = '';
    pending.value = true;

    try {
        await auth.resetPassword(form);
        await router.replace({ name: 'login', query: { reset: 'success' } });
    } catch (error) {
        errors.value = validationErrorsFrom(error);
        formError.value = messageFrom(error, 'We could not reset your password.');
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <AuthLayout
        description="Choose a new password for your AutoTracker account."
        eyebrow="Account recovery"
        title="Choose a new password"
    >
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
                autocomplete="new-password"
                :error="errors.password"
                label="New password"
                type="password"
            />
            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                autocomplete="new-password"
                :error="errors.password_confirmation"
                label="Confirm new password"
                type="password"
            />

            <button
                :aria-busy="pending"
                class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="pending"
                type="submit"
            >
                {{ pending ? 'Updating password…' : 'Update password' }}
            </button>
        </form>

        <template #footer>
            Need a new reset link?
            <RouterLink
                class="font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                to="/forgot-password"
            >
                Start again
            </RouterLink>
        </template>
    </AuthLayout>
</template>
