<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const expanded = ref(true);

const warnings = computed(() => page.props.setupWarnings ?? []);

const showBanner = computed(() => {
    if (! warnings.value.length) {
        return false;
    }

    const path = page.url.split('?')[0];

    return path !== '/setup';
});

const countLabel = computed(() => {
    const count = warnings.value.length;

    return `${count} ${count === 1 ? 'item needs' : 'items need'} attention`;
});

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
    expanded.value = localStorage.getItem('setup-warnings-collapsed') !== '1';
});

const toggleExpanded = () => {
    expanded.value = ! expanded.value;
    localStorage.setItem('setup-warnings-collapsed', expanded.value ? '0' : '1');
};
</script>

<template>
    <div
        v-if="showBanner"
        class="mb-5 overflow-hidden rounded-xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-orange-50/40 shadow-sm"
        role="alert"
    >
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="text-sm font-semibold text-amber-950">Workspace setup incomplete</p>
                    <span class="rounded-full bg-amber-200/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-900">
                        {{ countLabel }}
                    </span>
                </div>
                <p v-if="!expanded" class="mt-0.5 truncate text-xs text-amber-800/90">
                    {{ warnings.map((warning) => warning.title).join(' · ') }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <Link
                    href="/setup"
                    class="hidden rounded-lg px-2.5 py-1.5 text-xs font-medium text-amber-900 transition hover:bg-amber-100/80 sm:inline-flex"
                >
                    Setup guide
                </Link>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-amber-800 transition hover:bg-amber-100/80"
                    :aria-expanded="expanded"
                    @click="toggleExpanded"
                >
                    <svg
                        class="h-4 w-4 transition-transform duration-200"
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
            <div v-if="expanded" class="border-t border-amber-200/60 px-4 pb-4 pt-3">
                <div class="grid gap-2 sm:grid-cols-2">
                    <Link
                        v-for="warning in warnings"
                        :key="warning.key"
                        :href="warning.url"
                        class="group flex items-start gap-3 rounded-lg border border-white/80 bg-white/70 p-3.5 transition hover:border-amber-200 hover:bg-white hover:shadow-sm"
                    >
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-700 ring-1 ring-amber-100 transition group-hover:bg-amber-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="iconPath(warning.key)" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ warning.title }}</p>
                            <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-600">{{ warning.message }}</p>
                        </div>
                        <svg
                            class="mt-0.5 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-amber-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <div class="mt-3 flex items-center justify-between sm:hidden">
                    <Link href="/setup" class="text-xs font-medium text-amber-900 underline decoration-amber-400 underline-offset-2">
                        Open setup guide
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
