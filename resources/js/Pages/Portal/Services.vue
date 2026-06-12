<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';
import { usePortalRoutes } from '../../composables/usePortalRoutes.js';
import { useI18n } from 'vue-i18n';

defineProps({
    categories: Array,
});

const { t } = useI18n();
const { portalPath } = usePortalRoutes();
</script>

<template>
    <Head :title="$t('portal.service_catalog')" />
    <PortalLayout>
        <div>
            <Link :href="portalPath()" class="text-sm text-blue-600 hover:text-blue-700 dark:hover:text-blue-300 dark:text-blue-300">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $t('portal.service_catalog') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t('portal.choose_a_service_to_submit_a_structured_request') }}</p>

            <div class="mt-8 space-y-8">
                <section v-for="category in categories" :key="category.id">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ category.name }}</h2>
                    <p v-if="category.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ category.description }}</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <Link
                            v-for="item in category.items"
                            :key="item.id"
                            :href="portalPath(`/services/${item.slug}`)"
                            class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm transition hover:border-blue-300"
                        >
                            <h3 class="font-medium text-slate-900 dark:text-slate-100">{{ item.name }}</h3>
                            <p v-if="item.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ item.description }}</p>
                        </Link>
                    </div>
                </section>
                <p v-if="!categories?.length" class="text-sm text-slate-500 dark:text-slate-400">{{ $t('portal.no_services_are_available_right_now') }}</p>
            </div>
        </div>
    </PortalLayout>
</template>
