<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const publicRoutes = ['/', '/login', '/register'];

function isPublicRoute(path: string) {
    return publicRoutes.includes(path) || path.startsWith('/shared-notes/');
}

function normalizeDigits(value: string) {
    return value
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
}

function inputTarget(event: Event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)) return null;
    return target;
}

function normalizeTypedDigits(event: Event) {
    const inputEvent = event as InputEvent;
    const target = inputTarget(event);
    if (!target || inputEvent.isComposing || !inputEvent.data) return;

    const normalized = normalizeDigits(inputEvent.data);
    if (normalized === inputEvent.data) return;

    event.preventDefault();
    try {
        target.setRangeText(normalized, target.selectionStart ?? target.value.length, target.selectionEnd ?? target.value.length, 'end');
    } catch {
        target.value = `${target.value}${normalized}`;
    }
    target.dispatchEvent(new Event('input', { bubbles: true }));
}

function normalizeInputDigits(event: Event) {
    const target = inputTarget(event);
    if (!target) return;

    const normalized = normalizeDigits(target.value);
    if (normalized === target.value) return;

    target.value = normalized;
    target.dispatchEvent(new Event('input', { bubbles: true }));
}

onMounted(async () => {
    document.addEventListener('beforeinput', normalizeTypedDigits, true);
    document.addEventListener('input', normalizeInputDigits, true);
    await auth.fetchUser();
    if (!auth.user && !isPublicRoute(route.path)) {
        router.replace('/login');
    }
});

onUnmounted(() => {
    document.removeEventListener('beforeinput', normalizeTypedDigits, true);
    document.removeEventListener('input', normalizeInputDigits, true);
});

watch(
    () => [auth.checked, auth.user, route.path],
    () => {
        if (!auth.checked) return;
        if (!auth.user && !isPublicRoute(route.path)) router.replace('/login');
        if (auth.user && ['/login', '/register'].includes(route.path)) router.replace('/app');
    },
);
</script>

<template>
    <div v-if="!auth.checked" class="boot-screen">
        <div class="brand-mark brand-icon-mark">
            <img :src="'/brand/bejelo-mark.png'" alt="" />
            <span>ر</span>
        </div>
        <span>در حال آماده‌سازی برنامه...</span>
    </div>
    <RouterView v-else />
</template>
