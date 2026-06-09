<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    centralDomain: String,
    prefillSlug: String,
    prefillEmail: String,
});

const form = useForm({
    slug: props.prefillSlug ?? '',
    email: props.prefillEmail ?? '',
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <Head title="Sign in" />
    <div class="min-h-screen bg-slate-50 px-4 py-12">
        <div class="mx-auto max-w-md">
            <div class="mb-8 text-center">
                <Link href="/" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to home</Link>
                <h1 class="mt-4 text-2xl font-semibold text-slate-900">Sign in to your workspace</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Enter your workspace URL to open the agent login for your team.
                </p>
            </div>

            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Workspace URL</label>
                    <div class="flex overflow-hidden rounded-lg border border-slate-300">
                        <input
                            v-model="form.slug"
                            type="text"
                            required
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            class="min-w-0 flex-1 px-3 py-2 text-sm"
                            placeholder="demo"
                            autofocus
                        />
                        <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ centralDomain }}</span>
                    </div>
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email (optional)</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="you@company.com" />
                    <p class="mt-1 text-xs text-slate-500">Prefills the sign-in form on your workspace.</p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    :disabled="form.processing"
                >
                    Continue to sign in
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                No workspace yet?
                <Link href="/register" class="font-medium text-blue-600 hover:text-blue-700">Create one</Link>
            </p>

            <p class="mt-3 text-center text-xs text-slate-500">
                Demo:
                <a href="http://demo.helpdesk.test/login" class="text-blue-600 hover:text-blue-700">demo.{{ centralDomain }}/login</a>
            </p>
        </div>
    </div>
</template>
