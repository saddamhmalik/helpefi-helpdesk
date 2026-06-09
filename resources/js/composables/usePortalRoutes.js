import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePortalRoutes() {
    const page = usePage();
    const brand = computed(() => page.props.portalBrand || { slug: 'default', portal_title: 'Help Center' });
    const slug = computed(() => brand.value.slug || 'default');

    const portalPath = (path = '') => `/portal/${slug.value}${path}`;
    const portalApiPath = (path = '') => `/api/v1/portal/${slug.value}${path}`;

    return { brand, slug, portalPath, portalApiPath };
}
