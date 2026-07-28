<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type CategoryStat = { id: number; name: string; color: string; soft_color: string; icon: string; total: number; done: number; actual_seconds: number };
type DayStat = { date: string; tasks_total: number; tasks_done: number; actual_seconds: number; meals_total: number; meals_done: number; routine_total: number; routine_done: number; wake_time: string | null; sleep_time: string | null; income: number; expense: number };
type FinanceEntry = { id: number; title: string; amount: number; type: 'expense' | 'income'; expense_date: string; category: { name: string; color: string } | null };
type RoutineItemStat = { id: number; title: string; color: string; done_days: number };
type ReportPayload = {
    category_stats: CategoryStat[];
    overview: { tasks_total: number; tasks_done: number; follow_total: number; follow_done: number; actual_seconds: number };
    days: DayStat[];
    finance: { income: number; expense: number; entries: FinanceEntry[] };
    routine_items: RoutineItemStat[];
    meals: { total: number; done: number };
};

const loading = ref(true);
const today = toJalaali(new Date());
const selected = ref({ jy: today.jy, jm: today.jm });
const report = ref<ReportPayload | null>(null);

const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const iconMap: Record<string, string> = { briefcase: 'M10 6h4M5 9h14v10H5zM8 9V7a2 2 0 012-2h4a2 2 0 012 2v2', activity: 'M22 12h-4l-3 8-6-16-3 8H2', leaf: 'M5 21c8 0 14-6 14-14V4h-3C8 4 4 8 4 16c0 2 1 4 1 5z', book: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v20H6.5A2.5 2.5 0 014 19.5z', home: 'M3 11l9-8 9 8v10H3z', target: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 18a6 6 0 100-12 6 6 0 000 12zM12 14a2 2 0 100-4 2 2 0 000 4z', calendar: 'M7 3v4M17 3v4M4 9h16M5 5h14v16H5z', clock: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6v6l4 2', star: 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.9 6.4 21.2 7.5 15 3 10.6l6.2-.9z', heart: 'M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 000-7.8z', wallet: 'M3 7h15a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM16 12h3', cart: 'M4 6h2l2 11h11l2-8H7M9 21a1 1 0 100-2 1 1 0 000 2zM18 21a1 1 0 100-2 1 1 0 000 2z', code: 'M8 9l-4 3 4 3M16 9l4 3-4 3M14 5l-4 14', pen: 'M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16zM13 7l4 4', phone: 'M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7l.5 2.6a2 2 0 01-.6 1.9L7.8 9.4a16 16 0 006.8 6.8l1.2-1.2a2 2 0 011.9-.6l2.6.5a2 2 0 011.7 2z', users: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8', music: 'M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zM21 16a3 3 0 11-6 0 3 3 0 016 0z', camera: 'M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2zM12 17a4 4 0 100-8 4 4 0 000 8z', plane: 'M22 2L11 13M22 2l-7 20-4-9-9-4z', gift: 'M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 100-5C13 2 12 7 12 7z', shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', coffee: 'M17 8h1a4 4 0 010 8h-1M3 8h14v5a6 6 0 01-6 6H9a6 6 0 01-6-6zM6 2v3M10 2v3M14 2v3', sparkles: 'M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5zM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8zM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8z', map: 'M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3zM9 3v15M15 6v15', folder: 'M3 5h7l2 3h9v11H3z', zap: 'M13 2L3 14h8l-1 8 10-12h-8z', sun: 'M12 18a6 6 0 100-12 6 6 0 000 12zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4', moon: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z', check: 'M20 6L9 17l-5-5', flag: 'M4 22V4h12l-1 4 1 4H4' };

const monthLength = computed(() => jalaaliMonthLength(selected.value.jy, selected.value.jm));
const monthLabel = computed(() => `${monthNames[selected.value.jm - 1]} ${fa(selected.value.jy)}`);
const isCurrentMonth = computed(() => selected.value.jy === today.jy && selected.value.jm === today.jm);
const overview = computed(() => {
    const total = report.value?.overview.tasks_total ?? 0;
    const done = report.value?.overview.tasks_done ?? 0;
    return {
        total,
        done,
        remaining: Math.max(0, total - done),
        percent: total ? Math.round((done / total) * 100) : 0,
        hours: formatDuration(report.value?.overview.actual_seconds ?? 0),
    };
});
const dayMap = computed(() => new Map((report.value?.days ?? []).map((day) => [day.date, day])));
const monthDays = computed(() => Array.from({ length: monthLength.value }, (_, index) => {
    const jd = index + 1;
    const date = gregorianString(selected.value.jy, selected.value.jm, jd);
    const stat = dayMap.value.get(date);
    const taskPercent = stat?.tasks_total ? Math.round((stat.tasks_done / stat.tasks_total) * 100) : 0;
    const routinePercent = stat?.routine_total ? Math.round((stat.routine_done / stat.routine_total) * 100) : 0;
    return { jd, date, stat, taskPercent, routinePercent, isEmpty: !stat || (!stat.tasks_total && !stat.meals_total && !stat.routine_done && !stat.income && !stat.expense) };
}));
const categoryStats = computed(() => (report.value?.category_stats ?? []).map((cat) => {
    const percent = cat.total ? Math.round((cat.done / cat.total) * 100) : 0;
    return {
        ...cat,
        percent,
        remaining: Math.max(0, cat.total - cat.done),
        dash: `${(percent / 100) * 264} 264`,
        hours: formatDuration(cat.actual_seconds),
        iconPath: iconMap[cat.icon] ?? iconMap.briefcase,
        cardBg: gradient(cat.color),
    };
}));
const dailyMaxSeconds = computed(() => Math.max(3600, ...monthDays.value.map((day) => day.stat?.actual_seconds ?? 0)));
const dailyAvgLabel = computed(() => formatDuration(monthDays.value.reduce((sum, day) => sum + (day.stat?.actual_seconds ?? 0), 0) / Math.max(1, monthLength.value)));
const dailyHours = computed(() => monthDays.value.map((day) => {
    const total = day.stat?.actual_seconds ?? 0;
    const height = Math.min(120, Math.max(day.isEmpty ? 0 : 4, (total / dailyMaxSeconds.value) * 120));
    const activeCats = categoryStats.value.filter((cat) => cat.actual_seconds > 0);
    const segments = activeCats.length
        ? activeCats.map((cat) => ({ color: cat.color, px: Math.max(1, height / activeCats.length) }))
        : [{ color: '#EADFC7', px: day.isEmpty ? 2 : height }];
    return { ...day, segments, bg: 'transparent' };
}));
const routineAverage = computed(() => {
    const days = monthDays.value;
    const total = days.reduce((sum, day) => sum + (day.stat?.routine_total ?? 0), 0);
    const done = days.reduce((sum, day) => sum + (day.stat?.routine_done ?? 0), 0);
    return total ? Math.round((done / total) * 100) : 0;
});
const routineStats = computed(() => (report.value?.routine_items ?? []).map((item) => {
    const percent = monthLength.value ? Math.round((item.done_days / monthLength.value) * 100) : 0;
    const color = percent >= 75 ? '#16A34A' : percent >= 50 ? '#FF8A3D' : '#DC2626';
    return { ...item, percent, color, dash: `${(percent / 100) * 264} 264`, cardBg: gradient(color, 120) };
}));
const finance = computed(() => {
    const income = report.value?.finance.income ?? 0;
    const expense = report.value?.finance.expense ?? 0;
    return { income, expense, net: income - expense };
});
const financeGroups = computed(() => {
    const groups = new Map<string, { name: string; color: string; entries: FinanceEntry[]; total: number }>();
    (report.value?.finance.entries ?? []).forEach((entry) => {
        const key = entry.category?.name ?? 'بدون دسته';
        if (!groups.has(key)) groups.set(key, { name: key, color: entry.category?.color ?? '#3A2E1F', entries: [], total: 0 });
        const group = groups.get(key)!;
        group.entries.push(entry);
        group.total += entry.type === 'income' ? entry.amount : -entry.amount;
    });
    return Array.from(groups.values()).map((group) => ({ ...group, iconPath: group.total >= 0 ? financeIcon.wallet : financeIcon.cart }));
});
const overviewDeltaLabel = computed(() => overview.value.percent ? '▲ ماه جاری' : 'بدون داده');
const financeIcon = {
    cart: 'M4 6h2l2 11h11l2-8H7 M9 21a1 1 0 100-2 1 1 0 000 2z M18 21a1 1 0 100-2 1 1 0 000 2z',
    wallet: 'M3 7h15a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z M16 12h3',
};
const sleepChart = computed(() => {
    const points = monthDays.value.map((day) => {
        const wake = minutesFromClock(day.stat?.wake_time ?? null);
        const sleep = sleepAxisMinutes(day.stat?.sleep_time ?? null);
        return { day: day.jd, wake, sleep };
    });
    const allValues = points.flatMap((point) => [point.wake, point.sleep]).filter((value): value is number => value !== null);
    const min = allValues.length ? Math.min(...allValues, 300) : 300;
    const max = allValues.length ? Math.max(...allValues, 1560) : 1560;
    const pad = 80;
    const minAxis = Math.max(0, min - pad);
    const maxAxis = Math.min(1680, max + pad);
    const width = 900;
    const height = 210;
    const xStep = width / Math.max(1, monthLength.value - 1);
    const yFor = (value: number) => 18 + ((maxAxis - value) / Math.max(1, maxAxis - minAxis)) * (height - 36);
    const toPoint = (day: number, value: number) => ({ x: (day - 1) * xStep, y: yFor(value), value });
    const wakePoints = points.filter((point) => point.wake !== null).map((point) => toPoint(point.day, point.wake!));
    const sleepPoints = points.filter((point) => point.sleep !== null).map((point) => toPoint(point.day, point.sleep!));

    return {
        width,
        height,
        minAxis,
        maxAxis,
        wakePoints,
        sleepPoints,
        wakePath: smoothPath(wakePoints),
        sleepPath: smoothPath(sleepPoints),
        labels: [maxAxis, Math.round((maxAxis + minAxis) / 2), minAxis].map(timeFromAxisMinutes),
        avgWake: averageLabel(wakePoints),
        avgSleep: averageLabel(sleepPoints),
    };
});

async function loadReport() {
    loading.value = true;
    const start = gregorianString(selected.value.jy, selected.value.jm, 1);
    const end = gregorianString(selected.value.jy, selected.value.jm, monthLength.value);
    const { data } = await api.get('/monthly-report', { params: { start, end } });
    report.value = data;
    loading.value = false;
}

function changeMonth(delta: number) {
    let jm = selected.value.jm + delta;
    let jy = selected.value.jy;
    if (jm < 1) { jm = 12; jy -= 1; }
    if (jm > 12) { jm = 1; jy += 1; }
    selected.value = { jy, jm };
}

function goThisMonth() {
    selected.value = { jy: today.jy, jm: today.jm };
}

function gregorianString(jy: number, jm: number, jd: number) {
    const g = toGregorian(jy, jm, jd);
    return `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}`;
}

function fa(value: string | number) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function money(value: number) {
    return `${fa(Math.abs(value).toLocaleString('en-US'))} تومان`;
}

function formatDuration(seconds: number) {
    const rounded = Math.round(seconds);
    const hours = Math.floor(rounded / 3600);
    const minutes = Math.round((rounded % 3600) / 60);
    if (!hours && !minutes) return '۰ دقیقه';
    if (!hours) return `${fa(minutes)} دقیقه`;
    return `${fa(hours)} ساعت و ${fa(minutes)} دقیقه`;
}

function minutesFromClock(value: string | null) {
    if (!value) return null;
    const [hour, minute] = value.split(':').map(Number);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
    return hour * 60 + minute;
}

function sleepAxisMinutes(value: string | null) {
    const minutes = minutesFromClock(value);
    if (minutes === null) return null;
    return minutes < 720 ? minutes + 1440 : minutes;
}

function timeFromAxisMinutes(value: number) {
    const normalized = ((Math.round(value) % 1440) + 1440) % 1440;
    const hour = Math.floor(normalized / 60);
    const minute = normalized % 60;
    return fa(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`);
}

function averageLabel(points: { value: number }[]) {
    if (!points.length) return 'ثبت نشده';
    const avg = points.reduce((sum, point) => sum + point.value, 0) / points.length;
    return timeFromAxisMinutes(avg);
}

function smoothPath(points: { x: number; y: number }[]) {
    if (!points.length) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;
    return points.reduce((path, point, index, items) => {
        if (index === 0) return `M ${point.x} ${point.y}`;
        const prev = items[index - 1];
        const control = (point.x - prev.x) / 2;
        return `${path} C ${prev.x + control} ${prev.y}, ${point.x - control} ${point.y}, ${point.x} ${point.y}`;
    }, '');
}

function shade(hex: string, amount: number) {
    const color = hex.replace('#', '');
    const num = parseInt(color, 16);
    const r = Math.max(0, Math.min(255, (num >> 16) + amount));
    const g = Math.max(0, Math.min(255, ((num >> 8) & 255) + amount));
    const b = Math.max(0, Math.min(255, (num & 255) + amount));
    return `#${[r, g, b].map((item) => item.toString(16).padStart(2, '0')).join('')}`;
}

function gradient(hex: string, angle = 135) {
    return `linear-gradient(${angle}deg, ${shade(hex, 40)}, ${shade(hex, -36)})`;
}

function dayLabel(date: string) {
    const j = toJalaali(new Date(`${date}T12:00:00`));
    return `${fa(j.jd)} ${monthNames[j.jm - 1]}`;
}

onMounted(loadReport);
watch(selected, loadReport);
</script>

<template>
    <div class="report-shell" dir="rtl">
        <div class="report-page">
            <i class="tape tape-yellow"></i>
            <i class="tape tape-cyan"></i>

            <header class="report-header">
                <div>
                    <div class="page-title">گزارش ماهانه</div>
                    <div class="page-subtitle">مرور کامل عملکردت در طول ماه</div>
                </div>
                <div class="top-actions">
                    <button class="icon-btn yellow" @click="changeMonth(-1)" aria-label="ماه قبل">
                        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
                    </button>
                    <div class="month-chip">{{ monthLabel }}</div>
                    <button class="icon-btn cyan" @click="changeMonth(1)" aria-label="ماه بعد">
                        <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"></path></svg>
                    </button>
                    <button v-if="!isCurrentMonth" class="today-btn" @click="goThisMonth">ماه جاری</button>
                    <AppMenu />
                </div>
            </header>

            <main v-if="!loading">
                <section class="insight">
                    گزارش {{ monthLabel }} با {{ fa(monthLength) }} روز کامل نمایش داده شده؛ روزهای بدون ثبت، خالی مانده‌اند تا تکمیل‌نشده‌ها مشخص باشند.
                </section>

                <section class="overview-grid">
                    <div class="stat pink"><small>کل وظایف ماه</small><strong>{{ fa(overview.total) }}</strong></div>
                    <div class="stat green"><small>انجام‌شده</small><strong>{{ fa(overview.done) }}</strong></div>
                    <div class="stat orange"><small>باقی‌مانده</small><strong>{{ fa(overview.remaining) }}</strong></div>
                    <div class="stat purple"><small>پیشرفت ماه</small><strong>{{ fa(overview.percent) }}٪</strong><em>{{ overviewDeltaLabel }}</em></div>
                </section>

                <section class="section-title"><b></b><h2>پیشرفت به تفکیک دسته</h2></section>
                <section class="category-grid">
                    <article v-for="cat in categoryStats" :key="cat.id" class="category-card" :style="{ background: cat.cardBg }">
                        <div class="ring">
                            <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="42"></circle><circle cx="50" cy="50" r="42" :stroke-dasharray="cat.dash"></circle></svg>
                            <span><svg viewBox="0 0 24 24"><path :d="cat.iconPath"></path></svg></span>
                        </div>
                        <strong>{{ cat.name }}</strong>
                        <b>{{ fa(cat.percent) }}٪</b>
                        <small>{{ fa(cat.done) }} از {{ fa(cat.total) }} انجام</small>
                        <small>{{ fa(cat.remaining) }} باقی‌مانده</small>
                        <em>{{ cat.hours }}</em>
                    </article>
                </section>

                <section class="section-title blue"><b></b><h2>ساعات کار روزانه به تفکیک بخش</h2></section>
                <div class="section-note">میانگین روزانه: {{ dailyAvgLabel }} · هر ستون یک روز از ماه است</div>
                <section class="work-chart">
                    <div v-for="day in dailyHours" :key="day.date" class="day-bar" :class="{ empty: day.isEmpty }" :style="{ background: day.bg }">
                        <div class="bar-stack">
                            <span v-for="(segment, index) in day.segments" :key="index" :style="{ height: `${segment.px}px`, background: segment.color }"></span>
                        </div>
                        <small>{{ fa(day.jd) }}</small>
                    </div>
                </section>
                <div class="legend-row">
                    <span v-for="cat in categoryStats" :key="cat.id"><i :style="{ background: cat.color }"></i>{{ cat.name }}</span>
                </div>

                <section class="section-title orange"><b></b><h2>نمای کلی روزهای ماه</h2></section>
                <section class="month-overview">
                    <article v-for="day in monthDays" :key="day.date" class="day-tile" :class="{ empty: day.isEmpty }">
                        <div class="day-num">{{ fa(day.jd) }}</div>
                        <div class="mini-rings">
                            <span class="task-ring" :style="{ '--pct': `${day.taskPercent * 3.6}deg` }">{{ fa(day.taskPercent) }}</span>
                            <span class="routine-ring" :style="{ '--pct': `${day.routinePercent * 3.6}deg` }">{{ fa(day.routinePercent) }}</span>
                        </div>
                        <strong>{{ day.isEmpty ? 'خالی' : formatDuration(day.stat?.actual_seconds ?? 0) }}</strong>
                        <small>{{ fa(day.stat?.tasks_done ?? 0) }} از {{ fa(day.stat?.tasks_total ?? 0) }} کار</small>
                    </article>
                </section>

                <section class="section-title orange"><b></b><h2>وعده‌ها و روتین‌های تکراری</h2></section>
                <section class="routine-card-grid">
                    <article v-for="item in routineStats" :key="item.id" class="routine-stat" :style="{ background: item.cardBg }">
                        <div class="small-ring">
                            <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="42"></circle><circle cx="50" cy="50" r="42" :stroke-dasharray="item.dash"></circle></svg>
                            <span>{{ fa(item.percent) }}٪</span>
                        </div>
                        <div><strong>{{ item.title }}</strong><small>{{ fa(item.done_days) }} از {{ fa(monthLength) }} روز رعایت شده</small></div>
                    </article>
                </section>

                <section class="section-title sleep-title"><b></b><h2>نمودار خواب و بیداری</h2></section>
                <section class="sleep-chart-card">
                    <div class="sleep-summary">
                        <span><i class="wake-dot"></i>میانگین بیداری: <b>{{ sleepChart.avgWake }}</b></span>
                        <span><i class="sleep-dot"></i>میانگین خواب: <b>{{ sleepChart.avgSleep }}</b></span>
                    </div>
                    <div class="sleep-chart-scroll">
                        <svg :viewBox="`0 0 ${sleepChart.width} ${sleepChart.height}`" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="wakeLine" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#FFD93D" />
                                    <stop offset="100%" stop-color="#FF8A3D" />
                                </linearGradient>
                                <linearGradient id="sleepLine" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0%" stop-color="#2563EB" />
                                    <stop offset="100%" stop-color="#9B5DE5" />
                                </linearGradient>
                            </defs>
                            <g class="sleep-grid">
                                <line v-for="index in 5" :key="`grid-${index}`" x1="0" :x2="sleepChart.width" :y1="index * 35" :y2="index * 35"></line>
                            </g>
                            <path v-if="sleepChart.sleepPath" class="sleep-area" :d="`${sleepChart.sleepPath} L ${sleepChart.width} ${sleepChart.height - 12} L 0 ${sleepChart.height - 12} Z`"></path>
                            <path v-if="sleepChart.wakePath" class="wake-path" :d="sleepChart.wakePath"></path>
                            <path v-if="sleepChart.sleepPath" class="sleep-path" :d="sleepChart.sleepPath"></path>
                            <g>
                                <circle v-for="point in sleepChart.wakePoints" :key="`wake-${point.x}`" class="wake-point" :cx="point.x" :cy="point.y" r="4"></circle>
                                <circle v-for="point in sleepChart.sleepPoints" :key="`sleep-${point.x}`" class="sleep-point" :cx="point.x" :cy="point.y" r="4"></circle>
                            </g>
                        </svg>
                        <div class="sleep-days" :style="{ '--days': monthLength }">
                            <span v-for="day in monthDays" :key="`sleep-day-${day.date}`">{{ fa(day.jd) }}</span>
                        </div>
                    </div>
                    <div class="sleep-axis">
                        <span v-for="label in sleepChart.labels" :key="label">{{ label }}</span>
                    </div>
                </section>

                <section class="section-title green"><b></b><h2>گزارش مالی ماه</h2></section>
                <section class="finance-cards">
                    <div class="income-card"><small>کل ورودی</small><strong>{{ money(finance.income) }}</strong></div>
                    <div class="expense-card"><small>کل خروجی</small><strong>{{ money(finance.expense) }}</strong></div>
                    <div class="net-card"><small>جمع خالص کل ماه</small><strong :class="{ neg: finance.net < 0 }">{{ finance.net < 0 ? '-' : '' }}{{ money(finance.net) }}</strong></div>
                </section>
                <section class="finance-list">
                    <article v-for="group in financeGroups" :key="group.name" class="finance-group">
                        <header :style="{ background: gradient(group.color, 120) }">
                            <div><i><svg viewBox="0 0 24 24"><path :d="group.iconPath"></path></svg></i><strong>{{ group.name }}</strong></div>
                            <span>{{ group.total < 0 ? '-' : '+ ' }}{{ money(group.total) }}</span>
                        </header>
                        <div class="finance-entry-wrap">
                            <div v-for="entry in group.entries.slice(0, 4)" :key="entry.id" class="finance-entry">
                                <i :class="entry.type"></i><span>{{ entry.title }}</span><small>{{ dayLabel(entry.expense_date) }}</small><b :class="entry.type">{{ entry.type === 'income' ? '+ ' : '- ' }}{{ money(entry.amount) }}</b>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="section-title pink-title"><b></b><h2>روتین روزانه — نمای کلی ماه</h2></section>
                <section class="routine-month">
                    <div class="routine-overall">
                        <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="42"></circle><circle cx="50" cy="50" r="42" :stroke-dasharray="`${(routineAverage / 100) * 264} 264`"></circle></svg>
                        <strong>{{ fa(routineAverage) }}٪</strong><span>میانگین رعایت روتین</span>
                    </div>
                    <div>
                        <div class="section-note">درصد رعایت روتین در هر روز ماه</div>
                        <div class="heatmap">
                            <span v-for="day in monthDays" :key="day.date" :class="{ empty: day.isEmpty, good: day.routinePercent >= 80, ok: day.routinePercent >= 60 && day.routinePercent < 80, mid: day.routinePercent >= 40 && day.routinePercent < 60, weak: day.routinePercent < 40 && !day.isEmpty }">{{ fa(day.jd) }}</span>
                        </div>
                        <div class="heat-legend"><span><i class="good"></i>عالی</span><span><i class="ok"></i>خوب</span><span><i class="mid"></i>متوسط</span><span><i class="weak"></i>ضعیف</span></div>
                    </div>
                </section>
            </main>

            <main v-else class="loading">در حال ساخت گزارش ماهانه...</main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&family=Lalezar&display=swap');
.report-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 10%,#2e2140 0%,#1a1424 65%);padding:36px 20px 60px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}
.report-page{width:1020px;max-width:100%;margin:auto;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:10px;box-shadow:0 30px 60px rgba(0,0,0,.5);position:relative;padding:34px 34px 44px}
.tape{position:absolute;top:-15px;width:100px;height:31px;opacity:.85;box-shadow:0 3px 6px rgba(0,0,0,.2)}.tape-yellow{right:70px;background:#ffd93d;transform:rotate(-6deg)}.tape-cyan{left:90px;background:#22d3d0;transform:rotate(5deg)}
.report-header,.top-actions{display:flex;align-items:center}.report-header{justify-content:space-between;gap:12px;margin-bottom:22px;flex-wrap:wrap}.page-title{font-family:Lalezar,Vazirmatn,sans-serif;font-size:28px;color:#3a2e1f}.page-subtitle{font-size:12.5px;color:#7a6a4f;margin-top:3px}.top-actions{gap:7px;flex-wrap:wrap}.icon-btn,.today-btn,.month-chip{height:34px;border:2px solid #3a2e1f;border-radius:9px;box-shadow:2px 2px 0 #3a2e1f;font-weight:900}.icon-btn{width:34px;display:grid;place-items:center;cursor:pointer}.icon-btn svg{width:15px;height:15px;fill:none;stroke:#3a2e1f;stroke-width:2.5}.cyan{background:#22d3d0}.yellow{background:#ffd93d}.today-btn{background:#ff6fa5;color:#fff;padding:0 12px;cursor:pointer}.month-chip{background:#fff;display:grid;place-items:center;min-width:130px;padding:0 16px;font-size:13.5px}
.menu-button{width:38px;height:36px;border:2px solid #3a2e1f;border-radius:9px;background:#fff;display:flex;flex-direction:column;justify-content:center;gap:4px;padding:0 8px;box-shadow:2px 2px 0 #3a2e1f;cursor:pointer}.menu-button span{height:3px;background:#3a2e1f;border-radius:3px}.drawer-card{position:absolute;top:84px;left:34px;z-index:5;background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:4px 4px 0 #3a2e1f;padding:10px;display:grid;gap:8px;min-width:220px}.drawer-card button{height:40px;border:0;border-radius:10px;text-align:right;padding:0 12px;font-weight:800;cursor:pointer}.drawer-blue{background:#dbeafe}.drawer-pink{background:#ffe4f0}.drawer-yellow{background:#fef3c7}.drawer-danger{background:#fee2e2;color:#991b1b}
.insight{background:linear-gradient(120deg,#ffd93d,#ff6fa5 55%,#9b5de5);border:2px solid #3a2e1f;border-radius:16px;padding:18px 22px;margin-bottom:22px;box-shadow:4px 4px 0 #3a2e1f;font-weight:800;line-height:1.9}.overview-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:26px}.stat{border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;color:#fff}.stat small{display:block;opacity:.9}.stat strong{display:block;font-size:25px;margin-top:5px}.stat em{font-style:normal;font-size:11px;background:rgba(0,0,0,.16);border-radius:20px;padding:2px 7px}.pink{background:linear-gradient(135deg,#ff9ac1,#d63384)}.green{background:linear-gradient(135deg,#34d399,#0d9488)}.orange{background:linear-gradient(135deg,#ffb55e,#f97316)}.purple{background:linear-gradient(135deg,#c084fc,#7c3aed)}
.section-title{display:flex;align-items:center;gap:8px;margin:22px 0 12px}.section-title b{width:5px;height:20px;border-radius:3px;background:#d63384}.section-title h2{font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;margin:0;font-weight:400}.section-title.blue b{background:#2563eb}.section-title.orange b{background:#ff8a3d}.section-title.green b{background:#16a34a}.pink-title b{background:#d63384}.section-note{font-size:11.5px;color:#7a6a4f;margin:-4px 0 10px}
.category-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:28px}.category-card{border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;text-align:center;color:#fff;display:flex;flex-direction:column;align-items:center}.ring{position:relative;width:70px;height:70px;margin:0 auto 8px;display:grid;place-items:center}.ring>svg{width:70px;height:70px;fill:none;stroke-width:11;display:block}.ring circle:first-child{stroke:rgba(255,255,255,.35)}.ring circle:last-child{stroke:#fff;stroke-linecap:round;transform:rotate(-90deg);transform-origin:center}.ring span{position:absolute;top:50%;left:50%;width:36px;height:36px;transform:translate(-50%,-50%);border-radius:50%;background:#fff;display:grid;place-items:center}.ring span svg{width:18px;height:18px;display:block;fill:none;stroke:#3a2e1f;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;overflow:visible}.category-card strong,.category-card b,.category-card small,.category-card em{display:block}.category-card strong{font-size:13px;font-weight:800}.category-card b{font-size:17px;margin-top:2px}.category-card small{opacity:.9;font-size:10.5px;margin-top:5px}.category-card em{width:100%;margin-top:6px;padding-top:6px;border-top:1px dashed rgba(255,255,255,.4);font-size:10.5px;font-weight:700;font-style:normal;color:#fff}
.work-chart{background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:18px 16px 14px;box-shadow:3px 3px 0 #3a2e1f;margin-bottom:10px;overflow-x:auto}.work-chart{display:flex;align-items:flex-end;gap:5px;height:184px}.day-bar{height:150px;min-width:10px;flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:5px;border-radius:4px}.bar-stack{display:flex;flex-direction:column-reverse;width:12px;border-radius:3px 3px 0 0;overflow:hidden}.bar-stack span{width:12px}.day-bar.empty .bar-stack{opacity:.55}.day-bar small{font-size:8.5px;color:#9a8b6a}.legend-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 28px}.legend-row span{display:flex;align-items:center;gap:5px;font-size:11px;color:#4b3b22}.legend-row i{width:9px;height:9px;border-radius:2px}
.month-overview{background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:28px}.day-tile{min-height:112px;background:linear-gradient(180deg,#fff,#fffbf0);border:2px solid #3a2e1f;border-radius:12px;padding:9px;box-shadow:2px 2px 0 #3a2e1f;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:space-between}.day-tile.empty{background:#f6ecd7;color:#9a8b6a;border-style:dashed;box-shadow:none}.day-num{width:28px;height:28px;border-radius:9px;background:#ffd93d;border:2px solid #3a2e1f;display:grid;place-items:center;font-weight:900}.day-tile.empty .day-num{background:#eadfc7}.mini-rings{display:flex;gap:5px}.mini-rings span{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:8px;font-weight:900;color:#3a2e1f;border:1px solid rgba(58,46,31,.2)}.task-ring{background:conic-gradient(#2563eb var(--pct),#f0ebd8 0)}.routine-ring{background:conic-gradient(#16a34a var(--pct),#f0ebd8 0)}.day-tile strong{font-size:10px}.day-tile small{font-size:10px;color:#7a6a4f}
.routine-card-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:28px}.routine-stat{border:2px solid #3a2e1f;border-radius:12px;padding:11px 14px;display:flex;align-items:center;gap:12px;box-shadow:3px 3px 0 #3a2e1f;color:#fff}.small-ring{position:relative;width:44px;height:44px;flex-shrink:0}.small-ring svg{width:44px;height:44px;fill:none;stroke-width:14}.small-ring circle:first-child{stroke:rgba(255,255,255,.4)}.small-ring circle:last-child{stroke:#fff;stroke-linecap:round;transform:rotate(-90deg);transform-origin:center}.small-ring span{position:absolute;inset:0;display:grid;place-items:center;font-size:10.5px;font-weight:900}.routine-stat strong{display:block;font-size:12.5px}.routine-stat small{font-size:10.5px;opacity:.9}
.section-title.sleep-title b{background:#9b5de5}.sleep-chart-card{position:relative;background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:14px 16px 12px;box-shadow:3px 3px 0 #3a2e1f;margin-bottom:28px;overflow:hidden}.sleep-chart-card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,217,61,.12),rgba(34,211,208,.08),rgba(155,93,229,.1));pointer-events:none}.sleep-summary{position:relative;z-index:1;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:10px;font-size:11.5px;color:#4b3b22}.sleep-summary span{display:flex;align-items:center;gap:6px;background:#fffbf0;border:1.5px solid #efe3c4;border-radius:999px;padding:5px 9px;font-weight:800}.sleep-summary b{color:#3a2e1f}.sleep-summary i{width:9px;height:9px;border-radius:50%;box-shadow:0 0 0 2px rgba(58,46,31,.12)}.wake-dot{background:#ff8a3d}.sleep-dot{background:#2563eb}.sleep-chart-scroll{position:relative;z-index:1;overflow-x:auto;padding-bottom:2px}.sleep-chart-scroll svg{display:block;width:100%;min-width:760px;height:210px;overflow:visible}.sleep-grid line{stroke:#efe3c4;stroke-width:1;stroke-dasharray:5 7}.wake-path,.sleep-path{fill:none;stroke-width:4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 3px 0 rgba(58,46,31,.12))}.wake-path{stroke:url(#wakeLine)}.sleep-path{stroke:url(#sleepLine)}.sleep-area{fill:rgba(155,93,229,.08);stroke:none}.wake-point,.sleep-point{stroke:#3a2e1f;stroke-width:1.5}.wake-point{fill:#ffd93d}.sleep-point{fill:#9b5de5}.sleep-days{min-width:760px;display:grid;grid-template-columns:repeat(var(--days, 31),1fr);gap:0;margin-top:-4px;color:#9a8b6a;font-size:8.5px;font-weight:900;text-align:center}.sleep-days span{min-width:0}.sleep-axis{position:absolute;left:14px;top:58px;bottom:34px;z-index:2;display:flex;flex-direction:column;justify-content:space-between;color:#7a6a4f;font-size:10px;font-weight:900;pointer-events:none}
.finance-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}.finance-cards div{border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;color:#fff}.finance-cards small,.finance-cards strong{display:block}.finance-cards small{font-size:11px;opacity:.9}.finance-cards strong{font-size:19px;margin-top:5px}.income-card{background:linear-gradient(135deg,#34d399,#059669)}.expense-card{background:linear-gradient(135deg,#f87171,#b91c1c)}.net-card{background:linear-gradient(135deg,#3a2e1f,#1a1424)}.net-card small{color:#c9bfa8}.net-card .neg{color:#f87171}.finance-list{display:flex;flex-direction:column;gap:12px;margin-bottom:10px}.finance-group{background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:3px 3px 0 #3a2e1f;overflow:hidden}.finance-group header{display:flex;align-items:center;justify-content:space-between;color:#fff;padding:12px 16px;font-weight:900}.finance-group header div{display:flex;align-items:center;gap:9px}.finance-group header i{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.3);display:grid;place-items:center;flex-shrink:0}.finance-group header svg{width:15px;height:15px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.finance-group header strong{font-size:13.5px}.finance-group header span{font-size:12.5px;background:rgba(0,0,0,.15);padding:3px 10px;border-radius:20px}.finance-entry-wrap{padding:12px 16px 8px}.finance-entry{display:grid;grid-template-columns:auto 1fr auto auto;gap:9px;align-items:center;background:#fffbf0;border-radius:9px;padding:7px 10px;font-size:12px;margin-bottom:6px}.finance-entry i{width:8px;height:8px;border-radius:50%}.finance-entry i.income{background:#16a34a}.finance-entry i.expense{background:#dc2626}.finance-entry small{color:#9a8b6a}.finance-entry b{white-space:nowrap;font-size:12.5px;min-width:110px;text-align:left}.finance-entry b.income{color:#16a34a}.finance-entry b.expense{color:#dc2626}
.routine-month{display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:center;background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:18px;box-shadow:3px 3px 0 #3a2e1f}.routine-overall{text-align:center;min-width:116px}.routine-overall svg{width:92px;height:92px;fill:none;stroke-width:10}.routine-overall circle:first-child{stroke:#f0ebd8}.routine-overall circle:last-child{stroke:#d63384;stroke-linecap:round;transform:rotate(-90deg);transform-origin:center}.routine-overall strong{display:block;font-family:Lalezar,Vazirmatn,sans-serif;font-size:19px;color:#3a2e1f}.routine-overall span{font-size:10.5px;color:#7a6a4f}.heatmap{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px}.heatmap span{width:20px;height:20px;border-radius:5px;background:#efe3c4;color:#7a6a4f;display:grid;place-items:center;font-size:8px;font-weight:800;border:1px solid rgba(58,46,31,.15)}.heatmap span.good{background:#16a34a;color:#fff}.heatmap span.ok{background:#86efac;color:#3a2e1f}.heatmap span.mid{background:#ffd93d;color:#3a2e1f}.heatmap span.weak{background:#fca5a5;color:#3a2e1f}.heat-legend{display:flex;align-items:center;gap:14px;flex-wrap:wrap;font-size:10.5px;color:#7a6a4f}.heat-legend span{display:flex;align-items:center;gap:5px}.heat-legend i{width:10px;height:10px;border-radius:3px;background:#efe3c4}.heat-legend i.good{background:#16a34a}.heat-legend i.ok{background:#86efac}.heat-legend i.mid{background:#ffd93d}.heat-legend i.weak{background:#fca5a5}.loading{min-height:360px;display:grid;place-items:center;font-weight:900}
@media (max-width:860px){.report-page{padding:24px 16px}.report-header{align-items:flex-start}.overview-grid,.category-grid,.month-overview,.finance-cards{grid-template-columns:1fr 1fr}.category-grid{grid-template-columns:repeat(2,1fr)}.routine-card-grid,.routine-month{grid-template-columns:1fr}.drawer-card{left:16px;top:78px}}
@media (max-width:520px){.overview-grid,.finance-cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.overview-grid .stat,.finance-cards div{min-width:0;padding:11px 10px;border-radius:12px;box-shadow:2px 2px 0 #3a2e1f}.overview-grid .stat small,.finance-cards small{font-size:9.5px}.overview-grid .stat strong{font-size:20px;line-height:1.35}.overview-grid .stat em{font-size:9px}.finance-cards strong{font-size:clamp(10px,3.1vw,13px);line-height:1.65;overflow-wrap:anywhere;word-break:break-word}.finance-cards .net-card{grid-column:1/-1}.finance-entry b,.finance-group header span{min-width:0;white-space:normal;overflow-wrap:anywhere;word-break:break-word;font-size:10.5px}.day-tile{min-height:116px}.month-chip{min-width:118px}.report-shell{padding:20px 10px 40px}}
</style>
