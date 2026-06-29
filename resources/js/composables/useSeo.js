import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const trimTo = (value, max) => {
    const text = String(value ?? '').trim().replace(/\s+/g, ' ');
    if (!text) return '';
    return text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text;
};

const absoluteUrl = (url, origin) => {
    const raw = String(url ?? '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('//')) return `https:${raw}`;
    if (!origin) return raw;
    if (raw.startsWith('/')) return `${origin}${raw}`;
    return `${origin}/${raw}`;
};

const resolveOrigin = () => {
    try {
        return window.location.origin;
    } catch {
        return '';
    }
};

export function useSeo(input = {}) {
    const page = usePage();

    const origin = computed(() => resolveOrigin());
    const path = computed(() => String(page.url ?? '/'));

    const canonical = computed(() => {
        const fromInput = input.canonical ?? input.url ?? '';
        if (fromInput) {
            return absoluteUrl(fromInput, origin.value);
        }

        const url = new URL(path.value, origin.value || 'https://helpefi.com');
        url.hash = '';
        return url.toString();
    });

    const isHomepage = computed(() => {
        const key = path.value.split('?')[0];
        return key === '/' || key === '';
    });

    const hostname = computed(() => {
        try {
            return window.location.hostname;
        } catch {
            return '';
        }
    });

    const shouldNoIndex = computed(() => {
        const key = path.value.split('?')[0];
        const host = hostname.value;

        const isAppArea = key.startsWith('/tickets')
            || key.startsWith('/settings')
            || key.startsWith('/workspace')
            || key.startsWith('/admin')
            || key.startsWith('/portal')
            || key.startsWith('/auth');

        const isTenantHost = Boolean(host) && host.endsWith('.helpefi.com') && host !== 'helpefi.com' && host !== 'www.helpefi.com';

        return isAppArea || isTenantHost;
    });

    const brand = 'Helpefi';

    const titleBase = computed(() => {
        const raw = trimTo(input.title ?? '', 70);
        return raw || brand;
    });

    const title = computed(() => {
        const raw = titleBase.value;
        if (!raw) return brand;
        if (isHomepage.value) return raw;
        if (raw.toLowerCase().endsWith(`| ${brand.toLowerCase()}`)) return raw;
        if (raw.toLowerCase() === brand.toLowerCase()) return raw;
        return `${raw} | ${brand}`;
    });

    const description = computed(() => {
        const raw = input.description ?? '';
        if (raw) return trimTo(raw, 160);
        return isHomepage.value
            ? 'Helpefi is a secure, AI-native helpdesk & ITSM platform with true database isolation.'
            : 'Helpefi is a secure, AI-native helpdesk & ITSM platform with true database isolation.';
    });

    const ogDescription = computed(() => {
        const raw = input.ogDescription ?? input.og_description ?? '';
        return raw ? trimTo(raw, 200) : description.value;
    });

    const twitterDescription = computed(() => {
        const raw = input.twitterDescription ?? input.twitter_description ?? '';
        return raw ? trimTo(raw, 200) : description.value;
    });

    const robots = computed(() => String(input.robots ?? (shouldNoIndex.value ? 'noindex,nofollow' : 'index,follow')));
    const keywords = computed(() => trimTo(input.keywords ?? '', 180));
    const image = computed(() => absoluteUrl(input.image ?? '', origin.value));

    const ogType = computed(() => String(input.ogType ?? 'website'));
    const twitterCard = computed(() => (image.value ? 'summary_large_image' : 'summary'));

    const schema = computed(() => {
        const s = input.schema ?? null;
        if (!s) return null;
        if (Array.isArray(s)) return s.filter(Boolean);
        if (typeof s === 'object') return s;
        return null;
    });

    return {
        title,
        titleBase,
        description,
        ogDescription,
        twitterDescription,
        canonical,
        robots,
        keywords,
        image,
        ogType,
        twitterCard,
        schema,
        isHomepage,
    };
}

