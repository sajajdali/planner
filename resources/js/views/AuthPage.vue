<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

type AuthMode = 'login' | 'register';
type AuthStep = 'phone' | 'code' | 'profile' | 'done';

const mode = ref<AuthMode>(route.path === '/register' ? 'register' : 'login');
const step = ref<AuthStep>('phone');
const phone = ref('');
const code = ref('');
const name = ref('');
const city = ref('');
const education = ref('');
const job = ref('');
const error = ref('');
const sent = ref(false);
const phoneSubmitted = ref('');
const codeSubmitted = ref('');
const codeInputRef = ref<HTMLInputElement | null>(null);

const title = computed(() => {
    if (step.value === 'code') return 'کد تایید رو وارد کن';
    if (step.value === 'profile') return 'عضویت رو کامل کن';
    if (step.value === 'done') return mode.value === 'login' ? 'خوش اومدی' : 'ثبت‌نام انجام شد';
    return mode.value === 'register' ? 'عضویت با شماره موبایل' : 'ورود با شماره موبایل';
});

const subtitle = computed(() => {
    if (step.value === 'code') return `کد چهار رقمی ارسال شده به ${phone.value}`;
    if (step.value === 'profile') return 'چند مشخصات کوتاه برای ساخت دفتر برنامه‌ریزی';
    if (step.value === 'done') return 'دفتر آماده است؛ ادامه بده و برنامه امروزت رو بچین.';
    return mode.value === 'register' ? 'شماره‌ات رو بزن، کد بگیر و سریع وارد دفتر شو' : 'شماره موبایل ثبت‌شده‌ات رو وارد کن';
});

watch(() => route.path, () => {
    mode.value = route.path === '/register' ? 'register' : 'login';
    step.value = 'phone';
    error.value = '';
    code.value = '';
    sent.value = false;
    phoneSubmitted.value = '';
    codeSubmitted.value = '';
});

watch(step, async (current) => {
    if (current !== 'code') return;

    await nextTick();
    codeInputRef.value?.focus();
    codeInputRef.value?.select();
});

function normalizeDigits(value: string) {
    const fa = '۰۱۲۳۴۵۶۷۸۹';
    const ar = '٠١٢٣٤٥٦٧٨٩';
    return value.replace(/[۰-۹٠-٩]/g, (digit) => {
        const faIndex = fa.indexOf(digit);
        return String(faIndex >= 0 ? faIndex : ar.indexOf(digit));
    }).replace(/\s|-/g, '');
}

async function sendCode() {
    error.value = '';
    const normalized = normalizeDigits(phone.value);
    if (auth.loading || phoneSubmitted.value === normalized) return;

    if (!/^09\d{9}$/.test(normalized)) {
        error.value = 'شماره موبایل رو کامل و با 09 وارد کن.';
        return;
    }

    phone.value = normalized;
    phoneSubmitted.value = normalized;
    try {
        const response = await auth.sendPhoneCode(phone.value, mode.value);
        sent.value = response.sent;
        step.value = 'code';
        code.value = '';
        if (!response.sent) {
            error.value = 'پیامک ارسال نشد؛ برای ورود تستی کد 9990 را وارد کن.';
        }
    } catch {
        error.value = mode.value === 'login'
            ? 'حسابی با این شماره پیدا نشد یا پیامک ارسال نشد.'
            : 'این شماره قبلاً ثبت شده یا پیامک ارسال نشد.';
        phoneSubmitted.value = '';
    }
}

async function verifyCode() {
    error.value = '';
    const normalizedCode = normalizeDigits(code.value);
    if (auth.loading || codeSubmitted.value === normalizedCode) return;

    if (!/^\d{4}$/.test(normalizedCode)) {
        error.value = 'کد تایید چهار رقمی رو وارد کن.';
        return;
    }

    code.value = normalizedCode;
    codeSubmitted.value = normalizedCode;

    if (mode.value === 'register') {
        step.value = 'profile';
        return;
    }

    try {
        await auth.phoneLogin(phone.value, normalizedCode);
        step.value = 'done';
    } catch {
        error.value = 'کد تایید درست نیست یا منقضی شده است.';
        codeSubmitted.value = '';
    }
}

function onPhoneInput() {
    phone.value = normalizeDigits(phone.value).replace(/\D/g, '').slice(0, 11);
    error.value = '';

    if (phone.value.length === 11) {
        void sendCode();
    }
}

function onCodeInput() {
    code.value = normalizeDigits(code.value).replace(/\D/g, '').slice(0, 4);
    error.value = '';

    if (code.value.length === 4) {
        void verifyCode();
    }
}

async function completeRegister() {
    error.value = '';
    if (!name.value.trim()) {
        error.value = 'نام و نام خانوادگی رو وارد کن.';
        return;
    }

    try {
        await auth.phoneRegister({
            phone: phone.value,
            code: normalizeDigits(code.value),
            name: name.value.trim(),
            city: city.value.trim(),
            education: education.value.trim(),
            job: job.value.trim(),
        });
        step.value = 'done';
    } catch {
        error.value = 'این شماره قبلاً ثبت شده یا اطلاعات کامل نیست.';
    }
}

function backOneStep() {
    error.value = '';
    phoneSubmitted.value = '';
    codeSubmitted.value = '';
    if (step.value === 'profile') {
        step.value = 'code';
        return;
    }
    if (step.value === 'code') {
        step.value = 'phone';
        return;
    }
    router.push('/');
}
</script>

<template>
    <main class="auth-shell" dir="rtl">
        <section class="auth-card auth-otp-card">
            <i class="landing-tape yellow"></i>
            <i class="landing-tape cyan"></i>

            <div class="auth-brand">
                <div class="notebook-logo brand-icon-mark">
                    <img :src="'/brand/bejelo-mark.png'" alt="" />
                    <span>ر</span>
                </div>
                <strong>دفتر یادداشت</strong>
                <span>ورود و عضویت با کد تایید</span>
            </div>

            <div v-if="step === 'phone'" class="auth-tabs">
                <RouterLink to="/login" :class="{ active: mode === 'login' }">ورود</RouterLink>
                <RouterLink to="/register" :class="{ active: mode === 'register' }">ثبت‌نام</RouterLink>
            </div>

            <form v-if="step === 'phone'" class="auth-form" @submit.prevent="sendCode">
                <h1>{{ title }}</h1>
                <p>{{ subtitle }}</p>

                <label>
                    شماره موبایل
                    <input v-model="phone" class="phone-input" inputmode="tel" autocomplete="tel" maxlength="11" placeholder="09123456789" required @input="onPhoneInput" />
                </label>

                <p v-if="error" class="form-error">{{ error }}</p>

                <button type="submit" :disabled="auth.loading">{{ auth.loading ? 'در حال ارسال...' : 'دریافت کد' }}</button>
            </form>

            <form v-else-if="step === 'code'" class="auth-form" @submit.prevent="verifyCode">
                <h1>{{ title }}</h1>
                <p>{{ subtitle }}</p>

                <label>
                    کد تایید
                    <input ref="codeInputRef" v-model="code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="4" placeholder="1234" required @input="onCodeInput" />
                </label>

                <p v-if="error" class="form-error">{{ error }}</p>

                <button type="submit" :disabled="auth.loading">
                    {{ auth.loading ? 'در حال بررسی...' : 'ادامه' }}
                </button>
                <button type="button" class="ghost-auth-btn" @click="backOneStep">ویرایش شماره</button>
            </form>

            <form v-else-if="step === 'profile'" class="auth-form" @submit.prevent="completeRegister">
                <h1>{{ title }}</h1>
                <p>{{ subtitle }}</p>

                <label>
                    نام و نام خانوادگی
                    <input v-model="name" autocomplete="name" placeholder="مثلاً: سجاد احمدی" required />
                </label>
                <label>
                    شهر
                    <input v-model="city" placeholder="مثلاً: تهران" />
                </label>
                <label>
                    تحصیلات
                    <input v-model="education" placeholder="مثلاً: کارشناسی" />
                </label>
                <label>
                    شغل
                    <input v-model="job" placeholder="مثلاً: مدیر فروش" />
                </label>

                <p v-if="error" class="form-error">{{ error }}</p>

                <button type="submit" :disabled="auth.loading">
                    {{ auth.loading ? 'در حال ساخت حساب...' : 'تکمیل عضویت' }}
                </button>
                <button type="button" class="ghost-auth-btn" @click="backOneStep">بازگشت به کد</button>
            </form>

            <div v-else class="auth-done">
                <div class="auth-checkmark">✓</div>
                <h1>{{ title }}</h1>
                <p>{{ subtitle }}</p>
                <button type="button" @click="router.replace('/app')">ورود به برنامه</button>
            </div>

            <RouterLink v-if="step === 'phone'" class="auth-back" to="/">بازگشت به صفحه اصلی</RouterLink>
            <button v-else-if="step !== 'done'" type="button" class="auth-back auth-back-button" @click="backOneStep">بازگشت</button>
        </section>
    </main>
</template>
