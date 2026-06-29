import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const titleCase = (value) => String(value || '')
    .split('-')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const safeString = (value) => (value === null || value === undefined ? '' : String(value)).trim();

const firstNonEmpty = (...values) => values.map(safeString).find((v) => v !== '') ?? '';

const labelFromDefinition = (definitions, slug) => {
    if (!Array.isArray(definitions)) return '';
    const match = definitions.find((entry) => entry && typeof entry === 'object' && entry.slug === slug);
    return match ? firstNonEmpty(match.nav_label, match.footer_label, match.source_name, match.competitor_name, match.name, match.slug) : '';
};

const truncate = (value, max = 56) => {
    const raw = safeString(value);
    if (raw.length <= max) return raw;
    return `${raw.slice(0, max - 1)}…`;
};

export function useCentralBreadcrumbs() {
    const page = usePage();

    const crumbs = computed(() => {
        const url = safeString(page.url).split('?')[0].split('#')[0];
        const props = page.props ?? {};

        const brand = firstNonEmpty(props.brand, props.siteName, props.seo?.brand, 'Home');
        const base = [{ label: brand, href: '/' }];

        if (url === '/' || url === '') {
            return [{ label: brand }];
        }

        if (url === '/features') {
            const label = firstNonEmpty(props.featuresHub?.nav_label, props.marketingChrome?.features, 'Features');
            return [...base, { label }];
        }

        if (url.startsWith('/features/')) {
            const slug = url.replace('/features/', '');
            const current = firstNonEmpty(props.content?.badge, labelFromDefinition(props.featurePages, slug), titleCase(slug));
            return [
                ...base,
                { label: firstNonEmpty(props.marketingChrome?.features, 'Features'), href: '/features' },
                { label: truncate(current) },
            ];
        }

        const featureMatch = labelFromDefinition(props.featurePages, url.replace(/^\//, ''));
        if (featureMatch !== '') {
            const slug = url.replace(/^\//, '');
            const current = firstNonEmpty(props.content?.badge, featureMatch, titleCase(slug));
            return [
                ...base,
                { label: firstNonEmpty(props.marketingChrome?.features, 'Features'), href: '/features' },
                { label: truncate(current) },
            ];
        }

        if (url.startsWith('/integrations/')) {
            const slug = url.replace('/integrations/', '');
            const current = firstNonEmpty(props.content?.badge, labelFromDefinition(props.integrationPages, slug), titleCase(slug));
            return [
                ...base,
                { label: firstNonEmpty(labelFromDefinition(props.staticPages, 'integrations'), 'Integrations'), href: '/integrations' },
                { label: truncate(current) },
            ];
        }

        if (url === '/blog') {
            const label = firstNonEmpty(props.marketingChrome?.blog?.index_nav_label, 'Blog');
            return [...base, { label }];
        }

        if (url.startsWith('/blog/')) {
            const current = firstNonEmpty(props.post?.title, titleCase(url.replace('/blog/', '')));
            const blogLabel = firstNonEmpty(props.marketingChrome?.blog?.index_nav_label, 'Blog');
            return [...base, { label: blogLabel, href: '/blog' }, { label: truncate(current) }];
        }

        if (url === '/industries') {
            const label = firstNonEmpty(props.content?.nav_label, labelFromDefinition(props.staticPages, 'industries'), 'Industries');
            return [...base, { label }];
        }

        if (url.startsWith('/helpdesk-for-')) {
            const slug = url.replace('/helpdesk-for-', '');
            const current = firstNonEmpty(props.content?.badge, labelFromDefinition(props.verticalPages, slug), titleCase(slug));
            return [...base, { label: firstNonEmpty(labelFromDefinition(props.staticPages, 'industries'), 'Industries'), href: '/industries' }, { label: truncate(current) }];
        }

        if (url === '/integrations') {
            const label = firstNonEmpty(props.integrationsHub?.nav_label, labelFromDefinition(props.staticPages, 'integrations'), 'Integrations');
            return [...base, { label }];
        }

        if (url === '/resources') {
            const label = firstNonEmpty(props.content?.nav_label, labelFromDefinition(props.staticPages, 'resources'), 'Resources');
            return [...base, { label }];
        }

        if (url === '/pricing') {
            const label = firstNonEmpty(props.content?.nav_label, labelFromDefinition(props.staticPages, 'pricing'), 'Pricing');
            return [...base, { label }];
        }

        if (url === '/compare') {
            const label = firstNonEmpty(props.compareHub?.nav_label, props.marketingChrome?.compare_nav, 'Compare');
            return [...base, { label }];
        }

        if (url.startsWith('/compare/')) {
            const competitor = firstNonEmpty(props.competitor?.name, props.content?.competitor_name, titleCase(url.replace('/compare/', '').replace(/-vs-helpefi$/, '')));
            const compareLabel = firstNonEmpty(props.compareHub?.nav_label, props.marketingChrome?.compare_nav, 'Compare');
            return [
                ...base,
                { label: compareLabel, href: '/compare' },
                { label: truncate(competitor) },
            ];
        }

        if (url === '/migrate') {
            const label = firstNonEmpty(props.migrateHub?.nav_label, props.marketingChrome?.migrate_nav, 'Migrate');
            return [...base, { label }];
        }

        if (url.startsWith('/migrate/from-')) {
            const source = firstNonEmpty(props.content?.badge, props.sourceSlug, titleCase(url.replace('/migrate/from-', '')));
            const migrateLabel = firstNonEmpty(props.migrateHub?.nav_label, props.marketingChrome?.migrate_nav, 'Migrate');
            return [
                ...base,
                { label: migrateLabel, href: '/migrate' },
                { label: truncate(source) },
            ];
        }

        if (url.startsWith('/vs/')) {
            const competitor = firstNonEmpty(props.competitor?.name, props.competitorSlug, titleCase(url.replace('/vs/', '')));
            const compareLabel = firstNonEmpty(props.compareHub?.nav_label, props.marketingChrome?.compare_nav, 'Compare');
            return [
                ...base,
                { label: compareLabel, href: '/compare' },
                { label: truncate(competitor) },
            ];
        }

        const staticLabel = firstNonEmpty(props.content?.nav_label, '');
        if (staticLabel !== '') {
            return [...base, { label: staticLabel }];
        }

        const fallback = url.split('/').filter(Boolean).map(titleCase).join(' / ');
        return [...base, { label: fallback || brand }];
    });

    return { crumbs };
}

