<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useDashboardAlarm } from './composables/useDashboardAlarm';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const publicRoutes = ['/', '/login', '/register'];
const { alarm, alarmRinging, startGlobalAlarmWatcher, stopGlobalAlarmWatcher, stopAlarmSound } = useDashboardAlarm();
const clockIconPath = 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6v6l4 2';

function fa(input: string | number) {
    return String(input).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function isPublicRoute(path: string) {
    return publicRoutes.includes(path) || path.startsWith('/s/') || path.startsWith('/shared-notes/');
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
    startGlobalAlarmWatcher();
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
    stopGlobalAlarmWatcher();
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
    <div v-if="alarmRinging" class="modal-backdrop alarm-backdrop" dir="rtl">
        <div class="modal-card alarm-ring-modal" role="alertdialog" aria-modal="true">
            <div class="alarm-ring-icon">
                <svg viewBox="0 0 24 24"><path :d="clockIconPath" /></svg>
            </div>
            <h2>{{ alarm.title || 'وقتشه!' }}</h2>
            <p>آلارم ساعت {{ fa(alarm.time) }} رسید.</p>
            <button type="button" @click="stopAlarmSound()">توقف زنگ</button>
        </div>
    </div>
</template>
