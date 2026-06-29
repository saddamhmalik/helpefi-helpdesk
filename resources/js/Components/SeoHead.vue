<script>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, h } from 'vue';
import { useSeo } from '../composables/useSeo.js';

export default {
    name: 'SeoHead',
    props: {
        seo: {
            type: Object,
            default: () => ({}),
        },
    },
    setup(props) {
        const page = usePage();

        const mergedSeo = computed(() => {
            const fromPage = page.props.seo ?? page.props.pageSeo ?? {};
            const fromSchemaKey = page.props.seo_schema ? { schema: page.props.seo_schema } : {};

            return {
                ...fromPage,
                ...fromSchemaKey,
                ...props.seo,
            };
        });

        const resolved = useSeo(mergedSeo.value);

        const schemaJson = computed(() => {
            const value = resolved.schema.value;
            if (!value) return '';
            return JSON.stringify(value, null, 0);
        });

        const preloads = computed(() => {
            const items = mergedSeo.value?.preloads ?? [];
            if (!Array.isArray(items)) return [];
            return items
                .filter((item) => item && typeof item === 'object' && item.href)
                .map((item) => ({
                    href: String(item.href),
                    as: item.as ? String(item.as) : 'image',
                    type: item.type ? String(item.type) : undefined,
                    imagesrcset: item.imagesrcset ? String(item.imagesrcset) : undefined,
                    imagesizes: item.imagesizes ? String(item.imagesizes) : undefined,
                    fetchpriority: item.fetchpriority ? String(item.fetchpriority) : undefined,
                    crossorigin: item.crossorigin ? String(item.crossorigin) : undefined,
                }));
        });

        return () => h(
            Head,
            { title: resolved.titleBase.value },
            () => [
                ...preloads.value.map((item, index) => h('link', {
                    'head-key': `preload-${index}`,
                    rel: 'preload',
                    href: item.href,
                    as: item.as,
                    type: item.type,
                    imagesrcset: item.imagesrcset,
                    imagesizes: item.imagesizes,
                    fetchpriority: item.fetchpriority,
                    crossorigin: item.crossorigin,
                })),
                h('meta', { 'head-key': 'description', name: 'description', content: resolved.description.value }),
                h('meta', { 'head-key': 'robots', name: 'robots', content: resolved.robots.value }),
                ...(resolved.keywords.value ? [h('meta', { 'head-key': 'keywords', name: 'keywords', content: resolved.keywords.value })] : []),
                h('link', { 'head-key': 'canonical', rel: 'canonical', href: resolved.canonical.value }),

                h('meta', { 'head-key': 'og:type', property: 'og:type', content: resolved.ogType.value }),
                h('meta', { 'head-key': 'og:url', property: 'og:url', content: resolved.canonical.value }),
                h('meta', { 'head-key': 'og:title', property: 'og:title', content: resolved.title.value }),
                h('meta', { 'head-key': 'og:description', property: 'og:description', content: resolved.ogDescription.value }),
                ...(resolved.image.value ? [h('meta', { 'head-key': 'og:image', property: 'og:image', content: resolved.image.value })] : []),

                h('meta', { 'head-key': 'twitter:card', name: 'twitter:card', content: resolved.twitterCard.value }),
                h('meta', { 'head-key': 'twitter:title', name: 'twitter:title', content: resolved.title.value }),
                h('meta', { 'head-key': 'twitter:description', name: 'twitter:description', content: resolved.twitterDescription.value }),
                ...(resolved.image.value ? [h('meta', { 'head-key': 'twitter:image', name: 'twitter:image', content: resolved.image.value })] : []),

                ...(schemaJson.value ? [h('script', { 'head-key': 'jsonld', type: 'application/ld+json', innerHTML: schemaJson.value })] : []),
            ],
        );
    },
};
</script>

