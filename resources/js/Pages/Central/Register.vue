<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate, useMarketingEnglish } from '../../composables/useMarketingEnglish.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    centralDomain: { type: String, default: '' },
    seo: { type: Object, default: () => ({}) },
    verificationSent: { type: Boolean, default: false },
    verificationEmail: { type: String, default: '' },
    registerContent: { type: Object, default: () => ({}) },
    marketingLabels: { type: Object, default: () => ({}) },
});

const page = usePage();
const platformName = computed(() => props.brand || 'helpefi');
const { label } = useMarketingEnglish(platformName, computed(() => page.props.marketingLabels ?? props.marketingLabels));

const WORKSPACE_FIELDS = ['organization_name', 'slug'];
const PROFILE_FIELDS = ['name', 'email'];
const SECURITY_FIELDS = ['password', 'password_confirmation'];
const ACCOUNT_FIELDS = [...PROFILE_FIELDS, ...SECURITY_FIELDS];
const FORM_STEP_COUNT = 3;
const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const currentStep = ref(1);
const stepErrors = ref({});

const form = useForm({
    organization_name: '',
    slug: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const flashError = computed(() => page.props.flash?.error ?? '');

const serverErrors = computed(() => ({
    ...page.props.errors,
    ...form.errors,
}));

const fieldError = (field) => stepErrors.value[field]
    ?? form.errors[field]
    ?? page.props.errors?.[field]
    ?? '';

const errorList = computed(() => {
    const local = Object.entries(stepErrors.value).map(([field, message]) => ({ field, message }));

    if (local.length) {
        return local;
    }

    return Object.values(form.errors).length
        ? Object.entries(form.errors).map(([field, message]) => ({ field, message }))
        : Object.entries(page.props.errors ?? {}).map(([field, message]) => ({ field, message }));
});

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

const slugTouched = ref(false);
const slugCheckStatus = ref('idle');
const slugCheckSlug = ref('');
let slugCheckTimer = null;
let slugCheckRequestId = 0;

const registerCopy = computed(() => page.props.registerContent ?? props.registerContent ?? {});

const onboarding = (key, params = {}) => formatMarketingTemplate(registerCopy.value[key] ?? key, {
    days: props.trialDays,
    brand: props.brand,
    ...params,
});

const platformName = computed(() => props.brand);

const sidebarBenefits = computed(() => [
    onboarding('benefit_inbox'),
    onboarding('benefit_setup'),
    onboarding('benefit_trial'),
    onboarding('benefit_upgrade'),
]);

const verifyTimeline = computed(() => [
    { title: onboarding('verify_timeline_verify_title'), body: onboarding('verify_timeline_verify_body') },
    { title: onboarding('verify_timeline_build_title'), body: onboarding('verify_timeline_build_body') },
    { title: onboarding('verify_timeline_go_title'), body: onboarding('verify_timeline_go_body') },
]);

const progressSteps = computed(() => {
    if (props.verificationSent) {
        return [
            { key: 'workspace', label: onboarding('progress_workspace'), state: 'complete' },
            { key: 'profile', label: onboarding('progress_profile'), state: 'complete' },
            { key: 'security', label: onboarding('progress_security'), state: 'complete' },
            { key: 'verify', label: onboarding('progress_verify'), state: 'active' },
        ];
    }

    const stepState = (step) => {
        if (currentStep.value === step) {
            return 'active';
        }

        return currentStep.value > step ? 'complete' : 'pending';
    };

    return [
        { key: 'workspace', label: onboarding('progress_workspace'), state: stepState(1) },
        { key: 'profile', label: onboarding('progress_profile'), state: stepState(2) },
        { key: 'security', label: onboarding('progress_security'), state: stepState(3) },
        { key: 'verify', label: onboarding('progress_verify'), state: 'pending' },
    ];
});

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

    const labels = [
        onboarding('password_weak'),
        onboarding('password_fair'),
        onboarding('password_good'),
        onboarding('password_strong'),
    ];
    const colors = ['bg-red-500', 'bg-amber-500', 'bg-blue-500', 'bg-emerald-500'];
    const index = Math.min(score, 3);

    return { label: labels[index], color: colors[index], width: `${((index + 1) / 4) * 100}%` };
});

const slugValid = computed(() => slugCheckStatus.value === 'available' && slugCheckSlug.value === form.slug.trim());

const slugFieldState = computed(() => {
    if (fieldError('slug')) {
        return 'error';
    }

    if (slugCheckStatus.value === 'checking') {
        return 'checking';
    }

    if (slugCheckStatus.value === 'taken' || slugCheckStatus.value === 'invalid') {
        return 'error';
    }

    if (slugValid.value) {
        return 'success';
    }

    return 'idle';
});

const slugFeedbackMessage = computed(() => {
    if (fieldError('slug')) {
        return fieldError('slug');
    }

    if (slugCheckStatus.value === 'checking') {
        return onboarding('workspace_slug_checking');
    }

    if (slugCheckStatus.value === 'taken') {
        return onboarding('workspace_slug_taken');
    }

    if (slugCheckStatus.value === 'invalid' && form.slug.trim() !== '') {
        return onboarding('workspace_slug_invalid');
    }

    if (slugValid.value) {
        return onboarding('workspace_url_ready');
    }

    return '';
});

const displayOrgName = computed(() => form.organization_name.trim() || onboarding('workspace_preview_fallback'));

const displaySlug = computed(() => form.slug.trim() || 'nova');

const workspacePreviewUrl = computed(() => `${displaySlug.value}.${workspaceDomainSuffix.value}`);

const orgInitials = computed(() => {
    const parts = displayOrgName.value.split(/\s+/).filter(Boolean);

    if (! parts.length) {
        return 'WS';
    }

    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join('');
});

const progressPercent = computed(() => {
    if (props.verificationSent) {
        return 100;
    }

    return (currentStep.value / (FORM_STEP_COUNT + 1)) * 100;
});

const isLastFormStep = computed(() => currentStep.value === FORM_STEP_COUNT);

const stepMeta = computed(() => {
    const map = {
        1: {
            title: onboarding('form_step_workspace'),
            subtitle: label('name_and_url_for_your_team'),
        },
        2: {
            title: onboarding('form_step_profile'),
            subtitle: onboarding('form_step_profile_sub'),
        },
        3: {
            title: onboarding('form_step_security'),
            subtitle: onboarding('form_step_security_sub'),
        },
    };

    return map[currentStep.value] ?? map[1];
});

const trustBadges = computed(() => [
    onboarding('trust_no_card'),
    onboarding('trust_cancel'),
    onboarding('trust_setup'),
]);

const slugify = (value) => value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 63);

const inferStepFromErrors = (errors) => {
    const fields = Object.keys(errors);

    if (fields.some((field) => SECURITY_FIELDS.includes(field))) {
        return 3;
    }

    if (fields.some((field) => PROFILE_FIELDS.includes(field))) {
        return 2;
    }

    if (fields.some((field) => WORKSPACE_FIELDS.includes(field))) {
        return 1;
    }

    return currentStep.value;
};

const syncStepFromServerErrors = () => {
    if (props.verificationSent) {
        return;
    }

    const errors = { ...page.props.errors, ...form.errors };

    if (! Object.keys(errors).length) {
        return;
    }

    currentStep.value = inferStepFromErrors(errors);
};

const clearStepErrors = () => {
    stepErrors.value = {};
};

const validateWorkspaceStep = () => {
    const errors = {};

    if (! form.organization_name.trim()) {
        errors.organization_name = onboarding('workspace_name_required');
    }

    if (! form.slug.trim()) {
        errors.slug = onboarding('workspace_slug_required');
    } else if (! SLUG_PATTERN.test(form.slug)) {
        errors.slug = onboarding('workspace_slug_invalid');
    } else if (slugCheckStatus.value === 'checking') {
        errors.slug = onboarding('workspace_slug_checking_wait');
    } else if (slugCheckStatus.value === 'taken') {
        errors.slug = onboarding('workspace_slug_taken');
    } else if (slugCheckStatus.value === 'invalid') {
        errors.slug = onboarding('workspace_slug_invalid');
    } else if (slugCheckStatus.value !== 'available') {
        errors.slug = onboarding('workspace_slug_checking_wait');
    }

    stepErrors.value = errors;

    return ! Object.keys(errors).length;
};

const validateProfileStep = () => {
    const errors = {};

    if (! form.name.trim()) {
        errors.name = onboarding('name_required');
    }

    if (! form.email.trim()) {
        errors.email = onboarding('email_required');
    } else if (! EMAIL_PATTERN.test(form.email.trim())) {
        errors.email = onboarding('email_invalid');
    }

    stepErrors.value = errors;

    return ! Object.keys(errors).length;
};

const validateSecurityStep = () => {
    const errors = {};

    if (! form.password) {
        errors.password = onboarding('password_required');
    } else if (form.password.length < 8) {
        errors.password = onboarding('password_min_length');
    }

    if (! form.password_confirmation) {
        errors.password_confirmation = onboarding('password_confirm_required');
    } else if (form.password !== form.password_confirmation) {
        errors.password_confirmation = onboarding('password_mismatch');
    }

    stepErrors.value = errors;

    return ! Object.keys(errors).length;
};

const goToStep = (step) => {
    if (props.verificationSent || step >= currentStep.value) {
        return;
    }

    clearStepErrors();
    currentStep.value = step;
};

const handleStepperClick = (step) => {
    const targets = { workspace: 1, profile: 2, security: 3 };

    if (step.state !== 'complete' || step.key === 'verify') {
        return;
    }

    goToStep(targets[step.key]);
};

const advanceStep = () => {
    if (currentStep.value === 1 && ! validateWorkspaceStep()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return false;
    }

    if (currentStep.value === 2 && ! validateProfileStep()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return false;
    }

    clearStepErrors();

    if (currentStep.value < FORM_STEP_COUNT) {
        currentStep.value += 1;
    }

    return true;
};

const nextStep = () => {
    advanceStep();
};

const prevStep = () => {
    if (currentStep.value <= 1) {
        return;
    }

    clearStepErrors();
    currentStep.value -= 1;
};

watch(() => form.organization_name, (name) => {
    if (! slugTouched.value) {
        form.slug = slugify(name);
    }
});

watch(() => form.slug, (slug) => {
    if (stepErrors.value.slug) {
        const { slug: _removed, ...rest } = stepErrors.value;
        stepErrors.value = rest;
    }

    scheduleSlugCheck(slug);
});

const onSlugInput = () => {
    slugTouched.value = true;
    form.slug = slugify(form.slug);
};

const resetSlugCheck = () => {
    slugCheckStatus.value = 'idle';
    slugCheckSlug.value = '';
};

const checkSlugAvailability = async (slug) => {
    const trimmed = slug.trim();

    if (trimmed === '') {
        resetSlugCheck();

        return;
    }

    if (! SLUG_PATTERN.test(trimmed)) {
        slugCheckSlug.value = trimmed;
        slugCheckStatus.value = 'invalid';

        return;
    }

    slugCheckStatus.value = 'checking';
    const requestId = ++slugCheckRequestId;

    try {
        const response = await fetch(`/api/register/check-slug?slug=${encodeURIComponent(trimmed)}`, {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            throw new Error('slug_check_failed');
        }

        const data = await response.json();

        if (requestId !== slugCheckRequestId) {
            return;
        }

        slugCheckSlug.value = data.slug ?? trimmed;
        slugCheckStatus.value = data.status ?? (data.available ? 'available' : 'taken');
    } catch {
        if (requestId !== slugCheckRequestId) {
            return;
        }

        resetSlugCheck();
    }
};

const scheduleSlugCheck = (slug) => {
    if (slugCheckTimer) {
        clearTimeout(slugCheckTimer);
    }

    slugCheckTimer = setTimeout(() => {
        checkSlugAvailability(slug);
    }, 350);
};

const submit = () => {
    if (currentStep.value < FORM_STEP_COUNT) {
        nextStep();

        return;
    }

    if (! validateSecurityStep()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    clearStepErrors();

    form.post('/register', {
        preserveScroll: true,
        onError: () => {
            syncStepFromServerErrors();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    });
};

onMounted(() => {
    syncStepFromServerErrors();

    if (form.slug.trim()) {
        scheduleSlugCheck(form.slug);
    }
});

onBeforeUnmount(() => {
    if (slugCheckTimer) {
        clearTimeout(slugCheckTimer);
    }
});

watch(serverErrors, () => {
    syncStepFromServerErrors();
}, { deep: true });

const stepCircleClass = (state) => {
    if (state === 'active') {
        return 'bg-blue-600 text-white shadow-lg shadow-blue-600/30 scale-110';
    }

    if (state === 'complete') {
        return 'bg-blue-600 text-white';
    }

    return 'bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
};

const connectorClass = (index) => {
    const steps = progressSteps.value;

    if (steps[index]?.state === 'complete') {
        return 'bg-blue-200 dark:bg-blue-900/60';
    }

    return 'bg-slate-200 dark:bg-slate-800';
};

const inputClass = (field) => {
    const base = 'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 dark:bg-slate-900 dark:text-slate-100';
    const hasError = !!fieldError(field);

    if (hasError) {
        return `${base} border-red-300 focus:border-red-500 focus:ring-red-500/20 dark:border-red-900/60`;
    }

    return `${base} border-slate-200 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-800`;
};
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :show-footer="false" :show-promo-bar="false" minimal-header>
        <div class="register-shell relative min-h-[calc(100dvh-3.5rem)] overflow-hidden bg-slate-50 dark:bg-slate-950 sm:min-h-[calc(100dvh-4rem)]">
            <div class="pointer-events-none absolute inset-0 register-grid opacity-[0.35]" />
            <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-blue-400/10 blur-3xl" />
            <div class="pointer-events-none absolute -right-24 bottom-0 h-80 w-80 rounded-full bg-violet-400/10 blur-3xl" />

            <div class="relative mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:py-10">
                <div class="register-card overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 lg:grid lg:grid-cols-2 lg:items-stretch">
                    <aside class="relative hidden overflow-hidden bg-slate-950 px-10 py-10 text-white lg:flex lg:h-full lg:min-h-full lg:flex-col">
                        <div class="pointer-events-none absolute inset-0">
                            <div class="absolute -right-20 top-0 h-72 w-72 rounded-full bg-blue-600/25 blur-3xl register-glow" />
                            <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl register-glow-delayed" />
                        </div>

                        <div class="relative">
                            <Link href="/" class="inline-flex items-center gap-1.5 text-sm text-slate-400 transition hover:text-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                Back to home
                            </Link>
                            <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-blue-200 backdrop-blur-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 register-pulse-dot" />
                                {{ onboarding('hero_eyebrow') }}
                            </div>
                            <h1 class="mt-5 text-3xl font-semibold leading-tight tracking-tight">
                                {{ onboarding('hero_title') }}
                            </h1>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-400">
                                {{ onboarding('hero_subtitle') }}
                            </p>
                        </div>

                        <div class="relative mt-8 flex flex-1 flex-col gap-6">
                            <div class="register-preview rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm transition-all duration-300">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ onboarding('workspace_preview_label') }}</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition-transform duration-300" :class="form.organization_name ? 'scale-100' : 'scale-95 opacity-80'">
                                        {{ orgInitials }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-white transition-all duration-300">{{ displayOrgName }}</p>
                                        <p class="truncate text-xs text-blue-300/90 transition-all duration-300">{{ workspacePreviewUrl }}</p>
                                    </div>
                                    <span v-if="slugValid" class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400 register-check-pop">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ onboarding('mock_inbox') }}</p>
                                        <p class="mt-0.5 text-lg font-semibold text-white">3</p>
                                        <p class="text-[10px] text-slate-500">{{ onboarding('mock_open_tickets') }}</p>
                                    </div>
                                    <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                                        <p class="text-[10px] uppercase tracking-wide text-slate-500">AI</p>
                                        <p class="mt-0.5 text-sm font-semibold text-emerald-300">{{ onboarding('mock_ai_copilot') }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-start gap-2.5 rounded-xl border border-indigo-400/20 bg-indigo-500/10 px-3 py-2.5">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-200">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-200/90">{{ onboarding('custom_domain_badge') }}</p>
                                        <p class="mt-0.5 text-xs leading-relaxed text-indigo-100/80">{{ onboarding('custom_domain_sidebar') }}</p>
                                    </div>
                                </div>
                            </div>

                            <ul class="mt-auto space-y-3">
                                <li
                                    v-for="item in sidebarBenefits"
                                    :key="item"
                                    class="flex items-center gap-3 text-sm text-slate-300"
                                >
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </aside>

                    <div class="px-4 py-6 sm:px-8 sm:py-8 lg:flex lg:h-full lg:min-h-full lg:flex-col lg:py-10">
                        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                            <div class="mb-6 lg:hidden">
                                <Link href="/" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">← Back to home</Link>
                                <h1 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ onboarding('hero_title') }}</h1>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ onboarding('hero_eyebrow') }}</p>
                            </div>

                            <div class="mb-6">
                                <div class="mb-3 h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-500 ease-out" :style="{ width: `${progressPercent}%` }" />
                                </div>
                                <div class="flex items-center gap-1">
                                    <template v-for="(step, index) in progressSteps" :key="step.key">
                                        <button
                                            type="button"
                                            class="group flex min-w-0 flex-1 flex-col items-center gap-1.5 disabled:cursor-default"
                                            :class="step.state === 'pending' ? 'opacity-45' : ''"
                                            :disabled="step.state !== 'complete' || step.key === 'verify'"
                                            @click="handleStepperClick(step)"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-full text-[10px] font-bold transition-all duration-300 sm:h-9 sm:w-9 sm:text-xs"
                                                :class="stepCircleClass(step.state)"
                                            >
                                                <template v-if="step.state === 'complete'">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                                </template>
                                                <template v-else>{{ index + 1 }}</template>
                                            </span>
                                            <span
                                                class="truncate text-[10px] font-semibold uppercase tracking-wide transition-colors"
                                                :class="step.state === 'active' ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400'"
                                            >
                                                {{ step.label }}
                                            </span>
                                        </button>
                                        <div
                                            v-if="index < progressSteps.length - 1"
                                            class="mb-5 h-0.5 flex-1 rounded-full transition-colors duration-500"
                                            :class="connectorClass(index)"
                                        />
                                    </template>
                                </div>
                            </div>

                            <div v-if="flashError" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                                {{ flashError }}
                            </div>

                            <div v-if="hasValidationErrors && !verificationSent" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/40">
                                <p class="text-sm font-semibold text-red-800 dark:text-red-300">{{ onboarding('fix_errors') }}</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                                    <li v-for="error in errorList" :key="error.field">{{ error.message }}</li>
                                </ul>
                            </div>

                            <form v-if="!verificationSent" class="flex flex-1 flex-col space-y-6" @submit.prevent="submit">
                                <div class="register-step-shell flex-1">
                                    <Transition name="register-step" mode="out-in">
                                        <section
                                            v-if="currentStep === 1"
                                            key="workspace"
                                            class="register-form-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-6"
                                        >
                                        <div class="mb-5">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ onboarding('step_of_total', { current: 1, total: FORM_STEP_COUNT }) }}</p>
                                            <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ stepMeta.title }}</h2>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ stepMeta.subtitle }}</p>
                                        </div>
                                        <div class="register-form-fields space-y-5">
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('organization_name') }}</label>
                                                <div class="relative">
                                                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                    </span>
                                                    <input v-model="form.organization_name" type="text" required autofocus :class="`${inputClass('organization_name')} pl-10`" :placeholder="label('acme_support')" />
                                                </div>
                                                <p v-if="fieldError('organization_name')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('organization_name') }}</p>
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('workspace_url') }}</label>
                                                <div
                                                    class="flex overflow-hidden rounded-xl border bg-white shadow-sm transition focus-within:ring-2 dark:bg-slate-900"
                                                    :class="slugFieldState === 'error'
                                                        ? 'border-red-300 focus-within:border-red-500 focus-within:ring-red-500/20 dark:border-red-900/60'
                                                        : slugFieldState === 'success'
                                                            ? 'border-emerald-300 focus-within:border-emerald-500 focus-within:ring-emerald-500/20 dark:border-emerald-900/50'
                                                            : slugFieldState === 'checking'
                                                                ? 'border-blue-300 focus-within:border-blue-500 focus-within:ring-blue-500/20 dark:border-blue-900/50'
                                                                : 'border-slate-200 focus-within:border-blue-500 focus-within:ring-blue-500/20 dark:border-slate-800'"
                                                >
                                                    <span class="pointer-events-none flex items-center pl-3.5 text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                                    </span>
                                                    <input v-model="form.slug" type="text" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-w-0 flex-1 border-0 bg-transparent px-2 py-2.5 text-sm focus:outline-none dark:text-slate-100" :placeholder="label('acme')" @input="onSlugInput" />
                                                    <span class="flex min-w-0 shrink items-center bg-slate-50 px-2 text-xs text-slate-500 dark:bg-slate-950 dark:text-slate-400 sm:px-3 sm:text-sm">.{{ workspaceDomainSuffix }}</span>
                                                </div>
                                                <p v-if="slugFeedbackMessage" class="mt-1.5 flex items-center gap-1 text-xs" :class="slugFieldState === 'error'
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : slugFieldState === 'checking'
                                                        ? 'text-blue-600 dark:text-blue-400'
                                                        : 'text-emerald-600 dark:text-emerald-400'">
                                                    <svg v-if="slugFieldState === 'checking'" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                    </svg>
                                                    <svg v-else-if="slugFieldState === 'success'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                                    <svg v-else-if="slugFieldState === 'error'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    {{ slugFeedbackMessage }}
                                                </p>
                                            </div>

                                            <div class="rounded-xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-4 ring-1 ring-indigo-100 dark:border-indigo-900/50 dark:from-indigo-950/40 dark:via-slate-900 dark:to-blue-950/30 dark:ring-indigo-900/40">
                                                <div class="flex gap-3">
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/25">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                                    </span>
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ onboarding('custom_domain_title') }}</p>
                                                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">{{ onboarding('custom_domain_badge') }}</span>
                                                        </div>
                                                        <p class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                                                            {{ onboarding('custom_domain_body', { workspaceUrl: workspacePreviewUrl }) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 lg:hidden">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ onboarding('workspace_preview_label') }}</p>
                                                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ displayOrgName }}</p>
                                                <p class="text-xs text-blue-600 dark:text-blue-400">{{ workspacePreviewUrl }}</p>
                                            </div>
                                        </div>
                                    </section>

                                    <section
                                        v-else-if="currentStep === 2"
                                        key="profile"
                                        class="register-form-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-6"
                                    >
                                        <div class="mb-5">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ onboarding('step_of_total', { current: 2, total: FORM_STEP_COUNT }) }}</p>
                                            <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ onboarding('form_step_profile') }}</h2>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ onboarding('form_step_profile_sub') }}</p>
                                            <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/80">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ displayOrgName }}</p>
                                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ workspacePreviewUrl }}</p>
                                                </div>
                                                <button type="button" class="shrink-0 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400" @click="goToStep(1)">
                                                    {{ onboarding('edit_workspace') }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="register-form-fields space-y-4">
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('full_name') }}</label>
                                                <input v-model="form.name" type="text" required autofocus :class="inputClass('name')" :placeholder="label('jane_admin')" />
                                                <p v-if="fieldError('name')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('name') }}</p>
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('work_email') }}</label>
                                                <input v-model="form.email" type="email" required :class="inputClass('email')" :placeholder="label('you_company_com')" />
                                                <p v-if="fieldError('email')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('email') }}</p>
                                            </div>
                                        </div>
                                    </section>

                                    <section
                                        v-else
                                        key="security"
                                        class="register-form-panel rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-6"
                                    >
                                        <div class="mb-5">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ onboarding('step_of_total', { current: 3, total: FORM_STEP_COUNT }) }}</p>
                                            <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ onboarding('form_step_security') }}</h2>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ onboarding('form_step_security_sub') }}</p>
                                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800/80">
                                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ form.name || label('jane_admin') }}</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ form.email || label('you_company_com') }} · {{ workspacePreviewUrl }}</p>
                                            </div>
                                        </div>
                                        <div class="register-form-fields space-y-4">
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('password') }}</label>
                                                <input v-model="form.password" type="password" required minlength="8" autofocus :class="inputClass('password')" :placeholder="label('at_least_8_characters')" />
                                                <p v-if="fieldError('password')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('password') }}</p>
                                                <div v-if="form.password" class="mt-2.5">
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                        <div class="h-full rounded-full transition-all duration-500 ease-out" :class="passwordStrength.color" :style="{ width: passwordStrength.width }" />
                                                    </div>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ passwordStrength.label }}</p>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('confirm_password') }}</label>
                                                <input v-model="form.password_confirmation" type="password" required :class="inputClass('password_confirmation')" />
                                                <p v-if="fieldError('password_confirmation')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('password_confirmation') }}</p>
                                            </div>
                                        </div>
                                    </section>
                                    </Transition>
                                </div>

                                <div class="register-form-actions mt-auto space-y-6">
                                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                                    <button
                                        v-if="currentStep > 1"
                                        type="button"
                                        class="inline-flex h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 sm:w-auto sm:min-w-[7rem]"
                                        :disabled="form.processing"
                                        @click="prevStep"
                                    >
                                        {{ onboarding('back_cta') }}
                                    </button>
                                    <button
                                        type="submit"
                                        class="register-cta group inline-flex h-12 w-full flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:from-blue-500 hover:to-indigo-500 hover:shadow-blue-600/35 disabled:cursor-not-allowed disabled:opacity-70"
                                        :disabled="form.processing"
                                    >
                                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        <span>{{ form.processing
                                            ? onboarding('submit_loading')
                                            : isLastFormStep
                                                ? onboarding('submit_cta')
                                                : onboarding('continue_cta') }}</span>
                                        <svg v-if="!form.processing" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                                    <span
                                        v-for="badge in trustBadges"
                                        :key="badge"
                                        class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                        {{ badge }}
                                    </span>
                                </div>

                                <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                                    {{ onboarding('already_have_workspace') }}
                                    <Link href="/login" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ label('sign_in') }}</Link>
                                </p>
                                </div>
                            </form>

                            <div v-else class="register-verify-shell flex flex-1 flex-col space-y-6">
                                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-8">
                                    <div class="register-mail-pulse relative mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 8l9 6 9-6M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                                    </div>
                                    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ onboarding('verify_title') }}</h2>
                                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                                        {{ onboarding('verify_lead') }}
                                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ verificationEmail }}</span>.
                                        {{ onboarding('verify_hint') }}
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500">{{ onboarding('verify_expiry') }}</p>

                                    <button type="button" class="register-cta mt-6 w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:from-blue-500 hover:to-indigo-500 disabled:cursor-not-allowed disabled:opacity-70" :disabled="resending" @click="resend">
                                        {{ resending ? onboarding('verify_resend_loading') : onboarding('verify_resend') }}
                                    </button>

                                    <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
                                        {{ onboarding('verify_wrong_email') }}
                                        <Link href="/register" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ onboarding('verify_start_over') }}</Link>
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-6">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ onboarding('verify_timeline_title') }}</p>
                                    <ol class="mt-4 space-y-4">
                                        <li
                                            v-for="(item, index) in verifyTimeline"
                                            :key="item.title"
                                            class="register-timeline-item flex gap-3"
                                            :style="{ animationDelay: `${index * 100}ms` }"
                                        >
                                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                {{ index + 1 }}
                                            </span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                                            </div>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>

<style scoped>
.register-grid {
    background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.22) 1px, transparent 0);
    background-size: 24px 24px;
}

@media (min-width: 1024px) {
    .register-card {
        min-height: 42rem;
    }

    .register-step-shell {
        min-height: 21rem;
    }

    .register-form-panel {
        display: flex;
        min-height: 21rem;
        flex-direction: column;
    }

    .register-form-fields {
        flex: 1;
    }

    .register-verify-shell {
        min-height: 30rem;
    }
}

.register-glow {
    animation: register-float 8s ease-in-out infinite;
}

.register-glow-delayed {
    animation: register-float 8s ease-in-out 2s infinite;
}

.register-timeline-item {
    animation: register-rise 0.45s ease both;
}

.register-preview {
    animation: register-rise 0.6s ease both;
}

.register-check-pop {
    animation: register-check-pop 0.35s ease both;
}

.register-pulse-dot {
    animation: register-pulse-dot 2s ease-in-out infinite;
}

.register-mail-pulse::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 9999px;
    border: 2px solid rgb(37 99 235 / 0.35);
    animation: register-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.register-cta:active:not(:disabled) {
    transform: scale(0.98);
}

.register-step-enter-active,
.register-step-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.register-step-enter-from {
    opacity: 0;
    transform: translateX(16px);
}

.register-step-leave-to {
    opacity: 0;
    transform: translateX(-16px);
}

@keyframes register-float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

@keyframes register-rise {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes register-check-pop {
    0% {
        opacity: 0;
        transform: scale(0.6);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes register-pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

@keyframes register-ping {
    0% {
        transform: scale(1);
        opacity: 0.6;
    }

    100% {
        transform: scale(1.35);
        opacity: 0;
    }
}
</style>
