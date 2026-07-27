<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const publicRoutes = ['/', '/login', '/register'];

onMounted(async () => {
    await auth.fetchUser();
    if (!auth.user && !publicRoutes.includes(route.path)) {
        router.replace('/login');
    }
});

watch(
    () => [auth.checked, auth.user, route.path],
    () => {
        if (!auth.checked) return;
        if (!auth.user && !publicRoutes.includes(route.path)) router.replace('/login');
        if (auth.user && ['/login', '/register'].includes(route.path)) router.replace('/app');
    },
);
</script>

<template>
    <div v-if="!auth.checked" class="boot-screen">
        <div class="brand-mark">ر</div>
        <span>در حال آماده‌سازی برنامه...</span>
    </div>
    <RouterView v-else />
</template>
