<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { adminInputClass } from '../../../../composables/usePlatformAdmin.js';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    notice: { type: Object, default: null },
    tenants: { type: Array, default: () => [] },
    types: { type: Object, default: () => ({}) },
    audiences: { type: Object, default: () => ({}) },
    priorities: { type: Object, default: () => ({}) },
    targetScopes: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const isEditing = computed(() => props.notice !== null);
const imagePreview = ref(props.notice?.image_url ?? null);
const tenantSearch = ref('');

const form = useForm({
    title: props.notice?.title ?? '',
    body_html: props.notice?.body_html ?? '',
    notice_type: props.notice?.notice_type ?? 'general',
    target_scope: props.notice?.target_scope ?? 'all',
    tenant_ids: props.notice?.tenant_ids ?? [],
    audience: props.notice?.audience ?? 'admins',
    starts_at: props.notice?.starts_at ? props.notice.starts_at.slice(0, 16) : '',
    ends_at: props.notice?.ends_at ? props.notice.ends_at.slice(0, 16) : '',
    dismissible: props.notice?.dismissible ?? true,
    priority: props.notice?.priority ?? 'normal',
    image: null,
    remove_image: false,
});

const filteredTenants = computed(() => {
    const term = tenantSearch.value.trim().toLowerCase();

    if (!term) {
        return props.tenants;
    }

    return props.tenants.filter((tenant) =>
        tenant.name.toLowerCase().includes(term) || tenant.slug.toLowerCase().includes(term),
    );
});

const selectedTenants = computed(() =>
    props.tenants.filter((tenant) => form.tenant_ids.includes(tenant.id)),
);

const toggleTenant = (tenantId) => {
    if (form.tenant_ids.includes(tenantId)) {
        form.tenant_ids = form.tenant_ids.filter((id) => id !== tenantId);
        return;
    }

    form.tenant_ids = [...form.tenant_ids, tenantId];
};

const onImageChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;

    if (file) {
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    form.image = null;
    form.remove_image = true;
    imagePreview.value = null;
};

const submit = () => {
    const options = form.image ? { forceFormData: true } : {};

    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/notices/${props.notice.id}`, options);
        return;
    }

    form.post('/admin/notices', options);
};

const typeStyles = {
    maintenance: 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 text-amber-900',
    offer: 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900',
    announcement: 'border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 text-blue-900',
    general: 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100',
};
</script>

<template>
    <Head :title="isEditing ? 'Edit notice' : 'New notice'" />
    <AdminLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-8">
                <Link href="/admin/notices" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">← Back to notices</Link>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ isEditing ? 'Edit notice' : 'New notice' }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('central.create_a_draft_then_publish_when_ready_published_notices_appear_as_ban') }}</p>
            </div>

            <form class="space-y-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('common.title') }}</label>
                    <input v-model="form.title" type="text" required :class="adminInputClass" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.type') }}</label>
                        <select v-model="form.notice_type" :class="adminInputClass">
                            <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.priority') }}</label>
                        <select v-model="form.priority" :class="adminInputClass">
                            <option v-for="(label, value) in priorities" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.audience') }}</label>
                        <select v-model="form.audience" :class="adminInputClass">
                            <option v-for="(label, value) in audiences" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.message_html_supported') }}</label>
                    <textarea v-model="form.body_html" rows="8" :class="adminInputClass" :placeholder="$t('central.describe_the_maintenance_window_offer_details_or_announcement')" />
                    <p v-if="form.errors.body_html" class="mt-1 text-xs text-red-600">{{ form.errors.body_html }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.image_optional') }}</label>
                    <div v-if="imagePreview" class="mb-3 flex items-start gap-3">
                        <img :src="imagePreview" alt="Preview" class="max-h-32 rounded-xl border border-slate-200 dark:border-slate-800 object-contain" />
                        <button type="button" class="text-sm text-red-600 hover:text-red-700 dark:text-red-300" @click="removeImage">{{ $t('central.remove_image') }}</button>
                    </div>
                    <input type="file" accept="image/*" class="block w-full text-sm text-slate-600 dark:text-slate-400" @change="onImageChange" />
                    <p v-if="form.errors.image" class="mt-1 text-xs text-red-600">{{ form.errors.image }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.start_optional') }}</label>
                        <input v-model="form.starts_at" type="datetime-local" :class="adminInputClass" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.end_optional') }}</label>
                        <input v-model="form.ends_at" type="datetime-local" :class="adminInputClass" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('central.target_workspaces') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="(label, value) in targetScopes"
                            :key="value"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm"
                            :class="form.target_scope === value ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40 text-blue-900' : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300'"
                        >
                            <input v-model="form.target_scope" type="radio" :value="value" class="text-blue-600" />
                            {{ label }}
                        </label>
                    </div>
                </div>

                <div v-if="form.target_scope === 'selected'" class="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Selected workspaces ({{ form.tenant_ids.length }})</p>
                        <input v-model="tenantSearch" type="search" :placeholder="$t('central.search_workspaces')" class="w-56 rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-1.5 text-sm" />
                    </div>

                    <div v-if="selectedTenants.length" class="mb-3 flex flex-wrap gap-2">
                        <span
                            v-for="tenant in selectedTenants"
                            :key="tenant.id"
                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800"
                        >
                            {{ tenant.name }}
                            <button type="button" class="text-blue-600 hover:text-blue-800" @click="toggleTenant(tenant.id)">×</button>
                        </span>
                    </div>

                    <div class="max-h-56 space-y-1 overflow-y-auto">
                        <label
                            v-for="tenant in filteredTenants"
                            :key="tenant.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50 dark:bg-slate-950 dark:hover:bg-slate-800"
                        >
                            <input
                                type="checkbox"
                                :checked="form.tenant_ids.includes(tenant.id)"
                                class="rounded border-slate-300 dark:border-slate-700"
                                @change="toggleTenant(tenant.id)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ tenant.name }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ tenant.slug }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.tenant_ids" class="mt-2 text-xs text-red-600">{{ form.errors.tenant_ids }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.dismissible" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    Allow recipients to dismiss this notice
                </label>

                <div class="rounded-xl border p-4" :class="typeStyles[form.notice_type] ?? typeStyles.general">
                    <p class="text-sm font-semibold">{{ $t('central.preview') }}</p>
                    <p class="mt-1 text-sm font-medium">{{ form.title || 'Notice title' }}</p>
                    <div v-if="form.body_html" class="prose prose-sm mt-2 max-w-none" v-html="form.body_html" />
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Save draft' }}
                    </button>
                    <Link href="/admin/notices" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 dark:text-slate-300">{{ $t('common.cancel') }}</Link>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
