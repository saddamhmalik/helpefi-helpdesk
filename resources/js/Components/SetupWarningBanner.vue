<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const expanded = ref(false);
const { t } = useI18n();

const warnings = computed(() => page.props.setupWarnings ?? []);

const showBanner = computed(() => {
    if (! warnings.value.length) {
        return false;
    }

    const path = page.url.split('?')[0];

    return path !== '/setup';
});

const countLabel = computed(() => t('components.setup_item_count', { count: warnings.value.length }));

const summary = computed(() => warnings.value.map((warning) => warning.title).join(' · '));

const icons = {
    business_hours: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    email_inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    email_outbound: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    chat_widget: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    invite_team: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    sla_policies: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
};

const iconPath = (key) => icons[key] ?? icons.sla_policies;

onMounted(() => {
    expanded.value = localStorage.getItem('setup-warnings-expanded') === '1';
});

const toggleExpanded = () => {
    expanded.value = ! expanded.value;
    localStorage.setItem('setup-warnings-expanded', expanded.value ? '1' : '0');
};
</script>

<template>
    <div
        v-if="showBanner"
        class="mb-3 overflow-hidden rounded-lg border border-amber-200/80 bg-amber-50"
        role="alert"
    >
        <div class="flex items-center gap-2 px-3 py-2" :title="summary">
            <svg class="h-4 w-4 shrink-0 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>

            <span class="shrink-0 text-sm font-semibold text-amber-950">{{ $t('components.setup_incomplete') }}</span>
            <span class="shrink-0 rounded bg-amber-200/60 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900">
                {{ countLabel }}
            </span>
            <span class="min-w-0 flex-1 truncate text-xs text-amber-800">{{ summary }}</span>

            <div class="flex shrink-0 items-center gap-0.5">
                <Link
                    href="/setup"
                    class="rounded px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100/80"
                >
                    {{ $t('components.setup_guide') }}
                </Link>
                <button
                    type="button"
                    class="inline-flex h-7 w-7 items-center justify-center rounded text-amber-800 hover:bg-amber-100/80"
                    :aria-expanded="expanded"
                    @click="toggleExpanded"
                >
                    <svg
                        class="h-3.5 w-3.5 transition-transform duration-200"
                        :class="expanded ? 'rotate-180' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="expanded" class="border-t border-amber-200/60 px-3 pb-3 pt-2">
                <div class="grid gap-1.5 sm:grid-cols-2">
                    <Link
                        v-for="warning in warnings"
                        :key="warning.key"
                        :href="warning.url"
                        class="group flex items-center gap-2 rounded-md border border-amber-100 bg-white/80 px-2.5 py-2 transition hover:border-amber-200 hover:bg-white"
                    >
                        <svg class="h-3.5 w-3.5 shrink-0 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="iconPath(warning.key)" />
                        </svg>
                        <span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-900">{{ warning.title }}</span>
                        <svg
                            class="h-3 w-3 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-amber-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
