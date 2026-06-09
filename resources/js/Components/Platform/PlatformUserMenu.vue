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
            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1.5 pr-2.5 text-left shadow-sm transition hover:bg-slate-50"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <AppAvatar :name="user?.name" :email="user?.email" size="sm" />
            <span class="hidden max-w-[9rem] truncate text-sm font-medium text-slate-700 sm:block">{{ user?.name }}</span>
            <svg class="hidden h-4 w-4 text-slate-400 sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
            role="menu"
        >
            <div class="border-b border-slate-100 px-4 py-3">
                <p class="truncate text-sm font-semibold text-slate-900">{{ user?.name }}</p>
                <p class="truncate text-xs text-slate-500">{{ user?.email }}</p>
                <p v-if="user?.roles?.length" class="mt-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                    {{ user.roles.join(', ').replaceAll('_', ' ') }}
                </p>
            </div>
            <Link
                v-if="can('profile.manage')"
                href="/admin/profile"
                class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50"
                @click="close"
            >
                Profile & password
            </Link>
            <Link href="/" class="block px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" @click="close">
                Public site
            </Link>
            <button
                type="button"
                class="block w-full border-t border-slate-100 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50"
                @click="logout"
            >
                Sign out
            </button>
        </div>
    </div>
</template>
