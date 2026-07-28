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
const avatarProcessing = ref(false);
const avatarStatus = ref('');

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

async function onAvatarChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    error.value = '';
    avatarStatus.value = '';

    if (!file) return;
    if (!file.type.startsWith('image/')) {
        error.value = 'فقط فایل تصویر قابل قبول است.';
        input.value = '';
        return;
    }

    avatarProcessing.value = true;
    avatarStatus.value = file.size > 900 * 1024 ? 'در حال کم‌کردن حجم عکس...' : 'در حال آماده‌سازی عکس...';

    try {
        const optimized = await optimizeAvatar(file);
        avatarFile.value = optimized;
        if (avatarPreview.value?.startsWith('blob:')) URL.revokeObjectURL(avatarPreview.value);
        avatarPreview.value = URL.createObjectURL(optimized);
        avatarStatus.value = optimized.size < file.size
            ? `حجم عکس کم شد و آماده آپلود است.`
            : 'عکس آماده آپلود است.';
    } catch {
        error.value = 'آماده‌سازی عکس انجام نشد. لطفاً یک تصویر دیگر انتخاب کن.';
    } finally {
        avatarProcessing.value = false;
        input.value = '';
    }
}

function loadImage(file: File) {
    return new Promise<HTMLImageElement>((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Image load failed'));
        };
        image.src = url;
    });
}

async function optimizeAvatar(file: File) {
    const targetSize = 900 * 1024;
    const maxSide = 1200;
    if (file.size <= targetSize && !file.type.includes('png')) return file;

    const image = await loadImage(file);
    const ratio = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));
    const width = Math.max(1, Math.round(image.naturalWidth * ratio));
    const height = Math.max(1, Math.round(image.naturalHeight * ratio));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Canvas is not supported');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, width, height);
    context.drawImage(image, 0, 0, width, height);

    for (const quality of [0.82, 0.74, 0.66, 0.58]) {
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        if (!blob) continue;
        if (blob.size <= targetSize || quality === 0.58) {
            return new File([blob], `${file.name.replace(/\.[^.]+$/, '') || 'avatar'}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now(),
            });
        }
    }

    return file;
}

async function saveProfile() {
    error.value = '';
    saved.value = false;

    if (!name.value.trim()) {
        error.value = 'نام را وارد کن.';
        return;
    }

    try {
        avatarStatus.value = avatarFile.value ? 'در حال آپلود عکس...' : 'در حال ذخیره پروفایل...';
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
        avatarStatus.value = '';
    } catch (exception: any) {
        error.value = exception?.response?.data?.message || 'ذخیره پروفایل انجام نشد. عکس یا اطلاعات را بررسی کن.';
        avatarStatus.value = '';
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
                    <button type="button" class="avatar-safe-zone" :class="{ loading: avatarProcessing || auth.loading }" :disabled="avatarProcessing || auth.loading" @click="chooseAvatar">
                        <span class="avatar-guide">عکس پروفایل</span>
                        <img v-if="avatarUrl" :src="avatarUrl" alt="تصویر پروفایل" />
                        <strong v-else>{{ emoji || initials }}</strong>
                        <small>{{ avatarProcessing ? 'در حال آماده‌سازی...' : auth.loading ? 'در حال آپلود...' : 'برای تغییر عکس بزن' }}</small>
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

                    <p v-if="avatarStatus" class="profile-upload-status">{{ avatarStatus }}</p>
                    <p v-if="error" class="form-error">{{ error }}</p>
                    <p v-if="saved" class="profile-success">پروفایل ذخیره شد.</p>

                    <button type="submit" :disabled="auth.loading || avatarProcessing">
                        {{ avatarProcessing ? 'در حال آماده‌سازی عکس...' : auth.loading ? 'در حال آپلود و ذخیره...' : 'ذخیره پروفایل' }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>
