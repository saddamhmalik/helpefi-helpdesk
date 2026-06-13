<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    page: { type: String, required: true },
    brand: { type: String, required: true },
    trialDays: { type: Number, default: 14 },
});

const { t } = useI18n();

const title = computed(() => t(`central.seo.${props.page}_title`, {
    brand: props.brand,
    days: props.trialDays,
}));
</script>

<template>
    <Head>
        <title>{{ title }}</title>
    </Head>
</template>
