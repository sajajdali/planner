<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type Category = { id: number; name: string; color: string; is_default: boolean };
type ReportItem = { id: number; description: string; duration_minutes: number; category: Category };
type Report = { id: number; report_date: string; start_time: string; end_time: string; ends_next_day: boolean; duration_minutes: number; description: string; category: Category; items: ReportItem[] };
type Slot = { index: number; start_time: string; end_time: string; ends_next_day: boolean };
type CategoryStat = Category & { minutes: number; percent: number };
type Summary = { report_count: number; total_minutes: number; coverage_percent: number; categories: CategoryStat[] };

const today = localDate(new Date());
const selectedDate = ref(today);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const categories = ref<Category[]>([]);
const reports = ref<Report[]>([]);
const summary = ref<Summary>({ report_count: 0, total_minutes: 0, coverage_percent: 0, categories: [] });
const showForm = ref(false);
const showCategoryForm = ref(false);
const newCategory = reactive({ name: '', color: '#14B8A6' });
const form = reactive({ start_time: '08:00', end_time: '12:00', items: [] as { category_id: number; description: string; duration_minutes: number }[] });
const slots = ref<Slot[]>([]);
const wakeTime = ref('06:00');
const wakeTimeSource = ref<'routine' | 'default'>('default');

const persianDate = computed(() => new Intl.DateTimeFormat('fa-IR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(`${selectedDate.value}T12:00:00`)));
const activeCategoryStats = computed(() => summary.value.categories.filter((item) => item.minutes > 0));
const selectedCategory = computed(() => categories.value.find((item) => item.id === form.items[0]?.category_id));
const formDuration = computed(() => {
    const [startHour, startMinute] = form.start_time.split(':').map(Number);
    const [endHour, endMinute] = form.end_time.split(':').map(Number);
    const start = startHour * 60 + startMinute;
    let end = endHour * 60 + endMinute;
    if (end <= start) end += 1440;
    return end - start;
});
const activityMinutes = computed(() => form.items.reduce((sum, item) => sum + Number(item.duration_minutes || 0), 0));

onMounted(load);
watch(selectedDate, load);

function localDate(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function fa(value: number | string) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function durationText(minutes: number) {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (!hours) return `${fa(rest)} دقیقه`;
    return rest ? `${fa(hours)} ساعت و ${fa(rest)} دقیقه` : `${fa(hours)} ساعت`;
}

function clockMinutes(value: string) {
    const [hour, minute] = value.split(':').map(Number);
    return hour * 60 + minute;
}

function slotReports(slot: Slot) {
    return reports.value.filter((report) => {
        const start = clockMinutes(slot.start_time);
        const end = clockMinutes(slot.end_time) + (slot.ends_next_day ? 1440 : 0);
        let reportStart = clockMinutes(report.start_time);
        if (slot.ends_next_day && reportStart < start) reportStart += 1440;
        return reportStart >= start && reportStart < end;
    });
}

function slotLabel(slot: Slot) {
    return `${fa(slot.start_time)} تا ${fa(slot.end_time)}${slot.ends_next_day ? ' روز بعد' : ''}`;
}

function moveDate(days: number) {
    const date = new Date(`${selectedDate.value}T12:00:00`);
    date.setDate(date.getDate() + days);
    selectedDate.value = localDate(date);
}

function setStartFromSlot(slot: Slot) {
    form.start_time = slot.start_time;
    form.end_time = slot.end_time;
    showForm.value = true;
    window.setTimeout(() => document.querySelector<HTMLTextAreaElement>('.report-description')?.focus(), 50);
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/four-hour-reports', { params: { date: selectedDate.value } });
        categories.value = data.categories ?? [];
        reports.value = data.reports ?? [];
        summary.value = data.summary ?? summary.value;
        slots.value = data.slots ?? [];
        wakeTime.value = data.wake_time ?? '06:00';
        wakeTimeSource.value = data.wake_time_source ?? 'default';
        if (slots.value.length) {
            form.start_time = slots.value[0].start_time;
            form.end_time = slots.value[0].end_time;
        }
        if (!form.items.length && categories.value.length) addActivity();
    } catch {
        error.value = 'گزارش‌های این روز بارگذاری نشدند.';
    } finally {
        loading.value = false;
    }
}

async function saveReport() {
    if (!form.items.length || form.items.some((item) => !item.category_id || !item.description.trim() || item.duration_minutes < 1) || formDuration.value <= 0 || formDuration.value > 240 || activityMinutes.value > formDuration.value) return;
    saving.value = true;
    error.value = '';
    try {
        await api.post('/four-hour-reports', {
            start_time: form.start_time,
            end_time: form.end_time,
            ends_next_day: form.end_time <= form.start_time,
            report_date: selectedDate.value,
            items: form.items.map((item) => ({ ...item, description: item.description.trim() })),
        });
        form.items = [];
        addActivity();
        showForm.value = false;
        await load();
    } catch (requestError: any) {
        error.value = requestError?.response?.data?.message || 'ثبت گزارش انجام نشد.';
    } finally {
        saving.value = false;
    }
}

async function deleteReport(report: Report) {
    await api.delete(`/four-hour-reports/${report.id}`);
    await load();
}

async function addCategory() {
    if (!newCategory.name.trim()) return;
    error.value = '';
    try {
        const { data } = await api.post('/four-hour-report-categories', { name: newCategory.name.trim(), color: newCategory.color });
        categories.value.push(data);
        if (form.items.length) form.items[form.items.length - 1].category_id = data.id;
        newCategory.name = '';
        showCategoryForm.value = false;
    } catch (requestError: any) {
        error.value = requestError?.response?.data?.message || 'افزودن دسته انجام نشد.';
    }
}

function addActivity() {
    form.items.push({ category_id: categories.value[0]?.id ?? 0, description: '', duration_minutes: 60 });
}

function removeActivity(index: number) {
    if (form.items.length === 1) {
        form.items[0].description = '';
        return;
    }
    form.items.splice(index, 1);
}
</script>

<template>
    <div class="hourly-shell" dir="rtl">
        <main class="hourly-page">
            <header class="page-head">
                <div class="title-wrap"><i>۴</i><div><span>ردیابی آگاهانهٔ روز</span><h1>گزارش ۴ ساعته</h1><p>هر چهار ساعت مکث کن و بنویس زمانت را صرف چه چیزی کردی.</p></div></div>
                <AppMenu />
            </header>

            <section class="date-navigator">
                <button type="button" @click="moveDate(1)">‹</button>
                <div><strong>{{ persianDate }}</strong><input v-model="selectedDate" type="date" /></div>
                <button type="button" @click="moveDate(-1)">›</button>
                <button v-if="selectedDate !== today" type="button" class="today-btn" @click="selectedDate = today">امروز</button>
            </section>

            <section v-if="!loading" class="rhythm-board">
                <header>
                    <div><span>ریتم روز شما</span><h2>چهار ایستگاه برای مرور روز</h2></div>
                    <p>شروع از <b>{{ fa(wakeTime) }}</b> · {{ wakeTimeSource === 'routine' ? 'طبق ساعت بیداری' : 'زمان پیش‌فرض' }}</p>
                </header>
                <div class="rhythm-cards">
                    <button
                        v-for="slot in slots"
                        :key="`rhythm-${slot.index}`"
                        type="button"
                        :class="[`rhythm-${slot.index}`, { complete: slotReports(slot).length }]"
                        @click="setStartFromSlot(slot)"
                    >
                        <span class="rhythm-number">{{ fa(slot.index) }}</span>
                        <i>{{ ['☀', '◐', '◒', '☾'][slot.index - 1] }}</i>
                        <strong>{{ fa(slot.start_time) }} <small>تا</small> {{ fa(slot.end_time) }}</strong>
                        <em v-if="slotReports(slot).length">✓ {{ fa(slotReports(slot).length) }} گزارش ثبت شده</em>
                        <em v-else>＋ ثبت گزارش این بازه</em>
                    </button>
                </div>
            </section>

            <div v-if="error" class="error-box">{{ error }}</div>
            <div v-if="loading" class="loading-box">در حال آماده‌سازی گزارش روز...</div>
            <template v-else>
                <section class="stats-grid">
                    <article class="stat-main"><div><small>زمان ثبت‌شده</small><strong>{{ durationText(summary.total_minutes) }}</strong><span>{{ fa(summary.report_count) }} گزارش در این روز</span></div><div class="coverage-ring" :style="{ '--p': `${summary.coverage_percent * 3.6}deg` }"><b>{{ fa(summary.coverage_percent) }}٪</b><small>از روز</small></div></article>
                    <article class="category-chart"><header><strong>زمان بر اساس دسته</strong><span v-if="!activeCategoryStats.length">هنوز آماری نیست</span></header><div class="chart-bar"><i v-for="item in activeCategoryStats" :key="item.id" :style="{ width: `${item.percent}%`, background: item.color }"></i></div><div class="chart-legend"><span v-for="item in activeCategoryStats" :key="item.id"><i :style="{ background: item.color }"></i>{{ item.name }} <b>{{ durationText(item.minutes) }}</b></span></div></article>
                </section>

                <section class="action-row">
                    <button type="button" class="add-report" @click="showForm = !showForm"><span>＋</span>{{ showForm ? 'بستن فرم' : 'ثبت گزارش جدید' }}</button>
                    <button type="button" class="add-category" @click="showCategoryForm = !showCategoryForm">＋ دسته‌بندی جدید</button>
                </section>

                <section v-if="showCategoryForm" class="category-form">
                    <input v-model="newCategory.name" maxlength="50" placeholder="نام دسته‌بندی..." @keydown.enter.prevent="addCategory" />
                    <label>رنگ <input v-model="newCategory.color" type="color" /></label>
                    <button type="button" @click="addCategory">افزودن</button>
                </section>

                <section v-if="showForm" class="report-form" :style="{ '--accent': selectedCategory?.color || '#2563EB' }">
                    <header><div><span>گزارش تازه</span><h2>در این بازه چه کار کردی؟</h2></div><strong>{{ durationText(formDuration) }}</strong></header>
                    <div class="time-fields"><label>از ساعت<input v-model="form.start_time" type="time" /></label><span>←</span><label>تا ساعت<input v-model="form.end_time" type="time" /></label></div>
                    <div class="activity-editor">
                        <article v-for="(item, index) in form.items" :key="index" :style="{ '--item-color': categories.find((category) => category.id === item.category_id)?.color || '#2563EB' }">
                            <header><strong>فعالیت {{ fa(index + 1) }}</strong><button type="button" title="حذف فعالیت" @click="removeActivity(index)">×</button></header>
                            <textarea v-model="item.description" class="report-description" maxlength="1000" rows="2" placeholder="چه کاری انجام دادی؟"></textarea>
                            <div class="activity-meta">
                                <label>دسته‌بندی<select v-model="item.category_id"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                                <label>مدت فعالیت<div><input v-model.number="item.duration_minutes" type="number" min="1" max="240" /><span>دقیقه</span></div></label>
                            </div>
                        </article>
                        <button type="button" class="add-activity" @click="addActivity">＋ افزودن یک فعالیت دیگر</button>
                    </div>
                    <p v-if="formDuration > 240" class="form-warning">هر گزارش حداکثر می‌تواند ۴ ساعت باشد.</p>
                    <p v-if="activityMinutes > formDuration" class="form-warning">جمع زمان فعالیت‌ها از بازهٔ گزارش بیشتر شده است.</p>
                    <footer><span>تاریخ: {{ persianDate }} · جمع فعالیت‌ها: {{ durationText(activityMinutes) }}</span><button type="button" :disabled="saving || form.items.some((item) => !item.description.trim()) || formDuration <= 0 || formDuration > 240 || activityMinutes > formDuration" @click="saveReport">{{ saving ? 'در حال ثبت...' : 'ثبت گزارش' }}</button></footer>
                </section>

                <section class="timeline-section">
                    <header><div><span>۴ بازه از ساعت بیداری</span><h2>گزارش‌های {{ persianDate }}</h2><small>شروع از {{ fa(wakeTime) }} — {{ wakeTimeSource === 'routine' ? 'براساس ساعت بیداری ثبت‌شده' : 'ساعت پیش‌فرض' }}</small></div><b>{{ fa(reports.length) }} مورد</b></header>
                    <div class="timeline">
                        <article v-for="slot in slots" :key="slot.index" class="time-slot" :class="{ empty: !slotReports(slot).length }">
                            <div class="slot-time"><em>بازه {{ fa(slot.index) }}</em><strong>{{ slotLabel(slot) }}</strong><span></span></div>
                            <div class="slot-content">
                                <div v-for="report in slotReports(slot)" :key="report.id" class="report-card" :style="{ '--c': report.items[0]?.category.color || report.category.color }">
                                    <header><time>{{ fa(report.start_time) }} — {{ fa(report.end_time) }}{{ report.ends_next_day ? ' روز بعد' : '' }}</time><b>{{ durationText(report.duration_minutes) }}</b></header>
                                    <div class="report-activities">
                                        <div v-for="item in report.items" :key="item.id" :style="{ '--item-color': item.category.color }"><span><i></i>{{ item.category.name }}</span><p>{{ item.description }}</p><small>{{ durationText(item.duration_minutes) }}</small></div>
                                    </div>
                                    <button type="button" title="حذف گزارش" @click="deleteReport(report)">×</button>
                                </div>
                                <button v-if="!slotReports(slot).length" type="button" class="empty-slot" @click="setStartFromSlot(slot)"><span>＋</span>برای این بازه گزارشی ثبت نشده؛ اضافه کن</button>
                            </div>
                        </article>
                    </div>
                </section>
            </template>
        </main>
    </div>
</template>

<style scoped>
.hourly-shell{min-height:100vh;padding:38px 18px 90px;background:#14231f;background-image:radial-gradient(circle at 15% 15%,#1d3932 0,transparent 34%),radial-gradient(circle at 85% 20%,#2b2440 0,transparent 32%);font-family:Vazirmatn,sans-serif;color:#2f352f}.hourly-page{width:min(1120px,100%);margin:auto;padding:28px;border:1px solid rgba(255,255,255,.25);border-radius:28px;background:#f7f5ee;box-shadow:0 30px 70px rgba(0,0,0,.42)}.page-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.title-wrap{display:flex;align-items:center;gap:14px}.title-wrap>i{display:grid;width:54px;height:54px;place-items:center;border:3px solid #263a34;border-radius:18px;background:#b8f34a;color:#263a34;font-family:Lalezar,sans-serif;font-size:31px;font-style:normal;box-shadow:4px 4px 0 #263a34;transform:rotate(-4deg)}.title-wrap span{color:#6e7c75;font-size:10px;font-weight:900}.title-wrap h1{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:31px;line-height:1.2}.title-wrap p{margin:2px 0 0;color:#78847e;font-size:11px;font-weight:700}.date-navigator{position:relative;display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px;padding:13px;border:1.5px solid #d8d9cf;border-radius:17px;background:#fff}.date-navigator>button{display:grid;width:36px;height:36px;place-items:center;border:0;border-radius:11px;background:#edf0e9;color:#263a34;font-size:25px;cursor:pointer}.date-navigator>div{display:grid;min-width:280px;text-align:center}.date-navigator strong{font-size:14px}.date-navigator input{margin:auto;border:0;background:transparent;color:#859089;font:inherit;font-size:10px}.date-navigator .today-btn{position:absolute;left:12px;width:auto;padding:0 12px;background:#263a34;color:#fff;font-size:10px;font-weight:900}.stats-grid{display:grid;grid-template-columns:340px 1fr;gap:14px;margin-bottom:16px}.stats-grid article{border:1.5px solid #d8d9cf;border-radius:18px;background:#fff}.stat-main{display:flex;align-items:center;justify-content:space-between;padding:18px}.stat-main>div:first-child{display:grid;gap:3px}.stat-main small{color:#859089;font-size:9.5px;font-weight:900}.stat-main strong{font-size:23px}.stat-main span{color:#859089;font-size:10px}.coverage-ring{--p:0deg;display:grid;width:86px;height:86px;place-content:center;border-radius:50%;background:radial-gradient(circle,#fff 58%,transparent 60%),conic-gradient(#6faa36 var(--p),#e9eee4 0);text-align:center}.coverage-ring b{font-size:15px}.coverage-ring small{font-size:8px}.category-chart{padding:16px}.category-chart>header{display:flex;justify-content:space-between;margin-bottom:14px}.category-chart header strong{font-size:12px}.category-chart header span{color:#9aa39e;font-size:10px}.chart-bar{display:flex;height:14px;overflow:hidden;border-radius:999px;background:#edf0e9}.chart-bar i{min-width:2px}.chart-legend{display:flex;flex-wrap:wrap;gap:10px 16px;margin-top:13px}.chart-legend span{display:flex;align-items:center;gap:5px;color:#6e7872;font-size:9.5px}.chart-legend i{width:8px;height:8px;border-radius:3px}.chart-legend b{color:#303a34}.action-row{display:flex;gap:9px;margin-bottom:14px}.action-row button{height:40px;padding:0 14px;border:2px solid #263a34;border-radius:11px;font:inherit;font-size:11px;font-weight:900;cursor:pointer}.add-report{background:#b8f34a;color:#263a34;box-shadow:2px 2px 0 #263a34}.add-report span{font-size:17px}.add-category{background:#fff;color:#627068;border-style:dashed!important}.category-form{display:flex;gap:9px;margin-bottom:14px;padding:12px;border:1.5px dashed #b8c0b7;border-radius:14px;background:#fff}.category-form>input{flex:1}.category-form input{height:38px;border:1.5px solid #d8d9cf;border-radius:9px;padding:0 10px;font:inherit}.category-form label{display:flex;align-items:center;gap:6px;color:#77827b;font-size:10px}.category-form label input{width:44px;padding:3px}.category-form button{border:0;border-radius:9px;padding:0 15px;background:#263a34;color:#fff;font:inherit;font-weight:900}.report-form{margin-bottom:20px;padding:18px;border:2px solid var(--accent);border-radius:19px;background:#fff;box-shadow:4px 4px 0 color-mix(in srgb,var(--accent) 30%,transparent)}.report-form>header{display:flex;align-items:flex-start;justify-content:space-between}.report-form header span{color:var(--accent);font-size:9px;font-weight:900}.report-form h2{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:22px}.report-form header>strong{padding:6px 10px;border-radius:9px;background:color-mix(in srgb,var(--accent) 12%,white);color:var(--accent);font-size:11px}.time-fields{display:flex;align-items:end;gap:10px;margin:14px 0}.time-fields label{display:grid;gap:4px;color:#7a857e;font-size:9px;font-weight:900}.time-fields input{height:38px;border:1.5px solid #d8d9cf;border-radius:9px;padding:0 10px;font:inherit}.time-fields>span{padding-bottom:8px}.category-pills{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px}.category-pills button{display:flex;align-items:center;gap:6px;height:32px;padding:0 10px;border:1.5px solid #d8d9cf;border-radius:999px;background:#fff;color:#66716b;font:inherit;font-size:10px;font-weight:900;cursor:pointer}.category-pills button i{width:9px;height:9px;border-radius:50%;background:var(--c)}.category-pills button.active{border-color:var(--c);background:color-mix(in srgb,var(--c) 12%,white);color:var(--c)}.report-description{box-sizing:border-box;width:100%;resize:vertical;border:1.5px solid #d8d9cf;border-radius:12px;padding:11px;background:#fbfcf9;font:inherit;font-size:12px;line-height:2;outline:0}.report-description:focus{border-color:var(--accent)}.report-form footer{display:flex;align-items:center;justify-content:space-between;margin-top:10px}.report-form footer span{color:#8a948e;font-size:9.5px}.report-form footer button{height:38px;padding:0 17px;border:0;border-radius:10px;background:var(--accent);color:#fff;font:inherit;font-size:11px;font-weight:900;cursor:pointer}.report-form footer button:disabled{opacity:.45}.form-warning,.error-box{color:#b91c1c;font-size:10px;font-weight:900}.error-box,.loading-box{margin-bottom:14px;padding:13px;border-radius:12px;background:#fee2e2;text-align:center}.loading-box{background:#fff;color:#77827b}.timeline-section{padding:18px;border:1.5px solid #d8d9cf;border-radius:20px;background:#fff}.timeline-section>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:17px}.timeline-section header span{color:#6faa36;font-size:9px;font-weight:900}.timeline-section h2{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:22px}.timeline-section header>b{padding:6px 10px;border-radius:9px;background:#edf0e9;font-size:10px}.timeline{display:grid}.time-slot{display:grid;grid-template-columns:125px 1fr;min-height:92px}.slot-time{position:relative;padding:9px 0 0 18px;text-align:left}.slot-time strong{font-size:10px;color:#69756e}.slot-time span{position:absolute;top:27px;left:0;width:10px;height:10px;border:2px solid #6faa36;border-radius:50%;background:#fff}.slot-time::after{content:"";position:absolute;top:39px;bottom:-9px;left:5px;width:2px;background:#e0e7dc}.time-slot:last-child .slot-time::after{display:none}.slot-content{padding:3px 0 14px 13px}.report-card{position:relative;margin-bottom:8px;padding:12px 38px 12px 13px;border-right:4px solid var(--c);border-radius:11px;background:color-mix(in srgb,var(--c) 7%,#fbfcf9)}.report-card header{display:flex;align-items:center;gap:10px}.report-card header span{display:flex;align-items:center;gap:5px;color:var(--c);font-size:10px;font-weight:900}.report-card header span i{width:8px;height:8px;border-radius:3px;background:var(--c)}.report-card time{color:#68736c;font-size:10px;font-weight:900}.report-card header b{margin-right:auto;color:#87918b;font-size:9px}.report-card p{margin:7px 0 0;white-space:pre-wrap;color:#3f4943;font-size:11.5px;line-height:1.9}.report-card>button{position:absolute;top:10px;right:10px;width:22px;height:22px;border:0;border-radius:7px;background:#fff;color:#b5bdb8;cursor:pointer}.empty-slot{width:100%;height:62px;border:1.5px dashed #d8d9cf;border-radius:11px;background:#fbfcf9;color:#9aa39e;font:inherit;font-size:10px;cursor:pointer}.empty-slot span{font-size:15px;color:#6faa36}.empty-slot:hover{border-color:#6faa36;color:#5b694f}
.activity-editor{display:grid;gap:10px}.activity-editor>article{padding:12px;border:1.5px solid color-mix(in srgb,var(--item-color) 45%,#d8d9cf);border-radius:13px;background:color-mix(in srgb,var(--item-color) 5%,white)}.activity-editor>article>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}.activity-editor>article>header strong{color:var(--item-color);font-size:10px}.activity-editor>article>header button{width:24px;height:24px;border:0;border-radius:7px;background:#fff;color:#b4bcb7;cursor:pointer}.activity-meta{display:flex;gap:10px;margin-top:8px}.activity-meta label{display:grid;gap:4px;color:#77827b;font-size:9px;font-weight:900}.activity-meta select,.activity-meta input{height:34px;border:1.5px solid #d8d9cf;border-radius:8px;background:#fff;padding:0 8px;font:inherit;font-size:10px}.activity-meta label>div{display:flex;align-items:center;gap:5px}.activity-meta input{width:70px}.activity-meta label>div span{font-size:9px}.add-activity{height:38px;border:1.5px dashed #8e9a92;border-radius:10px;background:#fbfcf9;color:#657169;font:inherit;font-size:10px;font-weight:900;cursor:pointer}.report-activities{display:grid;gap:6px;margin-top:9px}.report-activities>div{position:relative;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:9px;padding:8px 9px;border-right:3px solid var(--item-color);border-radius:8px;background:#fff}.report-activities span{display:flex;align-items:center;gap:4px;color:var(--item-color);font-size:9px;font-weight:900}.report-activities span i{width:7px;height:7px;border-radius:3px;background:var(--item-color)}.report-activities p{margin:0!important;font-size:10.5px!important;line-height:1.8!important}.report-activities small{color:#8a948e;font-size:8.5px;white-space:nowrap}
.timeline-section>header small{display:block;margin-top:3px;color:#87918b;font-size:9px}.slot-time em{display:block;margin-bottom:2px;color:#6faa36;font-size:8px;font-style:normal;font-weight:900}.slot-time strong{display:block}
.hourly-shell{background-color:#0c211b;background-image:radial-gradient(circle at 10% 8%,rgba(184,243,74,.18),transparent 27%),radial-gradient(circle at 92% 18%,rgba(139,92,246,.18),transparent 29%),linear-gradient(145deg,#0b201a,#172c26 55%,#171f2c)}.hourly-page{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.4);background:linear-gradient(155deg,#fbfaf5 0%,#f3f5ed 52%,#f7f2fb 100%)}.hourly-page::before{content:"";position:absolute;top:-90px;left:-80px;width:230px;height:230px;border-radius:50%;background:rgba(184,243,74,.1);pointer-events:none}.page-head{position:relative;margin:-10px -10px 18px;padding:22px;border-radius:22px;background:linear-gradient(135deg,#203b32,#182c26 62%,#263b34);color:#fff;box-shadow:0 12px 28px rgba(30,55,47,.18)}.page-head::after{content:"";position:absolute;left:90px;top:0;width:150px;height:100%;background:linear-gradient(110deg,transparent,rgba(255,255,255,.045),transparent);transform:skewX(-18deg);pointer-events:none}.page-head .title-wrap h1{color:#fff}.page-head .title-wrap span{color:#b8f34a}.page-head .title-wrap p{color:#c9d5cf}.page-head .title-wrap>i{border-color:#b8f34a;background:#fff;color:#274138;box-shadow:4px 4px 0 #b8f34a}.date-navigator{border:0;box-shadow:0 8px 20px rgba(38,58,52,.08)}.rhythm-board{position:relative;margin-bottom:18px;padding:17px;border:1.5px solid #d9ded4;border-radius:21px;background:rgba(255,255,255,.8);box-shadow:0 10px 25px rgba(42,59,52,.07);backdrop-filter:blur(8px)}.rhythm-board>header{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:13px}.rhythm-board>header span{color:#6faa36;font-size:9px;font-weight:900}.rhythm-board>header h2{margin:1px 0 0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:21px}.rhythm-board>header p{margin:0;padding:6px 9px;border-radius:8px;background:#eef3e9;color:#768078;font-size:9px}.rhythm-board>header b{color:#345129}.rhythm-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.rhythm-cards>button{position:relative;display:grid;min-width:0;min-height:112px;align-content:space-between;gap:5px;overflow:hidden;padding:12px;border:1.5px solid rgba(49,65,58,.13);border-radius:15px;background:var(--rhythm-bg);color:#314039;text-align:right;font:inherit;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease}.rhythm-cards>button:hover{transform:translateY(-3px);box-shadow:0 9px 18px rgba(42,55,49,.12)}.rhythm-cards>button::after{content:"";position:absolute;left:-18px;top:-20px;width:75px;height:75px;border-radius:50%;background:var(--rhythm-orb);opacity:.28}.rhythm-1{--rhythm-bg:linear-gradient(145deg,#fff9da,#fffdf1);--rhythm-orb:#facc15}.rhythm-2{--rhythm-bg:linear-gradient(145deg,#e9f8ef,#f6fff9);--rhythm-orb:#22c55e}.rhythm-3{--rhythm-bg:linear-gradient(145deg,#e9f3ff,#f7fbff);--rhythm-orb:#3b82f6}.rhythm-4{--rhythm-bg:linear-gradient(145deg,#f1eaff,#fcf9ff);--rhythm-orb:#8b5cf6}.rhythm-cards>button.complete{border-color:#6faa36;box-shadow:inset 0 0 0 1px rgba(111,170,54,.18)}.rhythm-cards>button>i{position:absolute;left:12px;top:10px;z-index:1;color:#526259;font-size:23px;font-style:normal}.rhythm-number{display:grid;width:23px;height:23px;place-items:center;border-radius:8px;background:#263a34;color:#fff;font-size:9px;font-weight:900}.rhythm-cards strong{position:relative;z-index:1;font-size:12px;direction:ltr;text-align:right}.rhythm-cards strong small{color:#8a958e;font-size:8px}.rhythm-cards em{position:relative;z-index:1;color:#748078;font-size:8.5px;font-style:normal;font-weight:900}.rhythm-cards .complete em{color:#528a25}.stats-grid article{border:0;box-shadow:0 8px 20px rgba(41,57,50,.07)}.stat-main{background:linear-gradient(135deg,#263a34,#315348)!important;color:#fff}.stat-main small,.stat-main span{color:#c8d7d0!important}.stat-main .coverage-ring{background:radial-gradient(circle,#29483e 58%,transparent 60%),conic-gradient(#b8f34a var(--p),rgba(255,255,255,.15) 0)}.category-chart{background:linear-gradient(145deg,#fff,#fbfaf5)!important}.action-row{justify-content:flex-end}.add-report{border-color:#263a34;background:linear-gradient(135deg,#b8f34a,#9ede32);box-shadow:3px 3px 0 #263a34}.add-category{border-color:#aeb9b2!important;background:rgba(255,255,255,.8)}.report-form{border-width:1.5px;box-shadow:0 16px 35px color-mix(in srgb,var(--accent) 14%,transparent)}.activity-editor>article{box-shadow:0 4px 12px rgba(44,58,51,.04)}.timeline-section{border:0;box-shadow:0 10px 28px rgba(40,55,49,.08)}.report-card{box-shadow:0 4px 12px rgba(40,55,49,.05)}.empty-slot{transition:.16s}.empty-slot:hover{background:#f3faeb;transform:translateX(-2px)}
@media(max-width:760px){.hourly-shell{padding:12px 8px 60px}.hourly-page{padding:16px 12px;border-radius:18px}.page-head{align-items:flex-start}.title-wrap>i{width:43px;height:43px;border-radius:14px;font-size:25px}.title-wrap h1{font-size:25px}.title-wrap p{display:none}.date-navigator>div{min-width:0;flex:1}.stats-grid{grid-template-columns:1fr}.category-chart{min-height:120px}.action-row{display:grid;grid-template-columns:1fr 1fr}.category-form{flex-wrap:wrap}.category-form>input{min-width:170px}.report-form footer{align-items:stretch;flex-direction:column;gap:9px}.time-slot{grid-template-columns:90px 1fr}.slot-time{padding-left:14px}.slot-time strong{font-size:8.5px}.report-card header{align-items:flex-start;flex-wrap:wrap}.report-card header b{margin-right:0}.report-card p{font-size:11px}.timeline-section{padding:13px 9px}.date-navigator .today-btn{position:static}.chart-legend{gap:8px}.hourly-page :deep(.shared-menu-button){width:38px;height:38px}}
@media(max-width:760px){.page-head{margin:-5px -3px 14px;padding:16px 13px}.rhythm-board{padding:13px}.rhythm-board>header{align-items:flex-start;flex-direction:column}.rhythm-cards{grid-template-columns:repeat(2,1fr)}.rhythm-cards>button{min-height:100px}.stat-main{min-height:120px}}
</style>
