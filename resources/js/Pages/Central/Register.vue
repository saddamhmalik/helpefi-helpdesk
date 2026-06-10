<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import CentralSeoHead from '../../Components/CentralSeoHead.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brand: { type: String, default: 'Helpdesk' },
    trialDays: { type: Number, default: 14 },
    centralDomain: { type: String, default: '' },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const centralDomain = computed(() => props.centralDomain || window.location.hostname);

const workspaceDomainSuffix = computed(() => {
    const domain = centralDomain.value;
    const port = window.location.port;
    const defaultPort = window.location.protocol === 'https:' ? '443' : '80';

    if (! port || port === defaultPort) {
        return domain;
    }

    return `${domain}:${port}`;
});

const form = useForm({
    organization_name: '',
    slug: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const slugTouched = ref(false);
const provisioningStep = ref(0);

const provisioningSteps = [
    'Creating your workspace database',
    'Configuring channels and email',
    'Setting up SLA policies',
    'Preparing your admin account',
    'Launching your helpdesk',
];

const passwordStrength = computed(() => {
    const value = form.password;

    if (! value) {
        return { score: 0, label: '', width: '0%', color: 'bg-slate-200' };
    }

    let score = 0;

    if (value.length >= 8) score += 1;
    if (value.length >= 12) score += 1;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;

    const labels = ['Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['bg-red-500', 'bg-amber-500', 'bg-blue-500', 'bg-emerald-500'];
    const index = Math.min(score, 3);

    return { label: labels[index], color: colors[index], width: `${((index + 1) / 4) * 100}%` };
});

const slugify = (value) => value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 63);

let stepTimer = null;

watch(() => form.organization_name, (name) => {
    if (! slugTouched.value) {
        form.slug = slugify(name);
    }
});

watch(() => form.processing, (processing) => {
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
});

const onSlugInput = () => {
    slugTouched.value = true;
};

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

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <CentralSeoHead page="register" :brand="brand" :trial-days="trialDays" :seo="seo" />
    <CentralLayout :brand="brand" :trial-days="trialDays" :show-footer="false">
        <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
            <div class="mx-auto grid max-w-6xl lg:min-h-[calc(100vh-4rem)] lg:grid-cols-2">
                <aside class="relative hidden overflow-hidden bg-slate-950 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-20 top-0 h-72 w-72 rounded-full bg-blue-600/20 blur-3xl" />
                        <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-indigo-500/15 blur-3xl" />
                    </div>
                    <div class="relative">
                        <Link href="/" class="text-sm text-slate-400 transition hover:text-white">← Back to home</Link>
                        <div class="mt-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-blue-200">
                            {{ trialDays }}-day free trial · No credit card
                        </div>
                        <h1 class="mt-6 text-3xl font-semibold leading-tight tracking-tight">
                            Create your {{ brand }} workspace
                        </h1>
                        <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                            Get full access during your trial — tickets, chat, KB, service catalog, automation, and more. Upgrade to Enterprise for Service Desk ITSM, SSO, and AI.
                        </p>
                    </div>
                    <ul class="relative space-y-4">
                        <li v-for="item in ['Full platform access during trial', 'Guided email, chat & channel setup', 'Service catalog & SLA wizard included', 'Choose a plan only when trial ends']" :key="item" class="flex items-center gap-3 text-sm text-slate-300">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            </span>
                            {{ item }}
                        </li>
                    </ul>
                </aside>

                <div class="px-4 py-8 sm:px-6 lg:py-12">
                    <div class="mx-auto max-w-lg">
                        <div class="mb-6 lg:hidden">
                            <Link href="/" class="text-sm text-slate-500 hover:text-slate-700">← Back to home</Link>
                            <h1 class="mt-4 text-2xl font-semibold text-slate-900">{{ $t('central.start_your_free_trial') }}</h1>
                            <p class="mt-1 text-sm text-slate-600">{{ trialDays }} days free · No credit card · No plan selection needed</p>
                        </div>

                        <form class="space-y-6" @submit.prevent="submit">
                            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">1</span>
                                    <div>
                                        <h2 class="font-semibold text-slate-900">{{ $t('settings.groups.workspace') }}</h2>
                                        <p class="text-xs text-slate-500">{{ $t('central.name_and_url_for_your_team') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.organization_name') }}</label>
                                        <input v-model="form.organization_name" type="text" required :class="inputClass" :placeholder="$t('central.acme_support')" />
                                        <p v-if="form.errors.organization_name" class="mt-1.5 text-xs text-red-600">{{ form.errors.organization_name }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.workspace_url') }}</label>
                                        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                            <input v-model="form.slug" type="text" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 text-sm focus:outline-none" :placeholder="$t('central.acme')" @input="onSlugInput" />
                                            <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ workspaceDomainSuffix }}</span>
                                        </div>
                                        <p v-if="form.errors.slug" class="mt-1.5 text-xs text-red-600">{{ form.errors.slug }}</p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">2</span>
                                    <div>
                                        <h2 class="font-semibold text-slate-900">{{ $t('central.your_account') }}</h2>
                                        <p class="text-xs text-slate-500">{{ $t('central.youll_be_the_workspace_admin') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.full_name') }}</label>
                                        <input v-model="form.name" type="text" required :class="inputClass" :placeholder="$t('central.jane_admin')" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('central.work_email') }}</label>
                                        <input v-model="form.email" type="email" required :class="inputClass" :placeholder="$t('central.you_company_com')" />
                                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('settings.password') }}</label>
                                        <input v-model="form.password" type="password" required minlength="8" :class="inputClass" :placeholder="$t('central.at_least_8_characters')" />
                                        <div v-if="form.password" class="mt-2">
                                            <div class="h-1 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full transition-all duration-300" :class="passwordStrength.color" :style="{ width: passwordStrength.width }" />
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">{{ passwordStrength.label }}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $t('profile.confirm_password_disable') }}</label>
                                        <input v-model="form.password_confirmation" type="password" required :class="inputClass" />
                                    </div>
                                </div>
                            </section>

                            <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70" :disabled="form.processing">
                                {{ form.processing ? 'Creating workspace…' : `Start ${trialDays}-day free trial` }}
                            </button>

                            <p class="text-center text-xs text-slate-500">
                                Already have a workspace?
                                <Link href="/login" class="font-medium text-blue-600 hover:text-blue-700">{{ $t('central.sign_in') }}</Link>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="provision-fade">
                <div v-if="form.processing" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 px-4 backdrop-blur-sm">
                    <div class="provision-card w-full max-w-md rounded-2xl border border-white/10 bg-white p-8 text-center shadow-2xl">
                        <div class="provision-ring mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-blue-50">
                            <div class="provision-spinner h-10 w-10 rounded-full border-[3px] border-blue-200 border-t-blue-600" />
                        </div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $t('central.building_your_workspace') }}</h2>
                        <p class="provision-step mt-3 text-sm text-slate-600">{{ provisioningSteps[provisioningStep] }}</p>
                        <p class="mt-6 text-xs text-slate-400">Redirecting to {{ form.slug ? `${form.slug}.${workspaceDomainSuffix}` : 'your workspace' }}…</p>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </CentralLayout>
</template>

<style scoped>
.provision-fade-enter-active, .provision-fade-leave-active { transition: opacity 0.35s ease; }
.provision-fade-enter-from, .provision-fade-leave-to { opacity: 0; }
.provision-card { animation: provision-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1); }
.provision-spinner { animation: provision-spin 0.85s linear infinite; }
.provision-ring { animation: provision-pulse 2s ease-in-out infinite; }
.provision-step { animation: provision-step 0.5s ease; }
@keyframes provision-pop { from { opacity: 0; transform: scale(0.92) translateY(12px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes provision-spin { to { transform: rotate(360deg); } }
@keyframes provision-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.15); } 50% { box-shadow: 0 0 0 12px rgba(37, 99, 235, 0); } }
@keyframes provision-step { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
</style>
