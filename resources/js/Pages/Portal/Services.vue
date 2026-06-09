<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PortalLayout from '../../Layouts/PortalLayout.vue';

defineProps({
    categories: Array,
});
</script>

<template>
    <Head title="Service catalog" />
    <PortalLayout>
        <div>
            <Link href="/portal" class="text-sm text-blue-600 hover:text-blue-700">← Help Center</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Service catalog</h1>
            <p class="mt-1 text-sm text-slate-600">Choose a service to submit a structured request.</p>

            <div class="mt-8 space-y-8">
                <section v-for="category in categories" :key="category.id">
                    <h2 class="text-lg font-semibold text-slate-900">{{ category.name }}</h2>
                    <p v-if="category.description" class="mt-1 text-sm text-slate-600">{{ category.description }}</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <Link
                            v-for="item in category.items"
                            :key="item.id"
                            :href="`/portal/services/${item.slug}`"
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300"
                        >
                            <h3 class="font-medium text-slate-900">{{ item.name }}</h3>
                            <p v-if="item.description" class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                        </Link>
                    </div>
                </section>
                <p v-if="!categories?.length" class="text-sm text-slate-500">No services are available right now.</p>
            </div>
        </div>
    </PortalLayout>
</template>
