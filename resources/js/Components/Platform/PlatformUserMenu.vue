<script setup>
import AppAvatar from '../AppAvatar.vue';
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { usePlatformAdmin } from '../../composables/usePlatformAdmin.js';

const { user, can } = usePlatformAdmin();
const open = ref(false);
const root = ref(null);

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const logout = () => {
    close();
    router.post('/admin/logout');
};

const onDocumentClick = (event) => {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1.5 pr-2.5 text-left shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-800"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <AppAvatar :name="user?.name" :email="user?.email" size="sm" />
            <span class="hidden max-w-[9rem] truncate text-sm font-medium text-slate-700 dark:text-slate-300 sm:block">{{ user?.name }}</span>
            <svg class="hidden h-4 w-4 text-slate-400 dark:text-slate-500 sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition name="dropdown">
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-1 shadow-xl"
                role="menu"
            >
            <div class="border-b border-slate-100 dark:border-slate-800 px-4 py-3">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user?.email }}</p>
                <p v-if="user?.roles?.length" class="mt-1 text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    {{ user.roles.join(', ').replaceAll('_', ' ') }}
                </p>
            </div>
            <Link
                v-if="can('profile.manage')"
                href="/admin/profile"
                class="block px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                @click="close"
            >
                {{ $t('components.profile_and_password') }}
            </Link>
            <Link href="/" class="block px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-800" @click="close">
                {{ $t('components.public_site') }}
            </Link>
            <button
                type="button"
                class="block w-full border-t border-slate-100 dark:border-slate-800 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 dark:bg-red-950/40"
                @click="logout"
            >
                {{ $t('components.sign_out') }}
            </button>
            </div>
        </Transition>
    </div>
</template>
