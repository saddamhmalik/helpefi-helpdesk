<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/admin/login');
};
</script>

<template>
    <Head title="Platform admin" />
    <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4">
        <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-white p-8 shadow-2xl">
            <h1 class="text-xl font-semibold text-slate-900">Platform admin</h1>
            <p class="mt-2 text-sm text-slate-600">Sign in to manage workspaces and platform settings.</p>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" autofocus />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                    <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Remember me
                </label>
                <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                    Sign in
                </button>
            </form>
            <Link href="/" class="mt-4 block text-center text-sm text-slate-500 hover:text-slate-700">← Back to site</Link>
        </div>
    </div>
</template>
