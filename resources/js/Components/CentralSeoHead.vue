<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    page: { type: String, required: true },
    brand: { type: String, required: true },
    trialDays: { type: Number, default: 14 },
    seo: {
        type: Object,
        default: () => ({ siteUrl: '', siteName: '', ogImage: null }),
    },
});

const { t, locale } = useI18n();

const paths = {
    home: '/',
    register: '/register',
    login: '/login',
};

const baseUrl = computed(() => {
    const configured = props.seo?.siteUrl?.replace(/\/$/, '');

    if (configured) {
        return configured;
    }

    return window.location.origin.replace(/\/$/, '');
});

const canonical = computed(() => `${baseUrl.value}${paths[props.page] ?? '/'}`);

const title = computed(() => t(`central.seo.${props.page}_title`, {
    brand: props.brand,
    days: props.trialDays,
}));

const description = computed(() => t(`central.seo.${props.page}_description`, {
    brand: props.brand,
    days: props.trialDays,
}));

const robots = computed(() => (props.page === 'login' ? 'noindex, follow' : 'index, follow'));

const ogLocale = computed(() => locale.value.replace('-', '_'));

const ogImage = computed(() => props.seo?.ogImage || null);

const jsonLd = computed(() => {
    if (props.page !== 'home') {
        return null;
    }

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'Organization',
                '@id': `${baseUrl.value}/#organization`,
                name: props.brand,
                url: baseUrl.value,
            },
            {
                '@type': 'WebSite',
                '@id': `${baseUrl.value}/#website`,
                name: props.brand,
                url: baseUrl.value,
                publisher: { '@id': `${baseUrl.value}/#organization` },
            },
            {
                '@type': 'SoftwareApplication',
                '@id': `${baseUrl.value}/#software`,
                name: props.brand,
                applicationCategory: 'BusinessApplication',
                operatingSystem: 'Web',
                url: baseUrl.value,
                description: description.value,
                offers: {
                    '@type': 'Offer',
                    price: '0',
                    priceCurrency: 'USD',
                    description: t('central.seo.trial_offer', { days: props.trialDays }),
                },
            },
        ],
    });
});
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta head-key="description" name="description" :content="description" />
        <meta head-key="robots" name="robots" :content="robots" />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:site_name" property="og:site_name" :content="brand" />
        <meta head-key="og:locale" property="og:locale" :content="ogLocale" />
        <meta v-if="ogImage" head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="twitter:card" name="twitter:card" :content="ogImage ? 'summary_large_image' : 'summary'" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta head-key="twitter:description" name="twitter:description" :content="description" />
        <meta v-if="ogImage" head-key="twitter:image" name="twitter:image" :content="ogImage" />
        <component
            v-if="jsonLd"
            :is="'script'"
            head-key="json-ld"
            type="application/ld+json"
            v-html="jsonLd"
        />
    </Head>
</template>
