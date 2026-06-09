<script setup>
import { Link } from '@inertiajs/vue3';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import { useAgentNavigation } from '../../composables/useAgentNavigation.js';

const { settingsNavGroups } = useAgentNavigation();
</script>

<template>
    <SettingsLayout
        title="Settings"
        description="Configure your helpdesk — team, channels, workflows, and account."
    >
        <div class="space-y-8">
            <section v-for="group in settingsNavGroups" :key="group.id">
                <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">{{ group.label }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
                    >
                        <span class="block text-sm font-medium text-slate-900 group-hover:text-blue-700">{{ item.label }}</span>
                        <span v-if="item.description" class="mt-1 block text-sm text-slate-500">{{ item.description }}</span>
                    </Link>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
