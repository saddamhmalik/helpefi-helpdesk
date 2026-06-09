<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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

const provisioningStep = ref(0);

const provisioningSteps = [
    'Creating your workspace database',
    'Configuring channels and email',
    'Setting up SLA policies',
    'Preparing your admin account',
    'Launching your helpdesk',
];

let stepTimer = null;

watch(
    () => form.processing,
    (processing) => {
        if (processing) {
            provisioningStep.value = 0;
            stepTimer = setInterval(() => {
                provisioningStep.value = (provisioningStep.value + 1) % provisioningSteps.length;
            }, 1400);
            return;
        }

        if (stepTimer) {
            clearInterval(stepTimer);
            stepTimer = null;
        }
    },
);

const submit = () => {
    form.post('/register', {
        onError: () => {
            if (stepTimer) {
                clearInterval(stepTimer);
                stepTimer = null;
            }
        },
    });
};
</script>

<template>
    <Head title="Create workspace" />
    <div class="min-h-screen bg-slate-50 px-4 py-12">
        <div class="mx-auto max-w-lg">
            <div class="mb-8 text-center">
                <Link href="/" class="text-sm text-slate-500 hover:text-slate-700">← Back</Link>
                <h1 class="mt-4 text-2xl font-semibold text-slate-900">Create your helpdesk</h1>
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

                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-70" :disabled="form.processing">
                    {{ form.processing ? 'Creating workspace…' : 'Create workspace' }}
                </button>
            </form>
        </div>

        <Teleport to="body">
            <Transition name="provision-fade">
                <div
                    v-if="form.processing"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 px-4 backdrop-blur-sm"
                >
                    <div class="provision-card w-full max-w-md rounded-2xl border border-white/10 bg-white p-8 text-center shadow-2xl">
                        <div class="provision-ring mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-blue-50">
                            <div class="provision-spinner h-10 w-10 rounded-full border-[3px] border-blue-200 border-t-blue-600" />
                        </div>
                        <h2 class="text-lg font-semibold text-slate-900">Building your workspace</h2>
                        <p class="provision-step mt-3 text-sm text-slate-600">{{ provisioningSteps[provisioningStep] }}</p>
                        <div class="mt-6 flex justify-center gap-1.5">
                            <span
                                v-for="(_, index) in provisioningSteps"
                                :key="index"
                                class="h-1.5 rounded-full transition-all duration-500"
                                :class="index === provisioningStep ? 'w-6 bg-blue-600' : 'w-1.5 bg-slate-200'"
                            />
                        </div>
                        <p class="mt-6 text-xs text-slate-400">You will be redirected to your workspace when ready.</p>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.provision-fade-enter-active,
.provision-fade-leave-active {
    transition: opacity 0.35s ease;
}

.provision-fade-enter-from,
.provision-fade-leave-to {
    opacity: 0;
}

.provision-card {
    animation: provision-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.provision-spinner {
    animation: provision-spin 0.85s linear infinite;
}

.provision-ring {
    animation: provision-pulse 2s ease-in-out infinite;
}

.provision-step {
    animation: provision-step 0.5s ease;
}

@keyframes provision-pop {
    from {
        opacity: 0;
        transform: scale(0.92) translateY(12px);
    }

    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes provision-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes provision-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.15);
    }

    50% {
        box-shadow: 0 0 0 12px rgba(37, 99, 235, 0);
    }
}

@keyframes provision-step {
    from {
        opacity: 0;
        transform: translateY(6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
