<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/register');
</script>

<template>
    <Head title="Register" />
    <AppLayout>
        <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-900">Create account</h1>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="name">Name</label>
                    <input id="name" v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input id="email" v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Password</label>
                    <input id="password" v-model="form.password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                </div>

                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" :disabled="form.processing">
                    Register
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                Already have an account?
                <a href="/login" class="text-blue-600 hover:text-blue-700">Sign in</a>
            </p>
        </div>
    </AppLayout>
</template>
