<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const expandedIds = ref(new Set());
const { t } = useI18n();

const notices = computed(() => page.props.platformNotices ?? []);

const styles = {
    maintenance: {
        shell: 'border-amber-200/80 bg-amber-50',
        title: 'text-amber-950',
        body: 'text-amber-900',
        badge: 'bg-amber-200/60 text-amber-900',
        button: 'text-amber-900 hover:bg-amber-100/80',
    },
    offer: {
        shell: 'border-emerald-200/80 bg-emerald-50',
        title: 'text-emerald-950',
        body: 'text-emerald-900',
        badge: 'bg-emerald-200/60 text-emerald-900',
        button: 'text-emerald-900 hover:bg-emerald-100/80',
    },
    announcement: {
        shell: 'border-blue-200/80 bg-blue-50',
        title: 'text-blue-950',
        body: 'text-blue-900',
        badge: 'bg-blue-200/60 text-blue-900',
        button: 'text-blue-900 hover:bg-blue-100/80',
    },
    general: {
        shell: 'border-slate-200/80 bg-slate-50',
        title: 'text-slate-950',
        body: 'text-slate-800',
        badge: 'bg-slate-200/60 text-slate-800',
        button: 'text-slate-800 hover:bg-slate-100/80',
    },
};

const typeLabelKeys = {
    maintenance: 'notice_maintenance',
    offer: 'notice_offer',
    announcement: 'notice_announcement',
    general: 'notice_general',
};

const typeLabel = (noticeType) => t(`components.${typeLabelKeys[noticeType] ?? 'notice_general'}`);

const styleFor = (notice) => styles[notice.notice_type] ?? styles.general;

const isExpanded = (id) => expandedIds.value.has(id);

const toggleExpanded = (id) => {
    const next = new Set(expandedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expandedIds.value = next;
};

const dismiss = (notice) => {
    router.post(`/platform-notices/${notice.id}/dismiss`, {}, { preserveScroll: true });
};
</script>

<template>
    <div v-if="notices.length" class="mb-2 space-y-2">
        <div
            v-for="notice in notices"
            :key="notice.id"
            class="overflow-hidden rounded-lg border"
            :class="styleFor(notice).shell"
            role="alert"
        >
            <div class="flex items-start gap-3 px-3 py-2.5">
                <img
                    v-if="notice.image_url && isExpanded(notice.id)"
                    :src="notice.image_url"
                    :alt="notice.title"
                    class="mt-0.5 max-h-24 shrink-0 rounded-lg border border-white/60 object-contain"
                />

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold" :class="styleFor(notice).title">{{ notice.title }}</span>
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="styleFor(notice).badge">
                            {{ typeLabel(notice.notice_type) }}
                        </span>
                        <span
                            v-if="notice.priority === 'high'"
                            class="rounded bg-red-200/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-900"
                        >
                            {{ $t('components.important') }}
                        </span>
                    </div>

                    <p v-if="!isExpanded(notice.id)" class="mt-0.5 truncate text-xs" :class="styleFor(notice).body">
                        {{ notice.body_html ? notice.body_html.replace(/<[^>]+>/g, ' ').trim() : $t('components.platform_notice_fallback') }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-0.5">
                    <button
                        v-if="notice.body_html || notice.image_url"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded"
                        :class="styleFor(notice).button"
                        :aria-expanded="isExpanded(notice.id)"
                        @click="toggleExpanded(notice.id)"
                    >
                        <svg
                            class="h-3.5 w-3.5 transition-transform duration-200"
                            :class="isExpanded(notice.id) ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <button
                        v-if="notice.dismissible"
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium"
                        :class="styleFor(notice).button"
                        @click="dismiss(notice)"
                    >
                        {{ $t('components.dismiss') }}
                    </button>
                </div>
            </div>

            <div v-if="isExpanded(notice.id) && notice.body_html" class="border-t border-black/5 px-3 pb-3 pt-2">
                <div class="prose prose-sm max-w-none" :class="styleFor(notice).body" v-html="notice.body_html" />
            </div>
        </div>
    </div>
</template>
