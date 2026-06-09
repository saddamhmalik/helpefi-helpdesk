<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/portal/login');
</script>

<template>
    <Head title="Sign in" />
    <PortalLayout>
        <div class="mx-auto max-w-md">
            <Link href="/portal" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Sign in</h1>
            <p class="mt-1 text-sm text-slate-600">View and track your support requests.</p>

            <form class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Remember me
                </label>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">Sign in</button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                No account?
                <Link href="/portal/register" class="text-blue-600 hover:text-blue-700">Register</Link>
                or
                <Link href="/portal/track" class="text-blue-600 hover:text-blue-700">track without signing in</Link>
            </p>
        </div>
    </PortalLayout>
</template>
