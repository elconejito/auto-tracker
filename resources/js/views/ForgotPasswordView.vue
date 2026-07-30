<script setup>
import { reactive, ref } from 'vue';

import AuthError from '../components/auth/AuthError.vue';
import AuthField from '../components/auth/AuthField.vue';
import AuthLayout from '../components/auth/AuthLayout.vue';
import { messageFrom, validationErrorsFrom } from '../api/errors';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const form = reactive({ email: '' });
const errors = ref({});
const formError = ref('');
const successMessage = ref('');
const pending = ref(false);

async function submit() {
    errors.value = {};
    formError.value = '';
    successMessage.value = '';
    pending.value = true;

    try {
        await auth.requestPasswordReset(form.email);
        successMessage.value = 'If an account uses that email, a password reset link is on its way.';
    } catch (error) {
        errors.value = validationErrorsFrom(error);
        formError.value = messageFrom(error, 'We could not start the password reset.');
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <AuthLayout
        description="Enter your email and we will send instructions if an account matches it."
        eyebrow="Account recovery"
        title="Reset your password"
    >
        <div class="grid gap-5">
            <div
                v-if="successMessage"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                role="status"
            >
                {{ successMessage }}
            </div>
            <AuthError v-if="formError && !Object.keys(errors).length" :message="formError" />

            <form class="grid gap-5" novalidate @submit.prevent="submit">
                <AuthField
                    id="email"
                    v-model="form.email"
                    autocomplete="email"
                    :error="errors.email"
                    label="Email address"
                    type="email"
                />

                <button
                    :aria-busy="pending"
                    class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="pending"
                    type="submit"
                >
                    {{ pending ? 'Sending instructions…' : 'Send reset link' }}
                </button>
            </form>
        </div>

        <template #footer>
            Remember your password?
            <RouterLink
                class="font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sky-700"
                to="/login"
            >
                Sign in
            </RouterLink>
        </template>
    </AuthLayout>
</template>
