<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    widths: { type: Array, default: () => [320, 480, 640, 768, 1024, 1280, 1536] },
    sizes: { type: String, default: '100vw' },
    width: { type: [Number, String], default: null },
    height: { type: [Number, String], default: null },
    priority: { type: Boolean, default: false },
    class: { type: [String, Array, Object], default: '' },
});

const normalizePath = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    return raw.startsWith('/') ? raw : `/${raw}`;
};

const humanizeAlt = (value) => {
    const raw = String(value ?? '').trim();
    if (raw) return raw;
    const path = normalizePath(props.src);
    const file = path.split('/').pop() ?? '';
    const base = file.replace(/\.(png|jpg|jpeg|webp|avif|gif|svg)$/i, '');
    const spaced = base.replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
    if (!spaced) return '';
    return spaced.replace(/\b\w/g, (c) => c.toUpperCase());
};

const isRemote = computed(() => /^https?:\/\//i.test(String(props.src)));

const buildUrl = (path, w, fmt, blur = false) => {
    const p = normalizePath(path);
    const parts = [
        `path=${encodeURIComponent(p)}`,
        `w=${encodeURIComponent(String(w))}`,
        `fmt=${encodeURIComponent(String(fmt))}`,
        ...(blur ? ['blur=1'] : []),
    ];
    const relative = `/_marketing-image?${parts.join('&')}`;

    if (typeof window === 'undefined') {
        return relative;
    }

    try {
        return new URL(relative, window.location.origin).toString();
    } catch {
        return relative;
    }
};

const resolvedAlt = computed(() => humanizeAlt(props.alt));

const resolvedWidths = computed(() => (
    props.widths
        .map((w) => Number(w))
        .filter((w) => Number.isFinite(w) && w > 0)
        .sort((a, b) => a - b)
));

const placeholderUrl = computed(() => {
    if (isRemote.value) return '';
    const w = Math.min(48, resolvedWidths.value[0] ?? 48);
    return buildUrl(props.src, w, 'webp', true);
});

const avifSrcset = computed(() => {
    if (isRemote.value) return '';
    return resolvedWidths.value.map((w) => `${buildUrl(props.src, w, 'avif')} ${w}w`).join(', ');
});

const webpSrcset = computed(() => {
    if (isRemote.value) return '';
    return resolvedWidths.value.map((w) => `${buildUrl(props.src, w, 'webp')} ${w}w`).join(', ');
});

const fallbackSrc = computed(() => {
    if (isRemote.value) return normalizePath(props.src);
    const w = resolvedWidths.value[2] ?? 640;
    return buildUrl(props.src, w, 'auto');
});

const loaded = ref(false);
</script>

<template>
    <span
        class="relative inline-block"
        :class="props.class"
        :style="placeholderUrl && !loaded ? { backgroundImage: `url(${placeholderUrl})`, backgroundSize: 'cover', backgroundPosition: 'center' } : null"
    >
        <picture v-if="!isRemote">
            <source v-if="avifSrcset" type="image/avif" :srcset="avifSrcset" :sizes="sizes" />
            <source v-if="webpSrcset" type="image/webp" :srcset="webpSrcset" :sizes="sizes" />
            <img
                :src="fallbackSrc"
                :alt="resolvedAlt"
                :width="width"
                :height="height"
                :loading="priority ? 'eager' : 'lazy'"
                :fetchpriority="priority ? 'high' : 'auto'"
                decoding="async"
                class="block h-full w-full"
                :class="loaded ? '' : 'opacity-0'"
                @load="loaded = true"
            >
        </picture>
        <img
            v-else
            :src="normalizePath(src)"
            :alt="resolvedAlt"
            :width="width"
            :height="height"
            :loading="priority ? 'eager' : 'lazy'"
            :fetchpriority="priority ? 'high' : 'auto'"
            decoding="async"
            class="block h-full w-full"
        >
        <span
            v-if="placeholderUrl && !loaded"
            class="pointer-events-none absolute inset-0 backdrop-blur-md"
        />
    </span>
</template>

