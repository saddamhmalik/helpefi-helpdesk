<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    plans: Array,
    defaultPlan: String,
});

const centralDomain = computed(() => window.location.hostname);

const form = useForm({
    organization_name: '',
    slug: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan: props.defaultPlan,
});

const submit = () => {
    form.post('/register');
};
</script>

<template>
    <Head title="Create workspace" />
    <div class="min-h-screen bg-slate-50 px-4 py-12">
        <div class="mx-auto max-w-lg">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold text-slate-900">Create your helpdesk</h1>
                <p class="mt-2 text-sm text-slate-600">Set up a workspace for your team in a few minutes.</p>
            </div>

            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Organization name</label>
                    <input v-model="form.organization_name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="form.errors.organization_name" class="mt-1 text-xs text-red-600">{{ form.errors.organization_name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Workspace URL</label>
                    <div class="flex overflow-hidden rounded-lg border border-slate-300">
                        <input v-model="form.slug" type="text" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-w-0 flex-1 px-3 py-2 text-sm" placeholder="acme" />
                        <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ centralDomain }}</span>
                    </div>
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Your name</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Work email</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                    <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Plan</label>
                    <div class="grid gap-2">
                        <label
                            v-for="plan in plans"
                            :key="plan.slug"
                            class="flex cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm"
                            :class="form.plan === plan.slug ? 'border-blue-500 bg-blue-50' : 'border-slate-200'"
                        >
                            <span class="flex items-center gap-2">
                                <input v-model="form.plan" type="radio" :value="plan.slug" />
                                {{ plan.name }}
                            </span>
                            <span class="text-slate-500">${{ plan.price }}/mo</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                    Create workspace
                </button>
            </form>
        </div>
    </div>
</template>
