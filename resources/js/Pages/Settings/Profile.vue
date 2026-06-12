<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsPage from '../../Components/SettingsPage.vue';
import SettingsSectionNav from '../../Components/SettingsSectionNav.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';
import { applyAppearance } from '../../composables/useAppearance.js';

const props = defineProps({
    twoFactor: Object,
    mfaRequired: Boolean,
    locale: { type: String, default: 'en' },
    storedTimezone: { type: String, default: null },
    appearance: { type: String, default: 'system' },
    localeOptions: { type: Array, default: () => [] },
    timezoneOptions: { type: Array, default: () => [] },
    appearanceOptions: { type: Array, default: () => [] },
});

const { t } = useI18n();

const { activeSection } = useSettingsSection({
    defaultSection: 'profile',
    sections: ['profile', 'password', 'security'],
});

const pageTitles = computed(() => ({
    profile: { title: t('profile.title'), description: t('profile.description') },
    password: { title: t('profile.password_title'), description: t('profile.password_description') },
    security: { title: t('profile.security_title'), description: t('profile.security_description') },
}));

const sectionTabs = computed(() => [
    { id: 'profile', label: t('settings.profile') },
    { id: 'password', label: t('settings.password') },
    { id: 'security', label: t('settings.two_factor') },
]);

const page = usePage();
const user = computed(() => page.props.auth.user);
const helpdeskTimezone = computed(() => page.props.helpdesk?.timezone ?? 'UTC');
const setup = computed(() => page.props.flash?.two_factor_setup);
const recoveryCodes = computed(() => page.props.flash?.recovery_codes);

const timezoneHint = computed(() => t('profile.timezone_hint', { timezone: helpdeskTimezone.value }));

const profileForm = useForm({
    name: user.value.name,
    email: user.value.email,
    locale: props.locale,
    timezone: props.storedTimezone ?? '',
    appearance: props.appearance,
});

watch(
    () => profileForm.appearance,
    (value) => applyAppearance(value),
);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const confirmForm = useForm({
    code: '',
});

const disableForm = useForm({
    password: '',
});

const showDisable = ref(false);

const updateProfile = () => profileForm.put('/settings/profile', {
    onError: () => applyAppearance(page.props.appearance ?? page.props.auth?.user?.appearance ?? 'system'),
});
const updatePassword = () => passwordForm.put('/settings/password', {
    onSuccess: () => passwordForm.reset(),
});

const beginSetup = () => {
    confirmForm.reset();
    useForm({}).post('/settings/two-factor/setup', { preserveScroll: true });
};

const confirmSetup = () => {
    confirmForm.post('/settings/two-factor/confirm', { preserveScroll: true });
};

const disableTwoFactor = () => {
    disableForm.delete('/settings/two-factor', {
        preserveScroll: true,
        onSuccess: () => {
            disableForm.reset();
            showDisable.value = false;
        },
    });
};
</script>

<template>
    <SettingsPage
        :title="pageTitles[activeSection]?.title ?? t('profile.title')"
        :description="pageTitles[activeSection]?.description ?? ''"
    >
        <SettingsSectionNav
            path="/settings/profile"
            default-section="profile"
            :sections="sectionTabs"
            :active-section="activeSection"
        />

        <div
            v-if="mfaRequired && !twoFactor.enabled"
            class="mb-6 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900"
        >
            {{ t('profile.mfa_required') }}
        </div>

        <div v-show="activeSection === 'profile'" class="max-w-2xl rounded-xl border agent-border agent-panel p-6 shadow-sm">
            <form class="space-y-4" @submit.prevent="updateProfile">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.name') }}</label>
                    <input v-model="profileForm.name" type="text" class="w-full rounded-lg border px-3 py-2 agent-input" required />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.email') }}</label>
                    <input v-model="profileForm.email" type="email" class="w-full rounded-lg border px-3 py-2 agent-input" required />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.language') }}</label>
                    <select v-model="profileForm.locale" class="w-full rounded-lg border px-3 py-2 text-sm agent-input" required>
                        <option v-for="option in localeOptions" :key="option.code" :value="option.code">
                            {{ option.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs agent-text-subtle">{{ t('profile.language_hint') }}</p>
                    <p v-if="profileForm.errors.locale" class="mt-1 text-sm text-red-600">{{ profileForm.errors.locale }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.timezone') }}</label>
                    <select v-model="profileForm.timezone" class="w-full rounded-lg border px-3 py-2 text-sm agent-input">
                        <option value="">{{ helpdeskTimezone }} — {{ t('profile.workspace_default') }}</option>
                        <optgroup v-for="group in timezoneOptions" :key="group.region" :label="group.region">
                            <option v-for="tz in group.options" :key="tz.value" :value="tz.value">
                                {{ tz.label }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="mt-1 text-xs agent-text-subtle">{{ timezoneHint }}</p>
                    <p v-if="profileForm.errors.timezone" class="mt-1 text-sm text-red-600">{{ profileForm.errors.timezone }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.appearance') }}</label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in appearanceOptions"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition"
                            :class="profileForm.appearance === option.value
                                ? 'border-blue-500 bg-blue-50 text-blue-900 dark:border-blue-400 dark:bg-blue-950/40 dark:text-blue-100'
                                : 'agent-border agent-panel agent-text-muted agent-hover-surface'"
                        >
                            <input
                                v-model="profileForm.appearance"
                                type="radio"
                                class="sr-only"
                                name="appearance"
                                :value="option.value"
                            />
                            <span class="font-medium">{{ t(`profile.appearance_${option.value}`) }}</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs agent-text-subtle">{{ t('profile.appearance_hint') }}</p>
                    <p v-if="profileForm.errors.appearance" class="mt-1 text-sm text-red-600">{{ profileForm.errors.appearance }}</p>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="profileForm.processing">
                    {{ t('profile.save_profile') }}
                </button>
            </form>
        </div>

        <div v-show="activeSection === 'password'" class="max-w-2xl agent-card">
            <form class="space-y-4" @submit.prevent="updatePassword">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.current_password') }}</label>
                    <input v-model="passwordForm.current_password" type="password" class="w-full rounded-lg border agent-border px-3 py-2" required />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.new_password') }}</label>
                    <input v-model="passwordForm.password" type="password" class="w-full rounded-lg border agent-border px-3 py-2" required />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.confirm_password') }}</label>
                    <input v-model="passwordForm.password_confirmation" type="password" class="w-full rounded-lg border agent-border px-3 py-2" required />
                </div>
                <button type="submit" class="agent-btn-secondary" :disabled="passwordForm.processing">
                    {{ t('profile.update_password') }}
                </button>
            </form>
        </div>

        <div v-show="activeSection === 'security'" class="max-w-2xl agent-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold agent-text">{{ t('profile.security_title') }}</h2>
                    <p class="mt-1 text-sm agent-text-muted">
                        {{ twoFactor.enabled ? t('profile.two_factor_enabled') : t('profile.two_factor_disabled') }}
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="twoFactor.enabled ? 'bg-emerald-100 text-emerald-800 dark:text-emerald-200' : 'bg-slate-100 dark:bg-slate-900 agent-text-muted'"
                >
                    {{ twoFactor.enabled ? t('common.active') : t('common.off') }}
                </span>
            </div>

            <div v-if="recoveryCodes?.length" class="mt-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 p-4">
                <p class="text-sm font-medium text-emerald-900">{{ t('profile.recovery_codes_title') }}</p>
                <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                    <li v-for="code in recoveryCodes" :key="code" class="font-mono text-sm text-emerald-800 dark:text-emerald-200">{{ code }}</li>
                </ul>
            </div>

            <div v-if="setup && !twoFactor.enabled" class="mt-4 rounded-lg border border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 p-4">
                <p class="text-sm text-blue-900">{{ t('profile.authenticator_secret') }}</p>
                <p class="mt-2 break-all font-mono text-sm text-blue-950">{{ setup.secret }}</p>
                <p class="mt-2 break-all text-xs text-blue-800">{{ setup.otpauth_url }}</p>
                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="confirmSetup">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.verification_code') }}</label>
                        <input v-model="confirmForm.code" type="text" class="rounded-lg border agent-border px-3 py-2 text-sm" required />
                        <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">{{ confirmForm.errors.code }}</p>
                    </div>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="confirmForm.processing">
                        {{ t('profile.confirm_enable') }}
                    </button>
                </form>
            </div>

            <div v-else-if="!twoFactor.enabled" class="mt-4">
                <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="beginSetup">
                    {{ t('profile.setup_authenticator') }}
                </button>
            </div>

            <div v-else class="mt-4">
                <button v-if="!showDisable" type="button" class="text-sm text-red-600 hover:text-red-700 dark:text-red-300" @click="showDisable = true">
                    {{ t('profile.disable_two_factor') }}
                </button>
                <form v-else class="mt-2 flex flex-wrap items-end gap-3" @submit.prevent="disableTwoFactor">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('profile.confirm_password_disable') }}</label>
                        <input v-model="disableForm.password" type="password" class="rounded-lg border agent-border px-3 py-2 text-sm" required />
                        <p v-if="disableForm.errors.password" class="mt-1 text-sm text-red-600">{{ disableForm.errors.password }}</p>
                    </div>
                    <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:bg-red-950/40" :disabled="disableForm.processing">
                        {{ t('common.disable') }}
                    </button>
                </form>
            </div>
        </div>
    </SettingsPage>
</template>
