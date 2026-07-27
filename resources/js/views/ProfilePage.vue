<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppMenu from '../components/AppMenu.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const fileInput = ref<HTMLInputElement | null>(null);
const name = ref(auth.user?.name ?? '');
const email = ref(auth.user?.email ?? '');
const phone = ref(auth.user?.phone ?? '');
const emoji = ref(auth.user?.profile_emoji ?? '🙂');
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const saved = ref(false);
const error = ref('');

const avatarUrl = computed(() => avatarPreview.value || auth.user?.avatar_url || null);
const initials = computed(() => (name.value.trim().slice(0, 1) || 'ر'));
const welcomeName = computed(() => auth.user?.name?.split(' ')[0] || name.value.split(' ')[0] || 'دوست خوب');

watch(() => auth.user, (user) => {
    name.value = user?.name ?? '';
    email.value = user?.email ?? '';
    phone.value = user?.phone ?? '';
    emoji.value = user?.profile_emoji ?? '🙂';
}, { deep: true });

function chooseAvatar() {
    fileInput.value?.click();
}

function onAvatarChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    error.value = '';

    if (!file) return;
    if (!file.type.startsWith('image/')) {
        error.value = 'فقط فایل تصویر قابل قبول است.';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        error.value = 'حجم عکس باید کمتر از ۲ مگابایت باشد.';
        return;
    }

    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
}

async function saveProfile() {
    error.value = '';
    saved.value = false;

    if (!name.value.trim()) {
        error.value = 'نام را وارد کن.';
        return;
    }

    try {
        await auth.updateProfile({
            name: name.value.trim(),
            email: email.value.trim(),
            phone: phone.value.trim(),
            profile_emoji: emoji.value.trim() || '🙂',
            avatar: avatarFile.value,
        });
        avatarFile.value = null;
        avatarPreview.value = null;
        saved.value = true;
    } catch {
        error.value = 'ذخیره پروفایل انجام نشد. عکس یا اطلاعات را بررسی کن.';
    }
}

async function logout() {
    await auth.logout();
    await router.push('/login');
}
</script>

<template>
    <main class="profile-shell profile-polished" dir="rtl">
        <section class="profile-card">
            <i class="landing-tape yellow"></i>
            <i class="landing-tape cyan"></i>

            <header class="profile-head">
                <div>
                    <span>پروفایل</span>
                    <h1>{{ welcomeName }} خوش آمدید</h1>
                    <p>اطلاعات حساب و تصویرت را همین‌جا مرتب کن.</p>
                </div>
                <div class="profile-head-actions">
                    <AppMenu />
                    <RouterLink to="/app">داشبورد</RouterLink>
                </div>
            </header>

            <div class="profile-body">
                <aside class="profile-showcase">
                    <button type="button" class="avatar-safe-zone" @click="chooseAvatar">
                        <span class="avatar-guide">عکس پروفایل</span>
                        <img v-if="avatarUrl" :src="avatarUrl" alt="تصویر پروفایل" />
                        <strong v-else>{{ emoji || initials }}</strong>
                        <small>برای تغییر عکس بزن</small>
                    </button>
                    <div class="profile-id-card">
                        <strong>{{ name || 'نام شما' }}</strong>
                        <span>{{ phone || email || 'اطلاعات تماس ثبت نشده' }}</span>
                    </div>
                    <button type="button" class="profile-logout-card" @click="logout">
                        <i><svg viewBox="0 0 24 24"><path d="M10 17l-5-5 5-5M5 12h12M14 4h4a2 2 0 012 2v12a2 2 0 01-2 2h-4"></path></svg></i>
                        <span>خروج از حساب</span>
                    </button>
                </aside>
                <input ref="fileInput" type="file" accept="image/*" hidden @change="onAvatarChange" />

                <form class="profile-form" @submit.prevent="saveProfile">
                    <label>
                        نام نمایشی
                        <input v-model="name" placeholder="مثلاً: سجاد" required />
                    </label>
                    <label>
                        ایمیل
                        <input v-model="email" type="email" placeholder="name@example.com" />
                    </label>
                    <label>
                        شماره موبایل
                        <input v-model="phone" inputmode="numeric" placeholder="09123456789" />
                    </label>
                    <label>
                        ایموجی وقتی عکس نداری
                        <input v-model="emoji" class="emoji-input" maxlength="4" placeholder="🙂" />
                    </label>

                    <div class="profile-preview-row">
                        <span>نمایش کوچک</span>
                        <i>
                            <img v-if="avatarUrl" :src="avatarUrl" alt="" />
                            <b v-else>{{ emoji || initials }}</b>
                        </i>
                    </div>

                    <p v-if="error" class="form-error">{{ error }}</p>
                    <p v-if="saved" class="profile-success">پروفایل ذخیره شد.</p>

                    <button type="submit" :disabled="auth.loading">
                        {{ auth.loading ? 'در حال ذخیره...' : 'ذخیره پروفایل' }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>
