<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentLayout from './AgentLayout.vue';
import SettingsSidebar from '../Components/SettingsSidebar.vue';
import { useAgentNavigation } from '../composables/useAgentNavigation.js';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    headTitle: { type: String, default: '' },
});

const page = usePage();
const { settingsNavGroups } = useAgentNavigation();
const currentUrl = computed(() => page.url.split('#')[0]);

const navigateMobile = (event) => {
    router.visit(event.target.value);
};
</script>

<template>
    <Head :title="headTitle || title" />
    <AgentLayout>
        <nav class="mx-auto mb-6 max-w-7xl lg:hidden" aria-label="Settings navigation">
            <select
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm"
                :value="currentUrl"
                @change="navigateMobile"
            >
                <optgroup v-for="group in settingsNavGroups" :key="group.id" :label="group.label">
                    <option
                        v-for="item in group.items"
                        :key="item.href"
                        :value="item.href"
                    >
                        {{ item.label }}
                    </option>
                </optgroup>
            </select>
        </nav>

        <div class="mx-auto flex max-w-7xl gap-8">
            <SettingsSidebar />

            <div class="min-w-0 flex-1">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
                        <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
                    </div>
                    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>

                <slot />
            </div>
        </div>
    </AgentLayout>
</template>
