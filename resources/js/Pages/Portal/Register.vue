<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/portal/register');
</script>

<template>
    <Head title="Register" />
    <PortalLayout>
        <div class="mx-auto max-w-md">
            <Link href="/portal" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Create account</h1>
            <p class="mt-1 text-sm text-slate-600">Register to view all your support requests in one place.</p>

            <form class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                    <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">Create account</button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                Already have an account?
                <Link href="/portal/login" class="text-blue-600 hover:text-blue-700">Sign in</Link>
            </p>
        </div>
    </PortalLayout>
</template>
