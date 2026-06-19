<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AgentLayout from './AgentLayout.vue';
import SettingsSidebar from '../Components/SettingsSidebar.vue';
import SettingsMobileNav from '../Components/SettingsMobileNav.vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';
import { settingsLayoutMeta } from '../composables/useSettingsLayoutMeta.js';

const { t } = useI18n();

const page = usePage();
const { settingsBreadcrumbForUrl } = useAgentNavigation();
const currentUrl = computed(() => page.url.split('#')[0]);

const breadcrumb = computed(() => settingsBreadcrumbForUrl(currentUrl.value));

const title = computed(() => settingsLayoutMeta.title);
const description = computed(() => settingsLayoutMeta.description);
const headTitle = computed(() => settingsLayoutMeta.headTitle || settingsLayoutMeta.title);

const contentRef = ref(null);

watch(currentUrl, () => {
    nextTick(() => {
        contentRef.value?.scrollTo({ top: 0 });
    });
});
</script>

<template>
    <Head :title="headTitle" />
    <AgentLayout>
        <div class="flex min-h-0 flex-1 flex-col lg:overflow-hidden">
            <div class="grid min-h-0 w-full flex-1 grid-cols-1 items-stretch overflow-hidden lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-x-6">
                <SettingsSidebar />

                <div ref="contentRef" class="min-h-0 min-w-0 overflow-y-auto overscroll-contain px-4 pb-8 sm:px-6 scrollbar-gutter-stable">
                    <SettingsMobileNav :breadcrumb="breadcrumb" />

                    <nav v-if="breadcrumb" class="mb-3 hidden flex-wrap items-center gap-1.5 text-sm agent-text-subtle lg:flex" aria-label="Breadcrumb">
                        <Link href="/settings" class="transition hover:text-slate-800 dark:text-slate-200 dark:hover:text-slate-200">{{ t('common.settings') }}</Link>
                        <span aria-hidden="true">/</span>
                        <span>{{ breadcrumb.group }}</span>
                        <span aria-hidden="true">/</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ breadcrumb.page }}</span>
                    </nav>

                    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight agent-text">{{ title }}</h1>
                            <p v-if="description" class="mt-1 text-sm agent-text-subtle" dir="auto">{{ description }}</p>
                        </div>
                        <div id="settings-page-actions" class="flex flex-wrap items-center gap-2" />
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
