<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsLayout from '../../Layouts/SettingsLayout.vue';
import { useAgentNavigation } from '../../composables/useAgentNavigation.js';

const { settingsNavGroups, flatSettingsNavItems } = useAgentNavigation();
const query = ref('');

const filteredGroups = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return settingsNavGroups.value;
    }

    return settingsNavGroups.value
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => (
                item.label.toLowerCase().includes(term)
                || item.description?.toLowerCase().includes(term)
                || group.label.toLowerCase().includes(term)
            )),
        }))
        .filter((group) => group.items.length > 0);
});

const resultCount = computed(() => filteredGroups.value.reduce((count, group) => count + group.items.length, 0));
</script>

<template>
    <SettingsLayout
        title="Settings"
        description="Find workspace, team, channel, and security settings in one place."
    >
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <label class="block text-sm font-medium text-slate-700" for="settings-search">Search settings</label>
            <input
                id="settings-search"
                v-model="query"
                type="search"
                placeholder="Search by name, e.g. domain, billing, SLA..."
                class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
            <p v-if="query" class="mt-2 text-xs text-slate-500">
                {{ resultCount }} result{{ resultCount === 1 ? '' : 's' }} for “{{ query }}”
            </p>
        </div>

        <div v-if="filteredGroups.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
            No settings matched your search.
        </div>

        <div v-else class="space-y-8">
            <section v-for="group in filteredGroups" :key="group.id">
                <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">{{ group.label }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-slate-900 group-hover:text-blue-700">{{ item.label }}</span>
                            <span
                                v-if="item.locked"
                                class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700"
                            >
                                {{ item.lockedLabel }}
                            </span>
                        </div>
                        <span v-if="item.description" class="mt-1 block text-sm leading-snug text-slate-500">{{ item.description }}</span>
                    </Link>
                </div>
            </section>
        </div>

        <p class="mt-8 text-xs text-slate-400">
            {{ flatSettingsNavItems.length }} settings available in your workspace.
        </p>
    </SettingsLayout>
</template>
