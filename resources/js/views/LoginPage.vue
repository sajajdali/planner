<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const email = ref('armin@example.com');
const password = ref('password');
const error = ref('');

async function submit() {
    error.value = '';
    try {
        await auth.login(email.value, password.value);
        router.replace('/');
    } catch {
        error.value = 'ایمیل یا رمز عبور درست نیست.';
    }
}
</script>

<template>
    <main class="login-shell" dir="rtl">
        <section class="login-panel">
            <div class="login-brand">
                <div class="brand-mark brand-icon-mark">
                    <img :src="'/brand/bejelo-mark.png'" alt="" />
                    <span>ر</span>
                </div>
                <div>
                    <p>برنامه‌ریز روزانه</p>
                    <span>ورود به حساب کاربری</span>
                </div>
            </div>

            <form class="login-card" @submit.prevent="submit">
                <h1>خوش برگشتی</h1>
                <p class="muted">برای دیدن برنامه، وظایف و تایمرهای خودت وارد شو.</p>

                <label>
                    ایمیل
                    <input v-model="email" type="email" autocomplete="email" required />
                </label>

                <label>
                    رمز عبور
                    <input v-model="password" type="password" autocomplete="current-password" required />
                </label>

                <p v-if="error" class="form-error">{{ error }}</p>

                <button type="submit" :disabled="auth.loading">
                    {{ auth.loading ? 'در حال ورود...' : 'ورود به برنامه' }}
                </button>

                <div class="demo-hint">
                    حساب نمونه: <strong>armin@example.com</strong> / <strong>password</strong>
                </div>
            </form>
        </section>

        <aside class="login-preview">
            <div class="preview-card">
                <span>پیشرفت امروز</span>
                <strong>۶۷٪</strong>
                <div class="mini-progress"><i></i></div>
            </div>
            <div class="preview-list">
                <div><b></b> طراحی صفحه ورود</div>
                <div><b></b> تمرین هوازی</div>
                <div><b></b> پیگیری پرداخت مشتری</div>
            </div>
        </aside>
    </main>
</template>
