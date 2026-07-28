<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const open = ref(false);
const menuRef = ref<HTMLElement | null>(null);

const avatarUrl = computed(() => auth.user?.avatar_url || null);
const avatarText = computed(() => auth.user?.profile_emoji || auth.user?.name?.slice(0, 1) || 'م');
const firstName = computed(() => auth.user?.name?.split(' ')[0] || 'کاربر');
const items = [
    { label: 'داشبورد', path: '/app', color: '#2563EB', icon: 'M3 13h8V3H3v10zM13 21h8V3h-8v18zM3 21h8v-6H3v6z' },
    { label: 'هدف', path: '/goals', color: '#9B5DE5', icon: 'M12 2v6M12 22a10 10 0 100-20 10 10 0 000 20zM12 16a4 4 0 100-8 4 4 0 000 8z' },
    { label: 'گزارش گیری', path: '/reports/monthly', color: '#D63384', icon: 'M4 19V5M8 19v-8M12 19V7M16 19v-4M20 19V9' },
    { label: 'مدیریت مالی', path: '/finance', color: '#16A34A', icon: 'M3 7h15a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM16 12h3M7 7V5a2 2 0 012-2h6' },
    { label: 'تنظیمات', path: '/settings', color: '#F97316', icon: 'M12 15.5A3.5 3.5 0 1012 8a3.5 3.5 0 000 7.5zM19.4 15a1.7 1.7 0 00.3 1.9l.1.1-2 3.4-.2-.1a1.7 1.7 0 00-2 .3l-.1.1-4 0-.1-.1a1.7 1.7 0 00-2-.3l-.2.1-2-3.4.1-.1A1.7 1.7 0 007.6 15l-.1-.2-2-3.5.1-.2A1.7 1.7 0 005.6 9L5.5 9l2-3.4.2.1a1.7 1.7 0 002-.3l.1-.1h4l.1.1a1.7 1.7 0 002 .3l.2-.1 2 3.4-.1.1a1.7 1.7 0 00-.3 1.9l.1.2 2 3.5-.1.3z' },
    { label: 'وظایف گروهی', path: '/group-tasks', color: '#2563EB', icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75' },
    { label: 'پروفایل', path: '/profile', color: '#22D3D0', icon: 'M20 21a8 8 0 10-16 0M12 13a5 5 0 100-10 5 5 0 000 10z' },
];

async function go(path: string) {
    open.value = false;
    await router.push(path);
}

async function logout() {
    open.value = false;
    await auth.logout();
    await router.push('/login');
}

function closeOnOutsideClick(event: MouseEvent) {
    if (!open.value) return;

    const target = event.target;
    if (target instanceof Node && menuRef.value?.contains(target)) return;

    open.value = false;
}

onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutsideClick, true);
});
</script>

<template>
    <div ref="menuRef" class="app-menu-wrap">
        <button class="shared-menu-button" :class="{ active: open }" aria-label="بازکردن منو" @click="open = !open">
            <span></span><span></span><span></span>
        </button>
        <button v-if="open" class="shared-menu-backdrop" type="button" aria-label="بستن منو" @click="open = false"></button>
        <aside v-if="open" class="shared-drawer">
            <button class="shared-user" type="button" @click="go('/profile')">
                <i>
                    <img v-if="avatarUrl" :src="avatarUrl" alt="" />
                    <b v-else>{{ avatarText }}</b>
                </i>
                <div>
                    <strong>{{ firstName }}</strong>
                    <small>{{ auth.user?.phone || auth.user?.email }}</small>
                </div>
            </button>
            <button
                v-for="item in items"
                :key="item.path"
                :class="{ active: route.path === item.path }"
                :style="{ '--c': item.color }"
                type="button"
                @click="go(item.path)"
            >
                <i><svg viewBox="0 0 24 24"><path :d="item.icon"></path></svg></i>
                <span>{{ item.label }}</span>
            </button>
            <button class="shared-logout" type="button" @click="logout">
                <i><svg viewBox="0 0 24 24"><path d="M10 17l-5-5 5-5M5 12h12M14 4h4a2 2 0 012 2v12a2 2 0 01-2 2h-4"></path></svg></i>
                <span>خروج از حساب</span>
            </button>
        </aside>
    </div>
</template>

<style scoped>
.app-menu-wrap{position:relative;display:inline-flex}.shared-menu-button{position:relative;z-index:92;width:42px;height:40px;border:3px solid #3a2e1f;border-radius:12px;background:#fff;display:flex;flex-direction:column;justify-content:center;gap:5px;padding:0 9px;box-shadow:3px 3px 0 #3a2e1f;cursor:pointer}.shared-menu-button span{height:4px;background:#3a2e1f;border-radius:999px}.shared-menu-button.active{background:#ffd93d}.shared-menu-backdrop{display:none}.shared-drawer{position:absolute;top:52px;left:0;z-index:91;display:grid;gap:8px;min-width:260px;padding:12px;border:3px solid #3a2e1f;border-radius:16px;background:#fffaf0;box-shadow:6px 6px 0 #3a2e1f}.shared-user{display:flex;align-items:center;gap:10px;min-width:0;padding:10px;border:1.5px dashed #eadfbe;border-radius:14px;background:#fff}.shared-user i{width:46px;height:46px;border:2px solid #3a2e1f;border-radius:14px;background:#22d3d0;display:grid;place-items:center;overflow:hidden;flex-shrink:0}.shared-user img{width:100%;height:100%;object-fit:cover}.shared-user b{font-size:22px;font-style:normal}.shared-user div{min-width:0}.shared-user strong,.shared-user small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.shared-user strong{font-size:14px}.shared-user small{color:#8a7a5b;font-size:10px;margin-top:2px}.shared-drawer button{height:44px;border:0;border-radius:12px;background:color-mix(in srgb,var(--c) 16%,white);display:flex;align-items:center;gap:10px;padding:0 10px;color:#3a2e1f;font-weight:900;cursor:pointer;text-align:right}.shared-drawer button.active{outline:2px solid var(--c);background:color-mix(in srgb,var(--c) 28%,white)}.shared-drawer button i{width:30px;height:30px;border:2px solid #3a2e1f;border-radius:10px;background:var(--c);display:grid;place-items:center;flex-shrink:0;box-shadow:1px 1px 0 #3a2e1f}.shared-drawer svg{width:16px;height:16px;fill:none;stroke:#fff;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}.shared-drawer .shared-logout{--c:#DC2626;margin-top:4px;border-top:1.5px dashed #eadfbe;border-radius:14px;background:linear-gradient(135deg,#fee2e2,#fff);color:#991b1b}.shared-drawer .shared-logout i{background:#dc2626}.shared-drawer .shared-logout:hover{background:linear-gradient(135deg,#fecaca,#fff7ed);transform:translate(-1px,-1px);box-shadow:2px 2px 0 rgba(58,46,31,.25)}
@media(max-width:640px){.app-menu-wrap{position:relative;z-index:5002}.shared-menu-button{width:40px;height:40px;background:#ffd93d}.shared-menu-backdrop{position:fixed;inset:0;z-index:90;display:block;border:0;background:rgba(20,14,28,.54);backdrop-filter:blur(4px);cursor:pointer}.shared-drawer{position:fixed;top:50%;left:50%;right:auto;width:min(320px,calc(100vw - 32px));min-width:0;max-height:min(78dvh,520px);overflow:auto;transform:translate(-50%,-50%);gap:7px;padding:12px;border-width:3px;border-radius:18px;box-shadow:7px 7px 0 rgba(58,46,31,.96),0 18px 48px rgba(20,14,28,.3);overscroll-behavior:contain}.shared-user{min-height:48px;padding:8px 9px;gap:8px;border-radius:13px}.shared-user i{width:38px;height:38px;border-radius:12px}.shared-user b{font-size:18px}.shared-user strong{font-size:13px}.shared-user small{font-size:10px}.shared-drawer button{min-height:40px;height:40px;border-radius:11px;font-size:13px;gap:8px;padding:0 9px}.shared-drawer button i{width:28px;height:28px;border-width:1.5px;border-radius:9px}.shared-drawer svg{width:15px;height:15px}.shared-drawer .shared-logout{margin-top:3px;border-radius:12px}}
</style>
