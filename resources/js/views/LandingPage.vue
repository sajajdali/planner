<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>

<template>
    <main class="landing-shell" dir="rtl">
        <header class="landing-header">
            <div class="landing-brand">
                <div class="notebook-logo">ر</div>
                <span>دفتر برنامه‌ریزی من</span>
            </div>
            <RouterLink v-if="auth.user" class="landing-profile-chip" to="/profile">
                <i>
                    <img v-if="auth.user.avatar_url" :src="auth.user.avatar_url" alt="" />
                    <b v-else>{{ auth.user.profile_emoji || '🙂' }}</b>
                </i>
                <span>{{ auth.user.name.split(' ')[0] }} خوش آمدید</span>
                <strong>پروفایل</strong>
            </RouterLink>
            <RouterLink class="landing-login" :to="auth.user ? '/app' : '/login'">
                {{ auth.user ? 'ورود به برنامه' : 'ورود / ثبت‌نام' }}
            </RouterLink>
        </header>

        <section class="landing-hero">
            <i class="landing-tape yellow"></i>
            <i class="landing-tape cyan"></i>
            <div class="landing-copy">
                <h1>روزتو مثل یک <span>دفتر رنگی</span> برنامه‌ریزی کن</h1>
                <p>وظیفه‌ها رو دسته‌بندی کن، تایمر بزن، زیروظیفه بساز، روتین و مالی روزتو ثبت کن و آخر روز همه چیز رو با یه نگاه ببین.</p>
                <div>
                    <RouterLink class="landing-primary" :to="auth.user ? '/app' : '/register'">شروع رایگان</RouterLink>
                    <a class="landing-secondary" href="#features">دیدن امکانات</a>
                </div>
            </div>

            <aside class="landing-preview-card">
                <strong>برنامه امروز</strong>
                <article class="blue"><i></i><span>توسعه صفحه ورود</span><b>۰۹:۰۰</b></article>
                <article class="orange"><i></i><span>باشگاه</span><b>۰۷:۰۰</b></article>
                <article class="green done"><i></i><span>صبحانه سالم</span></article>
                <div class="landing-progress"><i></i><span>۶۷٪</span></div>
            </aside>
        </section>

        <section id="features" class="landing-features">
            <div class="landing-section-title">
                <h2>همه چیزی که برای یه روز منظم لازم داری</h2>
                <p>بدون شلوغی، فقط چیزهایی که واقعاً به کار میاد</p>
            </div>
            <div class="landing-feature-grid">
                <article class="blue"><b>▣</b><strong>دسته‌بندی رنگی</strong><span>کاری، ورزش، تغذیه، آموزش و زندگی؛ هرکدوم با رنگ خودش.</span></article>
                <article class="orange"><b>◷</b><strong>تایمر زنده</strong><span>زمان واقعی هر کار دقیق ثبت می‌شود و در گزارش می‌آید.</span></article>
                <article class="green"><b>✓</b><strong>زیروظیفه و پیشرفت</strong><span>هر کار را به مراحل کوچک‌تر بشکن و پیشرفت را ببین.</span></article>
                <article class="purple"><b>◌</b><strong>روتین روزانه</strong><span>خواب، بیداری و عادت‌های روزانه را با تیک‌های جذاب دنبال کن.</span></article>
                <article class="cyan"><b>▥</b><strong>مالی روز</strong><span>درآمد و هزینه را جدا ثبت کن و مانده روز را ببین.</span></article>
                <article class="pink"><b>▤</b><strong>گزارش پایان روز</strong><span>کار، روتین، تغذیه و مالی در یک جمع‌بندی تمیز.</span></article>
            </div>
        </section>

        <section class="landing-steps">
            <h2>شروع کردن فقط ۳ قدم داره</h2>
            <div>
                <article><b>۱</b><span>ثبت‌نام کن</span></article>
                <article><b>۲</b><span>وظیفه‌های امروزتو بساز</span></article>
                <article><b>۳</b><span>پیشرفتتو آخر روز ببین</span></article>
            </div>
        </section>

        <section class="landing-cta">
            <h2>امروز رو متفاوت شروع کن</h2>
            <p>ثبت‌نام رایگانه و فقط چند ثانیه طول می‌کشه</p>
            <RouterLink :to="auth.user ? '/app' : '/register'">همین حالا شروع کن</RouterLink>
        </section>

        <footer class="landing-footer">دفتر برنامه‌ریزی من — همه‌جا همراه روزهات</footer>
    </main>
</template>
