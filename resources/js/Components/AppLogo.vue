<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    size: {
        type: String,
        default: 'md',
    },
    markOnly: {
        type: Boolean,
        default: false,
    },
    surface: {
        type: String,
        default: 'none',
    },
});

const { t } = useI18n();

const sizeClasses = {
    sm: 'h-7',
    md: 'h-8',
    lg: 'h-9',
    xl: 'h-10',
};

const imageClass = (size, markOnly) => [
    sizeClasses[size] ?? sizeClasses.md,
    markOnly ? 'w-auto aspect-square' : 'w-auto',
    'shrink-0 object-contain',
];

const imgUrl = (path, w, fmt) => {
    const p = String(path ?? '').startsWith('/') ? String(path ?? '') : `/${String(path ?? '')}`;
    const parts = [
        `path=${encodeURIComponent(p)}`,
        `w=${encodeURIComponent(String(w))}`,
        `fmt=${encodeURIComponent(String(fmt))}`,
    ];
    return `/_marketing-image?${parts.join('&')}`;
};

const srcset = (path, fmt) => [64, 96, 128, 160, 192, 256, 320, 384, 512]
    .map((w) => `${imgUrl(path, w, fmt)} ${w}w`)
    .join(', ');
</script>

<template>
    <span
        v-if="surface === 'light'"
        class="inline-flex items-center rounded-xl bg-white dark:bg-slate-900 px-3 py-1.5 shadow-sm shadow-black/10 ring-1 ring-black/5"
    >
        <picture>
            <source type="image/avif" :srcset="srcset(markOnly ? '/icon.png' : '/logo.png', 'avif')" sizes="(max-width: 640px) 128px, 160px" />
            <source type="image/webp" :srcset="srcset(markOnly ? '/icon.png' : '/logo.png', 'webp')" sizes="(max-width: 640px) 128px, 160px" />
            <img
                :src="imgUrl(markOnly ? '/icon.png' : '/logo.png', 256, 'auto')"
                :alt="t('app.name')"
                :class="imageClass(size, markOnly)"
                width="512"
                height="512"
                loading="eager"
                fetchpriority="high"
                decoding="async"
            >
        </picture>
    </span>
    <picture v-else>
        <source type="image/avif" :srcset="srcset(markOnly ? '/icon.png' : '/logo.png', 'avif')" sizes="(max-width: 640px) 128px, 160px" />
        <source type="image/webp" :srcset="srcset(markOnly ? '/icon.png' : '/logo.png', 'webp')" sizes="(max-width: 640px) 128px, 160px" />
        <img
            :src="imgUrl(markOnly ? '/icon.png' : '/logo.png', 256, 'auto')"
            :alt="t('app.name')"
            :class="imageClass(size, markOnly)"
            width="512"
            height="512"
            loading="eager"
            fetchpriority="high"
            decoding="async"
        >
    </picture>
</template>
