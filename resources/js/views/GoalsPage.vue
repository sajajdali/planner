<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';
import PersianDatePicker from '../components/PersianDatePicker.vue';

type Goal = {
    id: number;
    title: string;
    type: string;
    category: string;
    color: string;
    soft_color: string;
    icon: string;
    status: string;
    status_label: string;
    status_bg: string;
    status_color: string;
    start_value: number;
    current_value: number;
    target_value: number;
    unit: string;
    direction: string;
    deadline: string | null;
    days_left: number | null;
    why: string | null;
    next_action: string;
    last_activity: string;
    percent: number;
    milestones: { id: number; title: string; is_done: boolean; date_label: string; weight: number; progress: number; status: string; starts_on: string | null; ends_on: string | null; dependency: string | null }[];
    plan_items: { id: number; title: string; when: string }[];
    logs: { id: number; value: number; value_label: string; energy: number; note: string; date_label: string }[];
};

const loading = ref(true);
const error = ref('');
const goals = ref<Goal[]>([]);
const stats = ref({ activeCount: 0, avgProgress: 0, needsAttention: 0, completedCount: 0 });
const filter = ref('all');
const sortMode = ref('priority');
const drawerOpen = ref(false);
const menuGoalId = ref<number | null>(null);
const detailGoal = ref<Goal | null>(null);
const detailTab = ref('overview');
const progressGoal = ref<Goal | null>(null);
const progressDraft = ref({ value: '', milestone_id: '', milestone_progress: 100, energy: 3, note: '' });
const goalModal = ref(false);
const step = ref(1);
const goalDraft = ref({
    type: '',
    title: '',
    description: '',
    category: 'سلامتی',
    color: '#2563EB',
    why: '',
    impact: '',
    risk: '',
    importance: 7,
    mantra: '',
    start_value: '',
    current_value: '',
    target_value: '',
    unit: '',
    direction: 'increase',
    minimum_result: '',
    ideal_result: '',
    completion_criteria: '',
    expected_output: '',
    requires_approval: 'no',
    repeat_count: '',
    repeat_period: 'week',
    weekdays: [] as string[],
    suggested_time: '',
    min_amount: '',
    no_deadline: false,
    deadline: '',
    active_days: '',
    duration_minutes: '',
    actions: '',
    resources: '',
    obstacles: '',
    obstacle_solutions: '',
    companions: '',
    cost: '',
    notes: '',
    reminder_time: '',
    reminder_repeat: 'daily',
    report_reminder: true,
    deadline_reminder: true,
    lag_reminder: true,
    new_stage: '',
    new_stage_weight: '1',
    new_stage_ends_on: '',
    milestones: [] as { title: string; weight: number; ends_on: string; dependency: string }[],
});

const filters = [
    ['all', 'همه'],
    ['onTrack', 'در مسیر'],
    ['attention', 'نیازمند توجه'],
    ['atRisk', 'عقب‌افتاده'],
    ['planned', 'برنامه‌ریزی‌شده'],
    ['paused', 'متوقف'],
    ['done', 'تکمیل‌شده'],
    ['archived', 'بایگانی‌شده'],
];
const categories = ['سلامتی', 'کاری', 'مالی', 'آموزش', 'رابطه‌ها', 'رشد فردی'];
const colors = ['#2563EB', '#16A34A', '#F59E0B', '#DB2777', '#8B5CF6', '#06B6D4'];
const goalTypes = [
    { key: 'numeric', label: 'هدف عددی', example: 'مثلاً: وزنم را از ۹۵ به ۸۵ کیلو برسانم.' },
    { key: 'doable', label: 'هدف انجام‌شدنی', example: 'مثلاً: نسخه اول اپلیکیشن را منتشر کنم.' },
    { key: 'habit', label: 'هدف عادت‌محور', example: 'مثلاً: هفته‌ای سه جلسه ورزش کنم.' },
    { key: 'milestone', label: 'هدف مرحله‌ای', example: 'مثلاً: کسب‌وکار جدیدم را راه‌اندازی کنم.' },
    { key: 'ongoing', label: 'هدف مستمر', example: 'مثلاً: هر روز مطالعه داشته باشم.' },
];
const iconMap: Record<string, string> = {
    weight: 'M12 2v4M6 6l2.5 2.5M18 6L15.5 8.5M12 22a10 10 0 100-20 10 10 0 000 20zM12 12l3-3',
    product: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    habit: 'M9 11l3 3L22 4 M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
    business: 'M3 21h18 M5 21V9l7-6 7 6v12 M9 21v-6h6v6',
    finance: 'M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6',
    reading: 'M4 5c2-1 5-1 8 0v14c-3-1-6-1-8 0z M20 5c-2-1-5-1-8 0v14c3-1 6-1 8 0z',
    target: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 18a6 6 0 100-12 6 6 0 000 12zM12 14a2 2 0 100-4 2 2 0 000 4z',
};

const activeCount = computed(() => stats.value.activeCount);
const headerSubtitle = computed(() => `${fa(stats.value.activeCount)} هدف فعال · ${fa(stats.value.needsAttention)} مورد نیازمند توجه`);
const wizardSteps = [
    'نوع هدف',
    'اطلاعات اصلی',
    'معیار موفقیت',
    'تاریخ هدف',
];
const stepDots = computed(() => wizardSteps.map((_, index) => index + 1));
const currentType = computed(() => goalTypes.find((item) => item.key === goalDraft.value.type));
const filteredTotal = computed(() => goals.value.length);

onMounted(loadGoals);

async function loadGoals() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/goals', { params: { filter: filter.value, sort: sortMode.value } });
        goals.value = data.goals;
        stats.value = data.stats;
    } catch {
        error.value = 'اهداف فعلاً بارگذاری نشدند.';
    } finally {
        loading.value = false;
    }
}

function fa(value: number | string | null | undefined) {
    return String(value ?? '').replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function faText(value: number | string | null | undefined) {
    return fa(String(value ?? ''));
}

function toNumber(value: number | string | null | undefined, fallback = 0) {
    const normalized = String(value ?? '')
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
        .replace(/٬/g, '')
        .replace(/,/g, '')
        .trim();
    const number = Number(normalized);
    return Number.isFinite(number) ? number : fallback;
}

function valueLabel(goal: Goal) {
    return goal.unit === '٪'
        ? `${fa(Math.round(goal.current_value))}٪ از ${fa(Math.round(goal.target_value))}٪`
        : `${fa(Math.round(goal.current_value))} از ${fa(Math.round(goal.target_value))} ${goal.unit}`;
}

function daysLeftLabel(goal: Goal) {
    if (goal.days_left === null) return 'بدون مهلت مشخص';
    if (goal.days_left < 0) return `${fa(Math.abs(goal.days_left))} روز تأخیر`;
    return `${fa(goal.days_left)} روز باقی‌مانده`;
}

function logPercent(goal: Goal, value: number) {
    const start = Number(goal.start_value || 0);
    const target = Number(goal.target_value || 100);
    if (goal.direction === 'decrease') {
        const total = Math.max(0.01, start - target);
        return Math.max(0, Math.min(100, Math.round(((start - value) / total) * 100)));
    }
    const total = Math.max(0.01, target - start);
    return Math.max(0, Math.min(100, Math.round(((value - start) / total) * 100)));
}

function progressChart(goal: Goal) {
    const logs = [...goal.logs].reverse();
    if (!logs.length) return [{ id: 0, value_label: valueLabel(goal), percent: goal.percent, date_label: 'اکنون' }];
    return logs.map((log) => ({ ...log, percent: logPercent(goal, log.value) }));
}

function openNewGoal() {
    step.value = 1;
    goalDraft.value = {
        type: '',
        title: '',
        description: '',
        category: 'سلامتی',
        color: '#2563EB',
        why: '',
        impact: '',
        risk: '',
        importance: 7,
        mantra: '',
        start_value: '',
        current_value: '',
        target_value: '',
        unit: '',
        direction: 'increase',
        minimum_result: '',
        ideal_result: '',
        completion_criteria: '',
        expected_output: '',
        requires_approval: 'no',
        repeat_count: '',
        repeat_period: 'week',
        weekdays: [],
        suggested_time: '',
        min_amount: '',
        no_deadline: false,
        deadline: '',
        active_days: '',
        duration_minutes: '',
        actions: '',
        resources: '',
        obstacles: '',
        obstacle_solutions: '',
        companions: '',
        cost: '',
        notes: '',
        reminder_time: '',
        reminder_repeat: 'daily',
        report_reminder: true,
        deadline_reminder: true,
        lag_reminder: true,
        new_stage: '',
        new_stage_weight: '1',
        new_stage_ends_on: '',
        milestones: [],
    };
    goalModal.value = true;
}

function nextStep() {
    if (step.value === 1 && !goalDraft.value.type) return;
    if (step.value === 2 && !goalDraft.value.title.trim()) return;
    if (step.value < 4) {
        step.value += 1;
        return;
    }
    void createGoal();
}

function addStage() {
    const title = goalDraft.value.new_stage.trim();
    if (!title) return;
    goalDraft.value.milestones.push({
        title,
        weight: Number(goalDraft.value.new_stage_weight || 1),
        ends_on: goalDraft.value.new_stage_ends_on,
        dependency: '',
    });
    goalDraft.value.new_stage = '';
    goalDraft.value.new_stage_weight = '1';
    goalDraft.value.new_stage_ends_on = '';
}

function previewMeta() {
    const draft = goalDraft.value;
    if (draft.type === 'numeric') return `${draft.category} · ${fa(draft.start_value || '۰')} → ${fa(draft.target_value || '۱۰۰')} ${draft.unit || ''}`;
    if (draft.type === 'doable') return `${draft.category} · ${draft.completion_criteria || 'معیار تکمیل نامشخص'}`;
    if (draft.type === 'habit') return `${draft.category} · ${fa(draft.repeat_count || '؟')} بار در ${draft.repeat_period === 'day' ? 'روز' : draft.repeat_period === 'month' ? 'ماه' : 'هفته'}`;
    if (draft.type === 'milestone') return `${draft.category} · ${fa(draft.milestones.length)} مرحله · وزن کل ${fa(draft.milestones.reduce((sum, item) => sum + Number(item.weight || 1), 0))}`;
    return `${draft.category} · حداقل: ${draft.min_amount || 'نامشخص'}`;
}

async function createGoal() {
    const draft = goalDraft.value;
    const target = draft.type === 'numeric'
        ? toNumber(draft.target_value, 100)
        : draft.type === 'milestone'
            ? Math.max(1, draft.milestones.length)
            : draft.type === 'habit'
                ? toNumber(draft.repeat_count, 1)
                : 100;
    const unit = draft.type === 'numeric'
        ? (draft.unit || '٪')
        : draft.type === 'milestone'
            ? 'مرحله'
            : draft.type === 'habit'
                ? `بار در ${draft.repeat_period === 'day' ? 'روز' : draft.repeat_period === 'month' ? 'ماه' : 'هفته'}`
                : draft.type === 'ongoing'
                    ? (draft.unit || draft.min_amount || '٪')
                    : '٪';

    const { data } = await api.post('/goals', {
        title: draft.title,
        type: draft.type,
        category: draft.category,
        color: draft.color,
        why: draft.why,
        deadline: draft.no_deadline ? null : (draft.deadline || null),
        start_value: toNumber(draft.start_value, 0),
        current_value: toNumber(draft.current_value || draft.start_value, 0),
        target_value: target,
        unit,
        direction: draft.direction,
        next_action: draft.type === 'doable' ? draft.completion_criteria : 'شروع اولین اقدام',
        metadata: {
            description: draft.description,
            impact: draft.impact,
            risk: draft.risk,
            importance: draft.importance,
            mantra: draft.mantra,
            minimum_result: draft.minimum_result,
            ideal_result: draft.ideal_result,
            completion_criteria: draft.completion_criteria,
            expected_output: draft.expected_output,
            requires_approval: draft.requires_approval,
            repeat_count: draft.repeat_count,
            repeat_period: draft.repeat_period,
            weekdays: draft.weekdays,
            suggested_time: draft.suggested_time,
            min_amount: draft.min_amount,
            no_deadline: draft.no_deadline,
            active_days: draft.active_days,
            duration_minutes: draft.duration_minutes,
            actions: draft.actions,
            resources: draft.resources,
            obstacles: draft.obstacles,
            obstacle_solutions: draft.obstacle_solutions,
            companions: draft.companions,
            cost: draft.cost,
            notes: draft.notes,
            reminder_time: draft.reminder_time,
            reminder_repeat: draft.reminder_repeat,
            report_reminder: draft.report_reminder,
            deadline_reminder: draft.deadline_reminder,
            lag_reminder: draft.lag_reminder,
        },
        milestones: draft.milestones,
    });
    goals.value = [data, ...goals.value];
    goalModal.value = false;
    await loadGoals();
}

async function toggleMilestone(goal: Goal, milestoneId: number, done: boolean) {
    const { data } = await api.post(`/goals/${goal.id}/milestones/${milestoneId}/toggle`, { done });
    goals.value = goals.value.map((item) => item.id === data.id ? data : item);
    detailGoal.value = data;
    await loadGoals();
}

async function updateStatus(goal: Goal, status: string) {
    const { data } = await api.put(`/goals/${goal.id}/status`, { status });
    goals.value = goals.value.map((item) => item.id === goal.id ? data : item);
    menuGoalId.value = null;
}

async function deleteGoal(goal: Goal) {
    if (!window.confirm(`هدف «${goal.title}» حذف شود؟`)) return;
    await api.delete(`/goals/${goal.id}`);
    goals.value = goals.value.filter((item) => item.id !== goal.id);
    menuGoalId.value = null;
}

function openProgress(goal: Goal) {
    progressGoal.value = goal;
    progressDraft.value = {
        value: goal.type === 'numeric' ? String(Math.round(goal.current_value || goal.start_value || 0)) : '',
        milestone_id: goal.type === 'milestone' ? String(goal.milestones.find((item) => !item.is_done)?.id || goal.milestones[0]?.id || '') : '',
        milestone_progress: 100,
        energy: 3,
        note: '',
    };
}

async function submitProgress() {
    if (!progressGoal.value) return;
    if (progressGoal.value.type === 'milestone' && !progressDraft.value.milestone_id) return;
    if (progressGoal.value.type !== 'milestone' && progressDraft.value.value === '') return;
    const payload = progressGoal.value.type === 'milestone'
        ? {
            milestone_id: toNumber(progressDraft.value.milestone_id),
            milestone_progress: toNumber(progressDraft.value.milestone_progress, 100),
            energy: progressDraft.value.energy,
            note: progressDraft.value.note,
        }
        : {
            value: toNumber(progressDraft.value.value),
            energy: progressDraft.value.energy,
            note: progressDraft.value.note,
        };
    const { data } = await api.post(`/goals/${progressGoal.value.id}/progress`, payload);
    goals.value = goals.value.map((item) => item.id === data.id ? data : item);
    if (detailGoal.value?.id === data.id) detailGoal.value = data;
    progressGoal.value = null;
    await loadGoals();
}
</script>

<template>
    <div class="goals-shell" dir="rtl">
        <section class="goals-paper">
            <i class="goal-tape yellow"></i>
            <i class="goal-tape purple"></i>
            <i class="goal-tape pink"></i>

            <header class="goals-header">
                <div class="goals-brand">
                    <i>ه</i>
                    <div>
                        <h1>اهداف من</h1>
                        <p>{{ headerSubtitle }}</p>
                    </div>
                </div>
                <AppMenu />
            </header>

            <div class="goal-stats">
                <article class="blue"><span>اهداف فعال</span><b>{{ fa(stats.activeCount) }}</b></article>
                <article class="green"><span>میانگین پیشرفت</span><b>{{ fa(stats.avgProgress) }}٪</b></article>
                <article class="amber"><span>نیازمند توجه</span><b>{{ fa(stats.needsAttention) }}</b></article>
                <article class="violet"><span>تکمیل‌شده</span><b>{{ fa(stats.completedCount) }}</b></article>
            </div>

            <div class="goal-filters">
                <button v-for="[key, label] in filters" :key="key" :class="{ active: filter === key }" @click="filter = key; loadGoals()">{{ label }}</button>
            </div>

            <div class="goal-toolbar">
                <label>
                    <span>مرتب‌سازی:</span>
                    <select v-model="sortMode" @change="loadGoals">
                        <option value="priority">اولویت</option>
                        <option value="deadline">مهلت</option>
                        <option value="progress">درصد پیشرفت</option>
                        <option value="created">تاریخ ایجاد</option>
                        <option value="activity">آخرین فعالیت</option>
                    </select>
                </label>
                <button class="new-goal-btn" @click="openNewGoal"><span>+</span> هدف جدید</button>
            </div>

            <div v-if="loading" class="goal-state">در حال بارگذاری اهداف...</div>
            <div v-else-if="error" class="goal-state error">{{ error }}</div>
            <div v-else-if="!filteredTotal" class="goal-empty">
                <div>🎯</div>
                <strong>هنوز هدفی ثبت نکرده‌ای</strong>
                <p>اولین قدم، مشخص‌کردن چیزی است که می‌خواهی به آن برسی.</p>
                <button @click="openNewGoal">ساخت اولین هدف</button>
            </div>

            <div v-else class="goal-list">
                <section v-for="(goal, index) in goals" :key="goal.id" class="goal-card" :class="[`tilt-${index % 4}`, { 'menu-open': menuGoalId === goal.id }]" :style="{ '--c': goal.color, '--soft': goal.soft_color }">
                    <i class="goal-strip"></i>
                    <div class="goal-icon"><svg viewBox="0 0 24 24"><path :d="iconMap[goal.icon] || iconMap.target"></path></svg></div>
                    <div class="goal-card-body">
                        <header>
                            <strong @click="detailGoal = goal; detailTab = 'overview'">{{ goal.title }}</strong>
                            <span class="goal-status" :style="{ background: goal.status_bg, color: goal.status_color }">{{ goal.status_label }}</span>
                            <small>{{ goal.category }}</small>
                        </header>
                        <p>{{ valueLabel(goal) }}</p>
                        <div class="goal-progress"><i :style="{ width: `${goal.percent}%` }"></i></div>
                        <footer>
                            <div>
                                <span>⏳ {{ daysLeftLabel(goal) }}</span>
                                <span>اقدام بعدی: {{ goal.next_action }}</span>
                            </div>
                            <nav>
                                <button class="solid" @click="openProgress(goal)">ثبت پیشرفت</button>
                                <button @click="detailGoal = goal; detailTab = 'overview'">مشاهده</button>
                                <button class="dots" @click="menuGoalId = menuGoalId === goal.id ? null : goal.id">⋮</button>
                                <div v-if="menuGoalId === goal.id" class="goal-menu">
                                    <button @click="updateStatus(goal, goal.status === 'paused' ? 'onTrack' : 'paused')">{{ goal.status === 'paused' ? 'ادامه هدف' : 'توقف موقت' }}</button>
                                    <button @click="updateStatus(goal, 'done')">تکمیل هدف</button>
                                    <button @click="updateStatus(goal, 'archived')">بایگانی</button>
                                    <button class="danger" @click="deleteGoal(goal)">حذف</button>
                                </div>
                            </nav>
                        </footer>
                    </div>
                </section>
            </div>
        </section>

        <div v-if="goalModal" class="goal-modal-backdrop">
            <form class="goal-modal" @submit.prevent="nextStep">
                <h2>ساخت هدف جدید</h2>
                <p>مرحله {{ fa(step) }} از {{ fa(wizardSteps.length) }} · {{ wizardSteps[step - 1] }}</p>
                <div class="step-dots"><i v-for="dot in stepDots" :key="dot" :class="{ active: dot <= step }"></i></div>

                <div v-if="step === 1" class="goal-type-list">
                    <div class="goal-step-title">نوع هدف را انتخاب کن</div>
                    <button v-for="type in goalTypes" :key="type.key" type="button" :class="{ active: goalDraft.type === type.key }" @click="goalDraft.type = type.key">
                        <b>{{ type.label }}</b>
                        <span>{{ type.example }}</span>
                    </button>
                </div>

                <div v-else-if="step === 2" class="goal-form-grid">
                    <label class="full"><span>عنوان هدف</span><input v-model="goalDraft.title" required autofocus placeholder="مثلاً: وزنم را به ۸۵ کیلو برسانم" /></label>
                    <label class="full"><span>دسته‌بندی</span><select v-model="goalDraft.category"><option v-for="category in categories" :key="category">{{ category }}</option></select></label>
                    <label class="full"><span>رنگ هدف</span></label>
                    <div class="color-row"><button v-for="color in colors" :key="color" type="button" :class="{ active: goalDraft.color === color }" :style="{ background: color }" @click="goalDraft.color = color"></button></div>
                </div>

                <div v-else-if="step === 3" class="goal-form-grid">
                    <label class="full"><span>چرا این هدف برای من مهم است؟</span><textarea v-model="goalDraft.why" placeholder="دلیل و انگیزه‌ات را بنویس..."></textarea></label>
                    <div class="success-title full">معیار موفقیت ({{ currentType?.label || '' }})</div>
                    <template v-if="goalDraft.type === 'numeric'">
                        <label><span>مقدار شروع</span><input v-model="goalDraft.start_value" inputmode="decimal" placeholder="۹۵" /></label>
                        <label><span>مقدار هدف</span><input v-model="goalDraft.target_value" inputmode="decimal" placeholder="۸۵" /></label>
                        <label><span>واحد</span><input v-model="goalDraft.unit" placeholder="کیلوگرم" /></label>
                        <label class="full"><span>جهت هدف</span><select v-model="goalDraft.direction"><option value="increase">افزایشی</option><option value="decrease">کاهشی</option></select></label>
                    </template>
                    <template v-else-if="goalDraft.type === 'doable'">
                        <label class="full"><span>معیار تکمیل</span><input v-model="goalDraft.completion_criteria" placeholder="مثلاً: انتشار در استور" /></label>
                        <label class="full"><span>خروجی مورد انتظار</span><input v-model="goalDraft.expected_output" placeholder="مثلاً: نسخه قابل نصب" /></label>
                    </template>
                    <template v-else-if="goalDraft.type === 'habit'">
                        <label><span>تعداد تکرار</span><input v-model="goalDraft.repeat_count" placeholder="۳" /></label>
                        <label><span>دوره تکرار</span><select v-model="goalDraft.repeat_period"><option value="week">هفتگی</option><option value="day">روزانه</option><option value="month">ماهانه</option></select></label>
                        <label class="full"><span>زمان پیشنهادی</span><input v-model="goalDraft.suggested_time" type="time" /></label>
                    </template>
                    <template v-else-if="goalDraft.type === 'milestone'">
                        <div class="stage-list full">
                            <span v-for="(stage, index) in goalDraft.milestones" :key="`${stage.title}-${index}`">{{ stage.title }} <button type="button" @click="goalDraft.milestones.splice(index, 1)">×</button></span>
                        </div>
                        <div class="stage-inline full">
                            <input v-model="goalDraft.new_stage" placeholder="عنوان مرحله جدید..." @keydown.enter.prevent="addStage" />
                            <button type="button" @click="addStage">＋</button>
                        </div>
                    </template>
                    <template v-else>
                        <label class="full"><span>حداقل مقدار انجام در هر بار</span><input v-model="goalDraft.min_amount" placeholder="مثلاً: ۲۰ دقیقه" /></label>
                    </template>
                </div>

                <div v-else class="goal-form-grid">
                    <label class="full"><span>تاریخ هدف</span><PersianDatePicker v-model="goalDraft.deadline" placeholder="۱۴۰۵/۰۵/۰۴" /></label>
                    <div class="preview-box full">
                        <div>پیش‌نمایش هدف</div>
                        <b>{{ goalDraft.title || 'بدون عنوان' }}</b>
                        <span>{{ previewMeta() }}</span>
                    </div>
                </div>

                <footer>
                    <button type="button" @click="goalModal = false">انصراف</button>
                    <div>
                        <button v-if="step > 1" type="button" @click="step--">قبلی</button>
                        <button type="submit">{{ step < 4 ? 'بعدی' : 'ثبت و شروع هدف' }}</button>
                    </div>
                </footer>
            </form>
        </div>

        <div v-if="progressGoal" class="goal-modal-backdrop">
            <form class="goal-modal small" @submit.prevent="submitProgress">
                <h2 :style="{ color: progressGoal.color }">ثبت پیشرفت: {{ progressGoal.title }}</h2>
                <template v-if="progressGoal.type === 'milestone'">
                    <label><span>مرحله هدف</span>
                        <select v-model="progressDraft.milestone_id" required autofocus>
                            <option value="" disabled>انتخاب مرحله</option>
                            <option v-for="milestone in progressGoal.milestones" :key="milestone.id" :value="milestone.id">
                                {{ milestone.title }} · {{ milestone.is_done ? 'تکمیل‌شده' : milestone.progress ? `${fa(milestone.progress)}٪` : 'در انتظار' }}
                            </option>
                        </select>
                    </label>
                    <label><span>درصد پیشرفت مرحله</span><input v-model="progressDraft.milestone_progress" type="range" min="0" max="100" /></label>
                    <div class="progress-value">{{ fa(progressDraft.milestone_progress) }}٪</div>
                </template>
                <template v-else-if="progressGoal.type === 'habit'">
                    <label><span>تعداد انجام‌شده ({{ progressGoal.unit }})</span><input v-model="progressDraft.value" autofocus required inputmode="decimal" placeholder="مثلاً ۲" /></label>
                </template>
                <template v-else-if="progressGoal.type === 'doable'">
                    <label><span>درصد تکمیل</span><input v-model="progressDraft.value" autofocus required inputmode="decimal" placeholder="مثلاً ۶۰" /></label>
                </template>
                <template v-else-if="progressGoal.type === 'ongoing'">
                    <label><span>مقدار انجام‌شده ({{ progressGoal.unit }})</span><input v-model="progressDraft.value" autofocus required inputmode="decimal" placeholder="مثلاً ۲۰" /></label>
                </template>
                <template v-else>
                    <label><span>مقدار جدید ({{ progressGoal.unit }})</span><input v-model="progressDraft.value" autofocus required inputmode="decimal" /></label>
                </template>
                <label><span>میزان انرژی</span><input v-model="progressDraft.energy" type="range" min="1" max="5" /></label>
                <label><span>یادداشت</span><textarea v-model="progressDraft.note" placeholder="چه اتفاقی افتاد؟"></textarea></label>
                <footer><button type="button" @click="progressGoal = null">انصراف</button><button type="submit">ثبت پیشرفت</button></footer>
            </form>
        </div>

        <div v-if="detailGoal" class="goal-modal-backdrop">
            <section class="goal-modal detail">
                <header class="detail-head">
                    <div><h2 :style="{ color: detailGoal.color }">{{ detailGoal.title }}</h2><p>{{ detailGoal.category }} · {{ detailGoal.status_label }} · {{ daysLeftLabel(detailGoal) }}</p></div>
                    <button @click="detailGoal = null">×</button>
                </header>
                <div class="detail-progress">
                    <div><i :style="{ background: detailGoal.color, width: `${detailGoal.percent}%` }"></i></div>
                    <b :style="{ color: detailGoal.color }">{{ fa(detailGoal.percent) }}٪</b>
                </div>
                <div class="detail-tabs"><button v-for="tab in [['overview','نمای کلی'],['plan','برنامه'],['milestones','مراحل'],['reports','گزارش پیشرفت']]" :key="tab[0]" :class="{ active: detailTab === tab[0] }" :style="detailTab === tab[0] ? { background: detailGoal.color } : {}" @click="detailTab = tab[0]">{{ tab[1] }}</button></div>
                <div v-if="detailTab === 'overview'" class="detail-panel"><label>دلیل و انگیزه</label><p>{{ detailGoal.why || '—' }}</p><div><b>اقدام بعدی</b><span>{{ detailGoal.next_action }}</span></div><div><b>آخرین فعالیت</b><span>{{ detailGoal.last_activity }}</span></div></div>
                <div v-else-if="detailTab === 'plan'" class="detail-list"><article v-for="item in detailGoal.plan_items" :key="item.id"><i :style="{ background: detailGoal.color }"></i><span>{{ item.title }}</span><small>{{ item.when }}</small></article></div>
                <div v-else-if="detailTab === 'milestones'" class="detail-list milestone-detail-list">
                    <article v-for="item in detailGoal.milestones" :key="item.id">
                        <i :class="{ done: item.is_done }" :style="{ background: item.is_done ? detailGoal.color : '#fff' }"></i>
                        <span>{{ item.title }}</span>
                        <small>وزن {{ fa(item.weight) }} · {{ fa(item.progress) }}٪ · {{ item.date_label }}</small>
                        <button type="button" @click="toggleMilestone(detailGoal, item.id, !item.is_done)">{{ item.is_done ? 'برگشت' : 'انجام شد' }}</button>
                    </article>
                </div>
                <div v-else class="detail-reports">
                    <div v-if="detailGoal.logs.length" class="progress-chart">
                        <span v-for="point in progressChart(detailGoal)" :key="point.id" :style="{ height: `${Math.max(8, point.percent)}%`, background: detailGoal.color }" :title="`${point.value_label} · ${fa(point.percent)}٪`"></span>
                    </div>
                    <div v-if="detailGoal.logs.length" class="detail-list">
                        <article v-for="log in detailGoal.logs" :key="log.id">
                            <span>{{ faText(log.value_label) }}</span>
                            <small>{{ log.date_label }} · {{ fa(logPercent(detailGoal, log.value)) }}٪</small>
                            <p>{{ log.note }}</p>
                        </article>
                    </div>
                    <div v-else class="goal-state">هنوز گزارشی ثبت نشده است.</div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.goals-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 20%,#2e2140 0%,#1a1424 70%);padding:44px 20px 90px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}.goals-paper{width:900px;max-width:100%;margin:auto;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:6px;box-shadow:0 30px 60px rgba(0,0,0,.5),0 0 0 1px rgba(0,0,0,.05);position:relative;padding:34px;transform:rotate(-.4deg)}.goal-tape{position:absolute;box-shadow:0 3px 6px rgba(0,0,0,.2);opacity:.85}.goal-tape.yellow{top:-16px;right:60px;width:110px;height:34px;background:#ffd93d;transform:rotate(-6deg)}.goal-tape.purple{top:-14px;left:80px;width:90px;height:32px;background:#9b5de5;transform:rotate(5deg)}.goal-tape.pink{top:20px;left:-14px;width:32px;height:90px;background:#ff6fa5;transform:rotate(3deg)}.goals-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}.goals-brand{display:flex;align-items:center;gap:10px}.goals-brand i{width:38px;height:38px;border-radius:50%;background:#9b5de5;display:grid;place-items:center;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#fff;box-shadow:2px 2px 0 #3a2e1f;transform:rotate(-4deg);font-style:normal}.goals-brand h1{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:26px}.goals-brand p{margin:2px 0 0;font-size:12px;color:#7a6a4f}.goal-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-bottom:22px}.goal-stats article{border:2px solid #3a2e1f;border-radius:14px;padding:12px;box-shadow:2px 2px 0 #3a2e1f;color:#fff}.goal-stats .blue{background:#2563eb}.goal-stats .green{background:#16a34a}.goal-stats .amber{background:#f59e0b}.goal-stats .violet{background:#8b5cf6}.goal-stats span{font-size:10.5px;opacity:.88}.goal-stats b{display:block;margin-top:4px;font-size:19px}.goal-filters{display:flex;gap:7px;overflow-x:auto;padding-bottom:4px;margin-bottom:12px}.goal-filters button{height:32px;padding:0 14px;border-radius:20px;border:2px solid #3a2e1f;background:#fff;color:#3a2e1f;font-size:12px;font-weight:800;white-space:nowrap}.goal-filters button.active{background:#3a2e1f;color:#fff}.goal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:18px}.goal-toolbar label{display:flex;align-items:center;gap:8px}.goal-toolbar span{font-size:11.5px;color:#9a8b6a}.goal-toolbar select{height:32px;border-radius:9px;border:2px solid #3a2e1f;background:#fff;padding:0 9px}.new-goal-btn{height:38px;padding:0 16px;border-radius:10px;border:2px solid #3a2e1f;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;font-weight:900}.goal-list{display:grid;gap:14px}.goal-card{position:relative;display:grid;grid-template-columns:44px minmax(0,1fr);gap:13px;background:#fff;border:2px solid #3a2e1f;border-radius:16px;box-shadow:4px 4px 0 #3a2e1f;padding:16px 18px}.tilt-0{transform:rotate(-.5deg)}.tilt-1{transform:rotate(.4deg)}.tilt-2{transform:rotate(-.3deg)}.tilt-3{transform:rotate(.5deg)}.goal-strip{position:absolute;inset:0 auto 0 0;width:6px;background:var(--c);border-radius:14px 0 0 14px}.goal-icon{width:44px;height:44px;border-radius:12px;background:var(--soft);border:2px solid #3a2e1f;display:grid;place-items:center}.goal-icon svg{width:21px;height:21px;fill:none;stroke:var(--c);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.goal-card-body header{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.goal-card-body header strong{font-size:15px;cursor:pointer}.goal-status{font-size:9.5px;font-weight:900;padding:2px 8px;border-radius:20px;border:1px solid #3a2e1f}.goal-card-body small,.goal-card-body p,.goal-card footer span{font-size:11px;color:#7a6a4f}.goal-progress{height:9px;border-radius:6px;background:#f0ebd8;overflow:hidden;border:1px solid #efe3c4}.goal-progress i{display:block;height:100%;background:var(--c);border-radius:6px}.goal-card footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:9px;flex-wrap:wrap}.goal-card footer>div{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.goal-card nav{display:flex;align-items:center;gap:6px;position:relative}.goal-card nav button{height:28px;padding:0 11px;border-radius:8px;border:2px solid #3a2e1f;background:#fff;font-size:11px;font-weight:800}.goal-card nav .solid{background:var(--c);color:#fff;box-shadow:1.5px 1.5px 0 #3a2e1f}.goal-card nav .dots{width:28px;padding:0}.goal-menu{position:absolute;left:0;top:32px;z-index:30;display:grid;min-width:140px;background:#fff;border:2px solid #3a2e1f;border-radius:10px;box-shadow:3px 3px 0 #3a2e1f;overflow:hidden}.goal-menu button{height:34px;border:0;border-radius:0;text-align:right}.goal-menu .danger{color:#b91c1c}.goal-state,.goal-empty{background:#fff;border:2px dashed #b9ac8c;border-radius:16px;padding:42px 20px;text-align:center;font-weight:900}.goal-state.error{color:#b91c1c}.goal-empty div{font-size:40px}.goal-empty strong{display:block;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px}.goal-empty p{color:#9a8b6a}.goal-empty button{height:40px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;font-weight:900}.goal-modal-backdrop{position:fixed;inset:0;z-index:6000;display:grid;place-items:center;background:rgba(20,15,10,.6);padding:20px}.goal-modal{width:480px;max-width:92vw;max-height:88vh;overflow:auto;background:#fffbf0;border:3px solid #3a2e1f;border-radius:18px;box-shadow:6px 6px 0 rgba(0,0,0,.3);padding:22px;display:grid;gap:12px}.goal-modal.small{width:400px}.goal-modal.detail{width:560px;max-width:94vw}.goal-modal h2{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;color:#9b5de5}.goal-modal>p{margin:0;color:#9a8b6a;font-size:11.5px}.step-dots{display:flex;gap:5px}.step-dots i{flex:1;height:5px;border-radius:4px;background:#efe3c4}.step-dots i.active{background:#9b5de5}.goal-type-list{display:grid;gap:8px}.goal-type-list button{text-align:right;border:2px solid #efe3c4;background:#fff;border-radius:12px;padding:11px 13px}.goal-type-list button.active{border-color:#9b5de5;background:#f1ecfe}.goal-type-list b,.goal-type-list span{display:block}.goal-type-list span{margin-top:3px;color:#9a8b6a;font-size:11px}.goal-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.goal-form-grid .full{grid-column:1/-1}.goal-modal label{display:grid;gap:5px}.goal-modal label span{font-size:11.5px;font-weight:800;color:#4b3b22}.goal-modal input,.goal-modal select,.goal-modal textarea{border:1.5px solid #efe3c4;border-radius:9px;background:#fff;min-height:38px;padding:0 10px;font-family:inherit}.goal-modal textarea{padding:8px 10px;min-height:72px}.color-row{grid-column:1/-1;display:flex;gap:8px}.color-row button{width:28px;height:28px;border-radius:8px;border:2.5px solid transparent}.color-row button.active{border-color:#3a2e1f}.stage-list{display:flex;flex-direction:column;gap:6px}.stage-list span{background:#f5eedc;border-radius:8px;padding:6px 10px}.stage-list button{border:0;background:transparent}.stage-add{align-self:end;width:38px;height:38px;border:1.5px solid #3a2e1f;border-radius:9px;background:#ffd93d}.preview-box{background:#f5eedc;border-radius:12px;padding:13px}.preview-box b,.preview-box span{display:block}.preview-box span{color:#9a8b6a;font-size:11px}.goal-modal footer{display:flex;justify-content:space-between;gap:8px;margin-top:6px}.goal-modal footer div{display:flex;gap:8px}.goal-modal footer button{border:1.5px solid #3a2e1f;background:#fff;padding:9px 16px;border-radius:10px}.goal-modal footer button[type=submit],.goal-modal footer div button:last-child{background:#3a2e1f;color:#fff}.detail-head{display:flex;align-items:flex-start;justify-content:space-between}.detail-head p{margin:0;color:#9a8b6a;font-size:11.5px}.detail-head button{width:28px;height:28px;border-radius:8px;border:2px solid #3a2e1f;background:#fff}.detail-progress{display:flex;align-items:center;gap:12px;background:#f5eedc;border-radius:12px;padding:13px}.detail-progress::before{content:'';display:block;flex:1;height:10px;border-radius:6px;background:#f0ebd8;border:1px solid #efe3c4}.detail-progress i{position:absolute;height:10px;border-radius:6px;max-width:calc(100% - 90px)}.detail-tabs{display:flex;gap:6px;overflow-x:auto}.detail-tabs button{height:30px;padding:0 13px;border-radius:20px;border:2px solid #3a2e1f;background:#fff;white-space:nowrap}.detail-tabs button.active{color:#fff}.detail-panel{display:grid;grid-template-columns:1fr 1fr;gap:10px}.detail-panel label,.detail-panel p{grid-column:1/-1}.detail-panel p,.detail-panel div,.detail-list article{background:#fff;border:1.5px solid #efe3c4;border-radius:10px;padding:10px 12px}.detail-panel b,.detail-panel span{display:block}.detail-list{display:grid;gap:8px}.detail-list article{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.detail-list i{width:16px;height:16px;border-radius:5px;border:2px solid #d7c9a6}.detail-list i.done{border-color:transparent}.detail-list span{flex:1}.detail-list small{color:#9a8b6a}
.preview-box small{display:block;margin-top:8px;color:#7a6a4f;line-height:1.8}.goal-step-title,.success-title{font-size:12.5px;font-weight:800;color:#4b3b22;margin-bottom:2px}.success-title{color:#7a6a4f}.stage-inline{display:flex;gap:6px}.stage-inline input{flex:1;min-width:0}.stage-inline button{width:34px;height:34px;border-radius:8px;border:1.5px solid #3a2e1f;background:#ffd93d;color:#3a2e1f;cursor:pointer;font-size:16px;flex-shrink:0}.preview-box>div{font-size:12px;font-weight:900;color:#3a2e1f;margin-bottom:6px}.detail-reports{display:grid;gap:12px}.progress-chart{height:120px;display:flex;align-items:end;gap:8px;padding:12px;border:1.5px solid #efe3c4;border-radius:12px;background:#fff}.progress-chart span{flex:1;min-width:16px;border:2px solid #3a2e1f;border-radius:8px 8px 3px 3px;box-shadow:1.5px 1.5px 0 #3a2e1f;transition:height .2s ease}
.detail-progress::before{display:none}.detail-progress{display:flex;align-items:center;gap:12px;background:#f5eedc;border-radius:12px;padding:13px;position:relative;overflow:hidden}.detail-progress>div{flex:1;height:10px;border-radius:6px;background:#f0ebd8;border:1px solid #efe3c4;overflow:hidden}.detail-progress>div i{position:static;display:block;height:100%;max-width:none;border-radius:6px}.detail-progress>b{flex:none;min-width:42px;text-align:left;font-size:14px}
.new-goal-btn,.goal-empty button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;height:auto;padding:8px 22px;border-radius:12px;line-height:1.2;font-family:Lalezar,Vazirmatn,sans-serif;font-size:17px;white-space:nowrap}.new-goal-btn span{display:inline-flex;align-items:center;justify-content:center;font-family:Vazirmatn,sans-serif;font-size:22px;font-weight:900;line-height:1;transform:translateY(-1px)}.goal-empty{display:grid;place-items:center;gap:8px;min-height:300px}.goal-empty button{margin-top:4px;font-size:16px}
.goal-card.menu-open{z-index:80}.goal-card.menu-open .goal-menu{z-index:120}.goal-list{isolation:isolate}.goal-modal.detail{width:min(620px,calc(100vw - 32px));box-sizing:border-box}.detail-tabs{max-width:100%;padding-bottom:4px}.detail-tabs button{flex:0 0 auto}.detail-panel,.detail-list,.detail-reports{min-width:0}.detail-list article{min-width:0}.detail-list span,.detail-list small,.detail-list p{min-width:0;overflow-wrap:anywhere}.milestone-detail-list article button{height:30px;border:1.5px solid #3a2e1f;border-radius:9px;background:#fff;font-family:inherit;font-weight:900;cursor:pointer}
@media(max-width:720px){.goals-shell{padding:18px 10px 70px}.goals-paper{padding:22px 14px;transform:none}.goal-stats{grid-template-columns:1fr 1fr}.goal-card{grid-template-columns:1fr;padding:14px}.goal-icon{width:42px;height:42px}.goal-card footer,.goal-card footer>div{align-items:flex-start;flex-direction:column}.goal-form-grid,.detail-panel{grid-template-columns:1fr}.goal-modal{padding:18px}.goal-modal-backdrop{padding:10px;align-items:start;overflow:auto}.goal-modal.detail{width:100%;max-width:calc(100vw - 20px);max-height:none;margin:10px 0;border-radius:16px;padding:14px;box-shadow:5px 5px 0 rgba(0,0,0,.3)}.detail-head{gap:10px}.detail-head h2{font-size:20px;line-height:1.5}.detail-head p{line-height:1.9}.detail-tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;overflow:visible}.detail-tabs button{width:100%;min-width:0;height:36px;padding:0 8px;font-size:12px}.detail-panel p,.detail-panel div,.detail-list article{padding:10px}.detail-list article{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center}.detail-list article small,.detail-list article p,.milestone-detail-list article button{grid-column:2}.milestone-detail-list article{grid-template-columns:auto minmax(0,1fr);gap:7px}.progress-chart{height:104px;gap:6px;padding:10px}.progress-chart span{min-width:12px}.goal-toolbar{align-items:stretch;flex-direction:column}.goal-toolbar label{justify-content:space-between}.new-goal-btn{width:max-content}}
</style>
