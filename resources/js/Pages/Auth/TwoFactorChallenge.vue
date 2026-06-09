<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({
    code: '',
});

const submit = () => form.post('/two-factor-challenge');
</script>

<template>
    <Head title="Two-factor authentication" />
    <AppLayout>
        <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-900">Authentication code</h1>
            <p class="mt-2 text-sm text-slate-600">Enter the 6-digit code from your authenticator app or a recovery code.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="code">Code</label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 tracking-widest focus:border-blue-500 focus:outline-none"
                        required
                    />
                    <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Verify
                </button>
            </form>
        </div>
    </AppLayout>
</template>
