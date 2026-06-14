<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    centralDomain: { type: String, default: '' },
    seo: { type: Object, default: () => ({}) },
    verificationSent: { type: Boolean, default: false },
    verificationEmail: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();

const flashError = computed(() => page.props.flash?.error ?? '');

const fieldError = (field) => form.errors[field] ?? page.props.errors?.[field] ?? '';

const errorList = computed(() => Object.values(form.errors).length
    ? Object.entries(form.errors).map(([field, message]) => ({ field, message }))
    : Object.entries(page.props.errors ?? {}).map(([field, message]) => ({ field, message })));

const hasValidationErrors = computed(() => errorList.value.length > 0);

const resending = ref(false);

const resend = () => {
    resending.value = true;
    router.post('/register/resend', { email: props.verificationEmail }, {
        preserveScroll: true,
        onFinish: () => {
            resending.value = false;
        },
    });
};

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

const platformName = computed(() => t('app.name'));

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

watch(() => form.organization_name, (name) => {
    if (! slugTouched.value) {
        form.slug = slugify(name);
    }
});

const onSlugInput = () => {
    slugTouched.value = true;
};

const submit = () => {
    form.post('/register', {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};

const inputClass = 'w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 shadow-sm transition placeholder:text-slate-400 dark:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :show-footer="false">
        <div class="min-h-[calc(100dvh-3.5rem)] bg-slate-50 dark:bg-slate-950 sm:min-h-[calc(100vh-4rem)]">
            <div class="mx-auto grid max-w-6xl lg:min-h-[calc(100vh-4rem)] lg:grid-cols-2">
                <aside class="relative hidden overflow-hidden bg-slate-950 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-20 top-0 h-72 w-72 rounded-full bg-blue-600/20 blur-3xl" />
                        <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-indigo-500/15 blur-3xl" />
                    </div>
                    <div class="relative">
                        <Link href="/" class="text-sm text-slate-400 dark:text-slate-500 transition hover:text-white">← Back to home</Link>
                        <div class="mt-8 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-blue-200">
                            {{ trialDays }}-day free trial · No credit card
                        </div>
                        <h1 class="mt-6 text-3xl font-semibold leading-tight tracking-tight">
                            Create your {{ platformName }} workspace
                        </h1>
                        <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400 dark:text-slate-500">
                            Get full access during your trial — tickets, chat, KB, service catalog, automation, and more. Upgrade to Pro for AI, SSO, and integrations. Add Service Desk ITSM as an optional add-on when you need ITIL workflows.
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
                            <Link href="/" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">← Back to home</Link>
                            <h1 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.start_your_free_trial') }}</h1>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ trialDays }} days free · No credit card · No plan selection needed</p>
                        </div>

                        <div v-if="flashError" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                            {{ flashError }}
                        </div>

                        <div v-if="hasValidationErrors && !verificationSent" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/40">
                            <p class="text-sm font-semibold text-red-800 dark:text-red-300">Please fix the following:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                                <li v-for="error in errorList" :key="error.field">{{ error.message }}</li>
                            </ul>
                        </div>

                        <form v-if="!verificationSent" class="space-y-6" @submit.prevent="submit">
                            <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">1</span>
                                    <div>
                                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('settings.groups.workspace') }}</h2>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('central.name_and_url_for_your_team') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.organization_name') }}</label>
                                        <input v-model="form.organization_name" type="text" required :class="inputClass" :placeholder="$t('central.acme_support')" />
                                        <p v-if="fieldError('organization_name')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('organization_name') }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.workspace_url') }}</label>
                                        <div class="flex overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20">
                                            <input v-model="form.slug" type="text" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 text-sm focus:outline-none" :placeholder="$t('central.acme')" @input="onSlugInput" />
                                            <span class="flex min-w-0 shrink items-center bg-slate-50 dark:bg-slate-950 px-2 text-xs text-slate-500 dark:text-slate-400 sm:px-3 sm:text-sm">.{{ workspaceDomainSuffix }}</span>
                                        </div>
                                        <p v-if="fieldError('slug')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('slug') }}</p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">2</span>
                                    <div>
                                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.your_account') }}</h2>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('central.youll_be_the_workspace_admin') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.full_name') }}</label>
                                        <input v-model="form.name" type="text" required :class="inputClass" :placeholder="$t('central.jane_admin')" />
                                        <p v-if="fieldError('name')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('name') }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.work_email') }}</label>
                                        <input v-model="form.email" type="email" required :class="inputClass" :placeholder="$t('central.you_company_com')" />
                                        <p v-if="fieldError('email')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('email') }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('settings.password') }}</label>
                                        <input v-model="form.password" type="password" required minlength="8" :class="inputClass" :placeholder="$t('central.at_least_8_characters')" />
                                        <p v-if="fieldError('password')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('password') }}</p>
                                        <div v-if="form.password" class="mt-2">
                                            <div class="h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-900">
                                                <div class="h-full rounded-full transition-all duration-300" :class="passwordStrength.color" :style="{ width: passwordStrength.width }" />
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ passwordStrength.label }}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('profile.confirm_password_disable') }}</label>
                                        <input v-model="form.password_confirmation" type="password" required :class="inputClass" />
                                        <p v-if="fieldError('password_confirmation')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('password_confirmation') }}</p>
                                    </div>
                                </div>
                            </section>

                            <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70" :disabled="form.processing">
                                {{ form.processing ? 'Sending verification link…' : `Start ${trialDays}-day free trial` }}
                            </button>

                            <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                                Already have a workspace?
                                <Link href="/login" class="font-medium text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">{{ $t('central.sign_in') }}</Link>
                            </p>
                        </form>

                        <div v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 text-center shadow-sm sm:p-8">
                            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 8l9 6 9-6M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                            </div>
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Check your inbox</h2>
                            <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                                We've sent a verification link to
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ verificationEmail }}</span>.
                                Click it to confirm your email — your workspace is created only after you verify.
                            </p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-500">The link expires in 24 hours. Don't forget to check your spam folder.</p>

                            <button type="button" class="mt-6 w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70" :disabled="resending" @click="resend">
                                {{ resending ? 'Resending…' : 'Resend verification email' }}
                            </button>

                            <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
                                Entered the wrong email?
                                <Link href="/register" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">Start over</Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>
