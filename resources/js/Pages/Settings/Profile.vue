<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import { useSettingsSection } from '../../composables/useSettingsSection.js';

const props = defineProps({
    twoFactor: Object,
    mfaRequired: Boolean,
});

const { activeSection } = useSettingsSection({
    defaultSection: 'profile',
    sections: ['profile', 'password', 'security'],
});

const pageTitles = {
    profile: { title: 'Profile', description: 'Update your name and email address.' },
    password: { title: 'Password', description: 'Change the password you use to sign in.' },
    security: { title: 'Two-factor authentication', description: 'Add an extra layer of security with an authenticator app.' },
};

const page = usePage();
const user = computed(() => page.props.auth.user);
const setup = computed(() => page.props.flash?.two_factor_setup);
const recoveryCodes = computed(() => page.props.flash?.recovery_codes);

const profileForm = useForm({
    name: user.value.name,
    email: user.value.email,
});

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

const updateProfile = () => profileForm.put('/settings/profile');
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
    <SettingsLayout
        :title="pageTitles[activeSection]?.title ?? 'Profile'"
        :description="pageTitles[activeSection]?.description ?? ''"
    >
        <div
            v-if="mfaRequired && !twoFactor.enabled"
            class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Two-factor authentication is required for your account. Enable it below to continue using the helpdesk.
        </div>

        <div v-show="activeSection === 'profile'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form class="space-y-4" @submit.prevent="updateProfile">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                            <input v-model="profileForm.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                            <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                            <input v-model="profileForm.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                            <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="profileForm.processing">
                            Save profile
                        </button>
                    </form>
                </div>

        <div v-show="activeSection === 'password'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form class="space-y-4" @submit.prevent="updatePassword">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Current password</label>
                            <input v-model="passwordForm.current_password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">New password</label>
                            <input v-model="passwordForm.password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                            <input v-model="passwordForm.password_confirmation" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required />
                        </div>
                        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="passwordForm.processing">
                            Update password
                        </button>
                    </form>
                </div>

        <div v-show="activeSection === 'security'" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Two-factor authentication</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ twoFactor.enabled ? 'Enabled on your account.' : 'Add an extra layer of security with an authenticator app.' }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="twoFactor.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ twoFactor.enabled ? 'Active' : 'Off' }}
                        </span>
                    </div>

                    <div v-if="recoveryCodes?.length" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm font-medium text-emerald-900">Save these recovery codes in a safe place:</p>
                        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                            <li v-for="code in recoveryCodes" :key="code" class="font-mono text-sm text-emerald-800">{{ code }}</li>
                        </ul>
                    </div>

                    <div v-if="setup && !twoFactor.enabled" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm text-blue-900">Add this secret to your authenticator app:</p>
                        <p class="mt-2 break-all font-mono text-sm text-blue-950">{{ setup.secret }}</p>
                        <p class="mt-2 break-all text-xs text-blue-800">{{ setup.otpauth_url }}</p>
                        <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="confirmSetup">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Verification code</label>
                                <input v-model="confirmForm.code" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                                <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">{{ confirmForm.errors.code }}</p>
                            </div>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :disabled="confirmForm.processing">
                                Confirm and enable
                            </button>
                        </form>
                    </div>

                    <div v-else-if="!twoFactor.enabled" class="mt-4">
                        <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="beginSetup">
                            Set up authenticator
                        </button>
                    </div>

                    <div v-else class="mt-4">
                        <button v-if="!showDisable" type="button" class="text-sm text-red-600 hover:text-red-700" @click="showDisable = true">
                            Disable two-factor authentication
                        </button>
                        <form v-else class="mt-2 flex flex-wrap items-end gap-3" @submit.prevent="disableTwoFactor">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                                <input v-model="disableForm.password" type="password" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                                <p v-if="disableForm.errors.password" class="mt-1 text-sm text-red-600">{{ disableForm.errors.password }}</p>
                            </div>
                            <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" :disabled="disableForm.processing">
                                Disable
                            </button>
                        </form>
                    </div>
                </div>
    </SettingsLayout>
</template>
