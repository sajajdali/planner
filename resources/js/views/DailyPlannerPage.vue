<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isValidJalaaliDate, jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';
import PersianDatePicker from '../components/PersianDatePicker.vue';

type Category = { id: number; name: string; color: string; soft_color: string; icon: string };
type TaskGroup = { id: number; category_id: number; name: string; color: string; soft_color: string; is_active?: boolean };
type PrioritySetting = { id: number; key: string; label: string; color: string; soft_color: string; is_default?: boolean };
type ExpenseCategory = { id: number; name: string; color: string; soft_color: string; type: 'expense' | 'income'; is_default?: boolean };
type FinancialAccount = { id: number; name: string; color: string; initial_balance: number; income_total: number; expense_total: number; current_balance: number; is_default?: boolean; is_active: boolean };
type Expense = { id: number; expense_category_id: number; financial_account_id: number | null; title: string; amount: number; type: 'expense' | 'income'; expense_date: string; note: string | null; category: ExpenseCategory | null; account: { id: number; name: string; color: string } | null };
type TimeSession = { id: number; started_at: string | null; ended_at: string | null; duration_seconds: number };
type MealEntry = { id: number; title: string; meal_date: string; meal_time: string | null; meal_type: string; note: string | null; status: string; sort_order?: number };
type RoutineItem = { id: number; title: string; color: string; is_default: boolean; done: boolean };
type Routine = { wake_time: string | null; sleep_time: string | null; items: RoutineItem[] };
type Task = {
    id: number;
    category_id: number;
    task_group_id: number | null;
    group?: TaskGroup | null;
    parent_id: number | null;
    task_date: string | null;
    title: string;
    description: string | null;
    planned_start_time: string | null;
    planned_end_time: string | null;
    estimated_minutes: number | null;
    priority: string;
    status: string;
    actual_seconds: number;
    metadata?: Record<string, unknown> | null;
    time_sessions: TimeSession[];
    subtasks: Task[];
};
type FollowUp = { id: number; title: string; person_name: string | null; follow_up_time: string | null; status: string };
type FollowUpDraft = { title: string; person_name: string; follow_up_time: string };
type SubtaskDraft = { id?: number; title: string; planned_start_time: string; planned_end_time: string; priority: string };
type DueNotification = { id: string; kind: 'task' | 'subtask' | 'follow' | 'meal'; title: string; time: string; meta: string; targetId: string; color: string; softColor: string; category_id?: number };
type ActiveTimer = {
    task_id: number;
    task_title: string;
    task_date?: string | null;
    category_id?: number | null;
    category_name?: string | null;
    category_color?: string | null;
    duration_seconds: number;
    status: string;
    started_at?: string;
    local_started_at?: number;
};

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const date = ref(queryDate() ?? tehranDateString());
const jalaliDateInput = ref('');
const dateError = ref('');
const calendarOpen = ref(false);
const calendarRef = ref<HTMLElement | null>(null);
const calendarYear = ref(0);
const calendarMonth = ref(0);
const categories = ref<Category[]>([]);
const taskGroups = ref<TaskGroup[]>([]);
const priorities = ref<PrioritySetting[]>([]);
const tasks = ref<Task[]>([]);
const followUps = ref<FollowUp[]>([]);
const expenseCategories = ref<ExpenseCategory[]>([]);
const financialAccounts = ref<FinancialAccount[]>([]);
const expenses = ref<Expense[]>([]);
const newExpense = ref({ title: '', amount: '', expense_category_id: '', financial_account_id: '', note: '', type: 'expense' as 'expense' | 'income' });
const expenseModal = ref(false);
const financeNotice = ref('');
let financeNoticeTimer: number | undefined;
const meals = ref<MealEntry[]>([]);
const routine = ref<Routine>({ wake_time: null, sleep_time: null, items: [] });
const routineDraft = ref({ wake_time: '', sleep_time: '' });
const routineManagerOpen = ref(false);
const newRoutineTitle = ref('');
const newMeal = ref({ title: '', meal_time: '', meal_type: 'meal', note: '' });
const newNutritionTask = ref({ title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' });
const mealDrafts = ref<Record<number, { title: string; meal_time: string; meal_type: string; note: string }>>({});
const editingMealId = ref<number | null>(null);
const followDrafts = ref<Record<number, FollowUpDraft>>({});
const editingFollowUpId = ref<number | null>(null);
const activeTimer = ref<ActiveTimer | null>(null);
const nowTick = ref(Date.now());
let timerInterval: number | undefined;
const collapsed = ref<Record<number, boolean>>({});
const taskModal = ref(false);
const editingTask = ref<Task | null>(null);
const descriptionModalTask = ref<Task | null>(null);
const referModal = ref(false);
const timeLogTask = ref<Task | null>(null);
const referTaskTarget = ref<Task | null>(null);
const referDateInput = ref('');
const referDateError = ref('');
const referSubmitting = ref(false);
const referCalendarOpen = ref(false);
const referCalendarRef = ref<HTMLElement | null>(null);
const categoryPickerModal = ref(false);
const selectedCategory = ref<number | null>(null);
const newTask = ref({ title: '', planned_start_time: '', planned_end_time: '', estimated_minutes: '', priority: 'medium', description: '', subtasks: '', task_group_id: '' });
const modalSubtasks = ref<SubtaskDraft[]>([]);
const modalSubtaskDraft = ref({ title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' });
const modalSubtasksOpen = ref(false);
const inlineSubtasks = ref<Record<number, { title: string; planned_start_time: string; planned_end_time: string; priority: string }>>({});
const inlineSubtaskOpen = ref<Record<number, boolean>>({});
const draggedTaskId = ref<number | null>(null);
const draggedSubtask = ref<{ parentId: number; subtaskId: number } | null>(null);
const draggedModalSubtaskIndex = ref<number | null>(null);
const draggedMealId = ref<number | null>(null);
const followTitle = ref('');
const followPerson = ref('');
const followTime = ref('');
const review = ref({ achievement: '', improvement_note: '', satisfaction_score: 7, energy_score: 7, focus_score: 7 });
const dailyNote = ref('');
const dailyNoteRef = ref<HTMLTextAreaElement | null>(null);
const noteSaving = ref(false);
const noteSaved = ref(false);
const drawerRef = ref<HTMLElement | null>(null);
const reviewSubmitted = ref(false);
const viewMode = ref<'notebook' | 'table' | 'trello'>('notebook');
const viewMenuOpen = ref(false);
const tableStatusFilter = ref<'all' | 'pending' | 'done'>('all');
const tableFilterOpen = ref(false);
const tableCategoryFilter = ref('');
const tableGroupFilter = ref('');
const seenDueIds = ref<Set<string>>(new Set());

const fallbackPriorities: PrioritySetting[] = [
    { id: 1, key: 'low', label: 'کم', color: '#6B7280', soft_color: '#F0F2F6' },
    { id: 2, key: 'medium', label: 'متوسط', color: '#2563EB', soft_color: '#DBEAFE' },
    { id: 3, key: 'high', label: 'زیاد', color: '#F97316', soft_color: '#FFE4CC' },
    { id: 4, key: 'urgent', label: 'فوری', color: '#DC2626', soft_color: '#FEE2E2' },
];
const mealTypeLabels: Record<string, string> = { breakfast: 'صبحانه', lunch: 'ناهار', dinner: 'شام', snack: 'میان‌وعده', water: 'آب', meal: 'وعده' };
const mealTypeIcon: Record<string, string> = { breakfast: '☀', lunch: '◐', dinner: '☾', snack: '◆', water: '≈', meal: '●' };
const iconMap: Record<string, string> = { briefcase: 'M10 6h4M5 9h14v10H5zM8 9V7a2 2 0 012-2h4a2 2 0 012 2v2', activity: 'M22 12h-4l-3 8-6-16-3 8H2', leaf: 'M5 21c8 0 14-6 14-14V4h-3C8 4 4 8 4 16c0 2 1 4 1 5z', book: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v20H6.5A2.5 2.5 0 014 19.5z', home: 'M3 11l9-8 9 8v10H3z', target: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 18a6 6 0 100-12 6 6 0 000 12zM12 14a2 2 0 100-4 2 2 0 000 4z', calendar: 'M7 3v4M17 3v4M4 9h16M5 5h14v16H5z', clock: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6v6l4 2', star: 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.9 6.4 21.2 7.5 15 3 10.6l6.2-.9z', heart: 'M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 000-7.8z', wallet: 'M3 7h15a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM16 12h3', cart: 'M4 6h2l2 11h11l2-8H7M9 21a1 1 0 100-2 1 1 0 000 2zM18 21a1 1 0 100-2 1 1 0 000 2z', code: 'M8 9l-4 3 4 3M16 9l4 3-4 3M14 5l-4 14', pen: 'M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16zM13 7l4 4', phone: 'M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7l.5 2.6a2 2 0 01-.6 1.9L7.8 9.4a16 16 0 006.8 6.8l1.2-1.2a2 2 0 011.9-.6l2.6.5a2 2 0 011.7 2z', users: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8', music: 'M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zM21 16a3 3 0 11-6 0 3 3 0 016 0z', camera: 'M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2zM12 17a4 4 0 100-8 4 4 0 000 8z', plane: 'M22 2L11 13M22 2l-7 20-4-9-9-4z', gift: 'M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 100-5C13 2 12 7 12 7z', shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', coffee: 'M17 8h1a4 4 0 010 8h-1M3 8h14v5a6 6 0 01-6 6H9a6 6 0 01-6-6zM6 2v3M10 2v3M14 2v3', sparkles: 'M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5zM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8zM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8z', map: 'M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3zM9 3v15M15 6v15', folder: 'M3 5h7l2 3h9v11H3z', zap: 'M13 2L3 14h8l-1 8 10-12h-8z', sun: 'M12 18a6 6 0 100-12 6 6 0 000 12zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4', moon: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z', check: 'M20 6L9 17l-5-5', flag: 'M4 22V4h12l-1 4 1 4H4' };
const viewOptions = [
    { value: 'notebook', label: 'دفترچه‌ای', icon: 'book', hint: 'نمای رنگی روزانه' },
    { value: 'trello', label: 'ترلو', icon: 'folder', hint: 'کارت‌ها کنار هم' },
    { value: 'table', label: 'جدولی ساده', icon: 'calendar', hint: 'مرتب و فشرده' },
] as const;
const currentViewOption = computed(() => viewOptions.find((option) => option.value === viewMode.value) || viewOptions[0]);

function chooseViewMode(mode: 'notebook' | 'table' | 'trello') {
    viewMode.value = mode;
    viewMenuOpen.value = false;
}

function closeViewMenuOnOutsideClick(event: MouseEvent) {
    if (!viewMenuOpen.value) return;
    const target = event.target as Node | null;
    if (target && drawerRef.value?.contains(target)) return;
    viewMenuOpen.value = false;
}

const summary = computed(() => {
    const total = tasks.value.length;
    const done = tasks.value.filter((task) => task.status === 'done').length;
    const remaining = tasks.value.filter((task) => task.status !== 'done').length;
    const actual = tasks.value.reduce((sum, task) => sum + task.actual_seconds, 0);
    const percent = total ? Math.round((done / total) * 100) : 0;
    return { total, done, remaining, actual, percent };
});
const expenseItems = computed(() => expenses.value.filter((expense) => (expense.type ?? 'expense') === 'expense'));
const incomeItems = computed(() => expenses.value.filter((expense) => expense.type === 'income'));
const expenseOnlyCategories = computed(() => expenseCategories.value.filter((category) => (category.type ?? 'expense') === 'expense'));
const incomeOnlyCategories = computed(() => expenseCategories.value.filter((category) => category.type === 'income'));
const activeFinanceCategories = computed(() => newExpense.value.type === 'income' ? incomeOnlyCategories.value : expenseOnlyCategories.value);
const expenseTotal = computed(() => expenseItems.value.reduce((sum, expense) => sum + Number(expense.amount || 0), 0));
const incomeTotal = computed(() => incomeItems.value.reduce((sum, expense) => sum + Number(expense.amount || 0), 0));
const financeBalance = computed(() => incomeTotal.value - expenseTotal.value);
const expenseGroups = computed(() => expenseCategories.value.map((category) => {
    const items = expenseItems.value.filter((expense) => expense.expense_category_id === category.id);
    const incomes = incomeItems.value.filter((expense) => expense.expense_category_id === category.id);
    return {
        ...category,
        count: items.length,
        total: items.reduce((sum, expense) => sum + Number(expense.amount || 0), 0),
        incomeCount: incomes.length,
        incomeTotal: incomes.reduce((sum, expense) => sum + Number(expense.amount || 0), 0),
    };
}));
const financeGroupCards = computed(() => [...expenseGroups.value].sort((a, b) => {
    const aTotal = a.type === 'income' ? a.incomeTotal : a.total;
    const bTotal = b.type === 'income' ? b.incomeTotal : b.total;
    if (Boolean(bTotal) !== Boolean(aTotal)) return Number(Boolean(bTotal)) - Number(Boolean(aTotal));
    if (bTotal !== aTotal) return bTotal - aTotal;
    return a.name.localeCompare(b.name);
}));
const activeFinanceGroupCards = computed(() => financeGroupCards.value.filter((group) => (group.type === 'income' ? group.incomeTotal : group.total) > 0));
const dailyAccountCards = computed(() => financialAccounts.value.map((account) => {
    const accountExpenses = expenseItems.value.filter((expense) => expense.financial_account_id === account.id);
    const accountIncomes = incomeItems.value.filter((expense) => expense.financial_account_id === account.id);

    return {
        ...account,
        dailyExpense: accountExpenses.reduce((sum, expense) => sum + Number(expense.amount || 0), 0),
        dailyIncome: accountIncomes.reduce((sum, expense) => sum + Number(expense.amount || 0), 0),
        dailyCount: accountExpenses.length + accountIncomes.length,
    };
}).filter((account) => account.dailyCount > 0));
const nutritionCategory = computed(() => categories.value.find((category) => category.icon === 'leaf') ?? categories.value.find((category) => category.name.includes('تغذیه')) ?? null);
const selectedCategoryGroups = computed(() => selectedCategory.value ? taskGroups.value.filter((group) => group.category_id === selectedCategory.value && group.is_active !== false) : []);
const tableFilteredCategories = computed(() => {
    if (!tableCategoryFilter.value) return categories.value;
    return categories.value.filter((category) => String(category.id) === tableCategoryFilter.value);
});
const tableAvailableGroups = computed(() => {
    if (!tableCategoryFilter.value) return [];
    return taskGroups.value.filter((group) => String(group.category_id) === tableCategoryFilter.value && group.is_active !== false);
});
const tableFilteredTaskCount = computed(() => tableFilteredCategories.value.reduce((sum, category) => sum + tableCategoryTasks(category.id).length, 0));
const nutritionTasks = computed(() => nutritionCategory.value ? categoryTasks(nutritionCategory.value.id) : []);
const mealSummary = computed(() => {
    const total = meals.value.length;
    const eaten = meals.value.filter((meal) => meal.status === 'eaten').length;
    const percent = total ? Math.round((eaten / total) * 100) : 0;
    return { total, eaten, percent };
});
const routineSummary = computed(() => {
    const total = routine.value.items.length;
    const done = routine.value.items.filter((item) => item.done).length;
    return { total, done, percent: total ? Math.round((done / total) * 100) : 0 };
});
const dayScore = computed(() => {
    const completionScore = summary.value.percent * 0.55;
    const remainingPenalty = summary.value.total ? (summary.value.remaining / summary.value.total) * 15 : 0;
    const timeScore = Math.min(15, Math.round(summary.value.actual / 1800) * 3);
    const mealScore = mealSummary.value.total ? mealSummary.value.percent * 0.2 : 10;
    return Math.max(0, Math.min(100, Math.round(completionScore + timeScore + mealScore - remainingPenalty)));
});
const dayGrade = computed(() => {
    if (dayScore.value >= 90) return 'عالی';
    if (dayScore.value >= 75) return 'خیلی خوب';
    if (dayScore.value >= 55) return 'قابل قبول';
    return 'نیاز به بهتر شدن';
});
const currentMinutes = computed(() => {
    const now = new Date(nowTick.value);
    return now.getHours() * 60 + now.getMinutes();
});
const currentClock = computed(() => {
    const now = new Date(nowTick.value);
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
});
const isViewingToday = computed(() => date.value === tehranDateString(new Date(nowTick.value)));
const dayPlanTitle = computed(() => isViewingToday.value ? 'برنامه امروز' : 'برنامه روز');
const dueNotifications = computed<DueNotification[]>(() => {
    if (!isViewingToday.value) return [];
    const due: DueNotification[] = [];

    tasks.value.forEach((task) => {
        const category = categories.value.find((item) => item.id === task.category_id);
        const color = category?.color || '#ff8a3d';
        const softColor = category?.soft_color || '#fff3e0';

        if (task.status !== 'done' && task.planned_start_time && minutesFromTime(task.planned_start_time) <= currentMinutes.value) {
            due.push({
                id: `task-${task.id}`,
                kind: 'task',
                title: task.title,
                time: task.planned_start_time,
                meta: 'وظیفه اصلی',
                targetId: `task-${task.id}`,
                color,
                softColor,
                category_id: task.category_id,
            });
        }

        task.subtasks.forEach((subtask) => {
            if (subtask.status !== 'done' && subtask.planned_start_time && minutesFromTime(subtask.planned_start_time) <= currentMinutes.value) {
                due.push({
                    id: `subtask-${subtask.id}`,
                    kind: 'subtask',
                    title: subtask.title,
                    time: subtask.planned_start_time,
                    meta: `زیروظیفه ${task.title}`,
                    targetId: `subtask-${subtask.id}`,
                    color,
                    softColor,
                    category_id: task.category_id,
                });
            }
        });
    });

    followUps.value.forEach((followUp) => {
        if (followUp.status !== 'done' && followUp.follow_up_time && minutesFromTime(followUp.follow_up_time) <= currentMinutes.value) {
            due.push({
                id: `follow-${followUp.id}`,
                kind: 'follow',
                title: followUp.title,
                time: followUp.follow_up_time,
                meta: followUp.person_name || 'پیگیری',
                targetId: `follow-${followUp.id}`,
                color: '#22d3d0',
                softColor: '#e0fbff',
            });
        }
    });

    meals.value.forEach((meal) => {
        if (meal.status !== 'eaten' && meal.meal_time && minutesFromTime(meal.meal_time) <= currentMinutes.value) {
            due.push({
                id: `meal-${meal.id}`,
                kind: 'meal',
                title: meal.title,
                time: meal.meal_time,
                meta: mealTypeLabels[meal.meal_type] || 'وعده غذایی',
                targetId: `meal-${meal.id}`,
                color: '#fb923c',
                softColor: '#fff7ed',
            });
        }
    });

    return due.sort((a, b) => minutesFromTime(a.time) - minutesFromTime(b.time));
});

const persianDate = computed(() => {
    const [gy, gm, gd] = date.value.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    const weekday = new Intl.DateTimeFormat('fa-IR', { weekday: 'long', timeZone: 'Asia/Tehran' }).format(dateFromYmd(date.value));
    const month = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'][j.jm - 1];
    return `${weekday} ${fa(j.jd)} ${month} ${fa(j.jy)}`;
});
const jalaliMachineDate = computed(() => {
    const [gy, gm, gd] = date.value.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    return `${j.jy}/${String(j.jm).padStart(2, '0')}/${String(j.jd).padStart(2, '0')}`;
});
const activeTimerTask = computed(() => activeTimer.value ? tasks.value.find((task) => task.id === activeTimer.value?.task_id) : null);
const activeTimerCategory = computed(() => {
    if (!activeTimer.value) return null;
    const fromTask = activeTimerTask.value ? categories.value.find((category) => category.id === activeTimerTask.value?.category_id) : null;
    return {
        name: fromTask?.name ?? activeTimer.value.category_name ?? 'بدون گروه',
        color: fromTask?.color ?? activeTimer.value.category_color ?? '#3a2e1f',
    };
});
const activeTimerTaskNumber = computed(() => {
    const timer = activeTimer.value;
    const task = activeTimerTask.value;
    const categoryId = task?.category_id ?? timer?.category_id;
    if (!timer || !categoryId) return '';

    const index = tasks.value.filter((item) => item.category_id === categoryId).findIndex((item) => item.id === timer.task_id);
    return index >= 0 ? fa(index + 1) : '';
});
const activeTimerSeconds = computed(() => {
    const timer = activeTimer.value;
    if (!timer) return 0;
    if (timer.status === 'paused') return timer.duration_seconds;
    return timer.duration_seconds + Math.max(0, Math.floor((nowTick.value - (timer.local_started_at ?? nowTick.value)) / 1000));
});
const activeTimerIsLong = computed(() => activeTimerSeconds.value >= 3 * 60 * 60);
const activeTimerIsFromAnotherDay = computed(() => Boolean(activeTimer.value?.task_date && activeTimer.value.task_date !== date.value));
const activeTimerDockVisible = computed(() => activeTimer.value?.status === 'running');
const activeTimerWarning = computed(() => {
    if (!activeTimer.value) return '';
    if (activeTimerIsFromAnotherDay.value && activeTimerIsLong.value) return 'این تایمر از روز قبل فعال مانده و بیش از ۳ ساعت گذشته؛ لطفاً زمان پایان را بررسی کن.';
    if (activeTimerIsLong.value) return 'این تایمر بیش از ۳ ساعت روشن مانده؛ هنوز ادامه دارد؟';
    if (activeTimerIsFromAnotherDay.value) return 'این تایمر مربوط به روز دیگری است و هنوز بسته نشده.';
    return '';
});
const jalaliMonthName = computed(() => ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'][calendarMonth.value - 1] ?? '');
const calendarDays = computed(() => {
    if (!calendarYear.value || !calendarMonth.value) return [];
    return Array.from({ length: jalaaliMonthLength(calendarYear.value, calendarMonth.value) }, (_, index) => index + 1);
});
const totalPlannedMinutes = computed(() => tasks.value.reduce((sum, task) => {
    if (!task.planned_start_time || !task.planned_end_time) return sum;
    return sum + plannedMinutes(task);
}, 0));
const reportStats = computed(() => {
    const actualMinutes = Math.round(summary.value.actual / 60);
    const diff = actualMinutes - totalPlannedMinutes.value;

    return [
        { label: 'کل وظایف', value: fa(summary.value.total), color: '#3A2E1F' },
        { label: 'انجام‌شده', value: fa(summary.value.done), color: '#16A34A' },
        { label: 'باقی‌مانده', value: fa(summary.value.remaining), color: '#FF8A3D' },
        { label: 'درصد تکمیل', value: `${fa(summary.value.percent)}٪`, color: '#D63384' },
        { label: 'زمان واقعی', value: timeLabel(summary.value.actual), color: '#3A2E1F' },
        { label: 'اختلاف با برنامه', value: `${diff >= 0 ? '+' : ''}${fa(diff)} دقیقه`, color: diff > 0 ? '#DC2626' : '#16A34A' },
    ];
});
const categoryTimeShares = computed(() => categories.value.map((category) => ({
    id: category.id,
    name: category.name,
    color: category.color,
    seconds: categoryTasks(category.id).reduce((sum, task) => sum + task.actual_seconds, 0),
    timeLabel: timeLabel(categoryTasks(category.id).reduce((sum, task) => sum + task.actual_seconds, 0)),
})));
const pieGradient = computed(() => {
    const sumSeconds = categoryTimeShares.value.reduce((sum, item) => sum + item.seconds, 0) || 1;
    let acc = 0;
    const stops = categoryTimeShares.value.map((item) => {
        const start = (acc / sumSeconds) * 100;
        acc += item.seconds;
        const end = (acc / sumSeconds) * 100;
        return `${item.color} ${start}% ${end}%`;
    });
    return `conic-gradient(${stops.join(',')})`;
});
const barChart = computed(() => categories.value.map((category) => {
    const items = categoryTasks(category.id);
    const planned = items.reduce((sum, task) => {
        if (!task.planned_start_time || !task.planned_end_time) return sum;
        return sum + plannedMinutes(task);
    }, 0);
    const actual = items.reduce((sum, task) => sum + task.actual_seconds, 0) / 60;
    const maxValue = Math.max(planned, actual, 120, 1);

    return {
        name: category.name,
        color: category.color,
        plannedH: Math.max(3, Math.round((planned / maxValue) * 100)),
        actualH: Math.max(3, Math.round((actual / maxValue) * 100)),
    };
}));
const expenseReportGroups = computed(() => {
    const total = expenseTotal.value || 0;

    return expenseGroups.value
        .filter((group) => group.total > 0)
        .sort((a, b) => b.total - a.total)
        .map((group) => ({
            ...group,
            percent: total ? Math.round((group.total / total) * 100) : 0,
            amountLabel: moneyLabel(group.total),
        }));
});
const expensePieGradient = computed(() => {
    if (!expenseReportGroups.value.length) return 'conic-gradient(#efe3c4 0 100%)';

    let acc = 0;
    const stops = expenseReportGroups.value.map((group) => {
        const start = acc;
        acc += group.percent;
        return `${group.color} ${start}% ${Math.max(acc, start + 1)}%`;
    });

    return `conic-gradient(${stops.join(',')})`;
});
const expenseBarChart = computed(() => {
    const maxTotal = Math.max(...expenseReportGroups.value.map((group) => group.total), 1);

    return expenseReportGroups.value.map((group) => ({
        ...group,
        height: Math.max(8, Math.round((group.total / maxTotal) * 100)),
    }));
});
const largestExpenses = computed(() => [...expenses.value]
    .sort((a, b) => Number(b.amount || 0) - Number(a.amount || 0))
    .slice(0, 5));
const financeSummaryStats = computed(() => {
    const topExpenseGroup = expenseReportGroups.value[0];

    return [
        { label: 'درآمد', value: moneyLabel(incomeTotal.value), color: '#16A34A' },
        { label: 'هزینه', value: moneyLabel(expenseTotal.value), color: '#DC2626' },
        { label: 'مانده', value: moneyLabel(financeBalance.value), color: financeBalance.value >= 0 ? '#0F766E' : '#DC2626' },
        { label: 'تراکنش‌ها', value: fa(expenses.value.length), color: '#2563EB' },
        { label: 'بیشترین خرج', value: topExpenseGroup?.total ? topExpenseGroup.name : 'ندارد', color: topExpenseGroup?.color ?? '#3A2E1F' },
    ];
});

function fa(input: string | number) {
    return String(input).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function tehranDateString(value = new Date()) {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tehran',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(value).reduce<Record<string, string>>((carry, part) => {
        if (part.type !== 'literal') carry[part.type] = part.value;
        return carry;
    }, {});

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function dateFromYmd(value: string) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day, 12, 0, 0);
}

function shiftYmd(value: string, days: number) {
    const next = dateFromYmd(value);
    next.setDate(next.getDate() + days);
    return localDateString(next);
}

function localDateString(value: Date) {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}

function isIsoDate(value: unknown): value is string {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function queryDate() {
    return isIsoDate(route.query.date) ? route.query.date : null;
}

async function syncDateQuery(value: string, replace = true) {
    const query = { ...route.query, date: value };
    if (route.query.date === value) return;

    if (replace) {
        await router.replace({ path: route.path, query });
    } else {
        await router.push({ path: route.path, query });
    }
}

async function setPlannerDate(value: string, replace = true) {
    if (date.value !== value) {
        date.value = value;
    }

    await syncDateQuery(value, replace);
    await loadPlanner();
}

function en(input: string) {
    return input
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
        .replace(/٬/g, ',');
}

function normalizeLiveInput(event: Event) {
    const target = event.target as HTMLInputElement | HTMLTextAreaElement;
    if (!target || (!['INPUT', 'TEXTAREA'].includes(target.tagName))) return;
    const normalized = en(target.value);
    if (target.value !== normalized) {
        target.value = normalized;
        target.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function timeLabel(seconds: number) {
    const minutes = Math.round(seconds / 60);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h && m) return `${fa(h)} ساعت و ${fa(m)} دقیقه`;
    if (h) return `${fa(h)} ساعت`;
    return `${fa(m)} دقیقه`;
}

function clockLabel(seconds: number) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    const pad = (value: number) => String(value).padStart(2, '0');
    return fa(`${pad(h)}:${pad(m)}:${pad(s)}`);
}

function timeSessionTotal(task: Task | null) {
    return task?.time_sessions?.reduce((sum, session) => sum + Number(session.duration_seconds || 0), 0) ?? 0;
}

function sessionClock(value: string | null) {
    if (!value) return 'ثبت نشده';
    return fa(new Intl.DateTimeFormat('fa-IR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Tehran',
    }).format(new Date(value)));
}

function moneyLabel(amount: number) {
    return `${new Intl.NumberFormat('en-US').format(amount)} تومان`;
}

function formatMoneyInput(value: string) {
    const digits = en(value).replace(/[^\d]/g, '');
    return digits ? new Intl.NumberFormat('en-US').format(Number(digits)) : '';
}

function updateExpenseAmount() {
    newExpense.value.amount = formatMoneyInput(newExpense.value.amount);
}

function syncFinanceCategorySelection() {
    const categories = activeFinanceCategories.value;
    if (!categories.some((category) => String(category.id) === newExpense.value.expense_category_id)) {
        newExpense.value.expense_category_id = categories.length ? String(categories[0].id) : '';
    }
}

function syncFinancialAccountSelection() {
    if (financialAccounts.value.some((account) => String(account.id) === newExpense.value.financial_account_id)) return;
    const defaultAccount = financialAccounts.value.find((account) => account.is_default) ?? financialAccounts.value[0];
    newExpense.value.financial_account_id = defaultAccount ? String(defaultAccount.id) : '';
}

function syncMealDraft(meal: MealEntry) {
    mealDrafts.value[meal.id] = {
        title: meal.title,
        meal_time: meal.meal_time ?? '',
        meal_type: meal.meal_type,
        note: meal.note ?? '',
    };
}

function syncFollowDraft(followUp: FollowUp) {
    followDrafts.value[followUp.id] = {
        title: followUp.title,
        person_name: followUp.person_name ?? '',
        follow_up_time: followUp.follow_up_time ?? '',
    };
}

function taskTimerSeconds(task: Task) {
    if (activeTimer.value?.task_id !== task.id) return 0;
    if (activeTimer.value.status === 'paused') return activeTimer.value.duration_seconds;
    return activeTimer.value.duration_seconds + Math.max(0, Math.floor((nowTick.value - (activeTimer.value.local_started_at ?? nowTick.value)) / 1000));
}

function taskTotalSeconds(task: Task) {
    return task.actual_seconds + taskTimerSeconds(task);
}

function taskPlannedLabel(task: Task) {
    if (task.estimated_minutes) return `${fa(task.estimated_minutes)} دقیقه`;

    if (task.planned_start_time && task.planned_end_time) {
        return `${fa(plannedMinutes(task))} دقیقه`;
    }

    return 'ندارد';
}

function priorityOption(key: string) {
    return priorities.value.find((priority) => priority.key === key)
        ?? fallbackPriorities.find((priority) => priority.key === key)
        ?? { id: 0, key, label: key, color: '#3A2E1F', soft_color: '#F5EEDC' };
}

function priorityLabel(key: string) {
    return priorityOption(key).label;
}

function priorityStyle(key: string) {
    const priority = priorityOption(key);
    return { background: priority.soft_color, color: priority.color, borderColor: priority.color };
}

function taskGroupStyle(task: Task) {
    const group = task.group ?? taskGroups.value.find((item) => item.id === task.task_group_id);
    return group ? { background: group.soft_color, color: group.color, borderColor: group.color } : {};
}

function taskGroupLabel(task: Task) {
    return task.group?.name ?? taskGroups.value.find((item) => item.id === task.task_group_id)?.name ?? '';
}

function plannedMinutes(task: Task) {
    if (!task.planned_start_time || !task.planned_end_time) return 0;
    const start = minutesFromTime(task.planned_start_time);
    const end = minutesFromTime(task.planned_end_time);
    return end >= start ? end - start : (24 * 60) - start + end;
}

function isTaskTimerRunning(task: Task) {
    return activeTimer.value?.task_id === task.id && activeTimer.value.status === 'running';
}

function isTaskTimerPaused(task: Task) {
    return activeTimer.value?.task_id === task.id && activeTimer.value.status === 'paused';
}

function isTaskTimerActive(task: Task) {
    return activeTimer.value?.task_id === task.id && ['running', 'paused'].includes(activeTimer.value.status);
}

function minutesFromTime(time: string) {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
}

function categoryTasks(categoryId: number) {
    return tasks.value.filter((task) => task.category_id === categoryId).map((task) => {
        inlineSubtasks.value[task.id] ||= { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
        return task;
    });
}

function tableCategoryTasks(categoryId: number) {
    const items = categoryTasks(categoryId);
    const groupFiltered = tableGroupFilter.value ? items.filter((task) => String(task.task_group_id || '') === tableGroupFilter.value) : items;
    if (tableStatusFilter.value === 'done') return groupFiltered.filter((task) => task.status === 'done');
    if (tableStatusFilter.value === 'pending') return groupFiltered.filter((task) => task.status !== 'done');
    return groupFiltered;
}

function resetTableFilters() {
    tableStatusFilter.value = 'all';
    tableCategoryFilter.value = '';
    tableGroupFilter.value = '';
}

function categoryStats(categoryId: number) {
    const items = categoryTasks(categoryId);
    const done = items.filter((task) => task.status === 'done').length;
    const percent = items.length ? Math.round((done / items.length) * 100) : 0;
    const seconds = items.reduce((sum, task) => sum + task.actual_seconds, 0);
    const plannedTotalMinutes = items.reduce((sum, task) => {
        if (task.estimated_minutes) return sum + task.estimated_minutes;
        if (task.planned_start_time && task.planned_end_time) {
            return sum + plannedMinutes(task);
        }
        return sum;
    }, 0);
    return { total: items.length, done, percent, seconds, plannedMinutes: plannedTotalMinutes };
}

function openDueNotification(item: DueNotification) {
    if (item.category_id) {
        collapsed.value[item.category_id] = false;
    }

    window.setTimeout(() => {
        const target = document.getElementById(item.targetId);
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.classList.add('due-focus');
        window.setTimeout(() => target?.classList.remove('due-focus'), 1800);
    }, 80);
}

async function loadPlanner() {
    loading.value = true;
    const { data } = await api.get('/daily-planner', { params: { date: date.value } });
    categories.value = data.categories;
    taskGroups.value = data.taskGroups ?? [];
    priorities.value = data.priorities?.length ? data.priorities : fallbackPriorities;
    tasks.value = data.tasks;
    tasks.value.forEach((task) => {
        inlineSubtasks.value[task.id] ||= { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    });
    followUps.value = data.followUps;
    followUps.value.forEach(syncFollowDraft);
    expenseCategories.value = data.expenseCategories;
    financialAccounts.value = data.financialAccounts ?? [];
    expenses.value = data.expenses;
    meals.value = data.meals;
    meals.value.forEach(syncMealDraft);
    dailyNote.value = data.note?.body ?? '';
    noteSaved.value = Boolean(data.note?.updated_at);
    routine.value = data.routine ?? { wake_time: null, sleep_time: null, items: [] };
    routineDraft.value = {
        wake_time: routine.value.wake_time ?? '',
        sleep_time: routine.value.sleep_time ?? '',
    };
    if (!newExpense.value.expense_category_id && expenseCategories.value.length) {
        syncFinanceCategorySelection();
    }
    syncFinancialAccountSelection();
    activeTimer.value = data.activeTimer;
    if (activeTimer.value) {
        activeTimer.value.local_started_at = activeTimer.value.started_at ? new Date(activeTimer.value.started_at).getTime() : Date.now();
    }
    jalaliDateInput.value = fa(jalaliMachineDate.value);
    if (data.review) {
        review.value = {
            achievement: data.review.achievement ?? '',
            improvement_note: data.review.improvement_note ?? '',
            satisfaction_score: data.review.satisfaction_score ?? 7,
            energy_score: data.review.energy_score ?? 7,
            focus_score: data.review.focus_score ?? 7,
        };
        reviewSubmitted.value = Boolean(data.review.achievement || data.review.improvement_note);
    } else {
        reviewSubmitted.value = false;
    }
    loading.value = false;
    await nextTick();
    resizeDailyNote();
    dueNotifications.value.forEach((item) => seenDueIds.value.add(item.id));
}

async function saveDailyNote() {
    noteSaving.value = true;
    const rawBody = dailyNote.value;
    try {
        await api.put('/daily-note', { note_date: date.value, body: rawBody });
        dailyNote.value = rawBody;
        noteSaved.value = true;
        await nextTick();
        resizeDailyNote();
    } finally {
        noteSaving.value = false;
    }
}

function resizeDailyNote() {
    const textarea = dailyNoteRef.value;
    if (!textarea) return;

    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
}

function handleDailyNoteInput() {
    noteSaved.value = false;
    resizeDailyNote();
}

function shiftDate(days: number) {
    void setPlannerDate(shiftYmd(date.value, days), false);
}

function goToday() {
    void setPlannerDate(tehranDateString(), false);
}

function applyJalaliDate() {
    dateError.value = '';
    const normalized = en(jalaliDateInput.value).replace(/-/g, '/').trim();
    const match = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);

    if (!match) {
        dateError.value = 'فرمت تاریخ باید مثل ۱۴۰۵/۰۵/۰۴ باشد.';
        return;
    }

    const [, jy, jm, jd] = match.map(Number);
    if (!isValidJalaaliDate(jy, jm, jd)) {
        dateError.value = 'تاریخ شمسی معتبر نیست.';
        return;
    }

    const g = toGregorian(jy, jm, jd);
    const selectedDate = `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}`;
    calendarYear.value = jy;
    calendarMonth.value = jm;
    calendarOpen.value = false;
    void setPlannerDate(selectedDate, false);
}

function openJalaliCalendar() {
    const [gy, gm, gd] = date.value.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    calendarYear.value = j.jy;
    calendarMonth.value = j.jm;
    calendarOpen.value = true;
}

function changeJalaliMonth(delta: number) {
    let nextMonth = calendarMonth.value + delta;
    let nextYear = calendarYear.value;

    if (nextMonth < 1) {
        nextMonth = 12;
        nextYear -= 1;
    }

    if (nextMonth > 12) {
        nextMonth = 1;
        nextYear += 1;
    }

    calendarYear.value = nextYear;
    calendarMonth.value = nextMonth;
}

function selectJalaliDay(day: number) {
    const g = toGregorian(calendarYear.value, calendarMonth.value, day);
    const selectedDate = `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}`;
    jalaliDateInput.value = fa(`${calendarYear.value}/${String(calendarMonth.value).padStart(2, '0')}/${String(day).padStart(2, '0')}`);
    calendarOpen.value = false;
    void setPlannerDate(selectedDate, false);
}

function isSelectedJalaliDay(day: number) {
    const [gy, gm, gd] = date.value.split('-').map(Number);
    const selected = toJalaali(gy, gm, gd);
    return selected.jy === calendarYear.value && selected.jm === calendarMonth.value && selected.jd === day;
}

function jalaliInputToIso(value: string) {
    const normalized = en(value).replace(/-/g, '/').trim();
    const parts = normalized.split('/').map(Number);
    if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) return null;
    const [jy, jm, jd] = parts;
    if (!isValidJalaaliDate(jy, jm, jd)) return null;
    const g = toGregorian(jy, jm, jd);
    return `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}`;
}

function isoToJalaliInput(value: string) {
    const [gy, gm, gd] = value.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    return fa(`${j.jy}/${String(j.jm).padStart(2, '0')}/${String(j.jd).padStart(2, '0')}`);
}

function closeCalendarOnOutsideClick(event: MouseEvent) {
    if (!calendarOpen.value) return;
    const target = event.target as Node;
    if (calendarRef.value?.contains(target)) return;
    calendarOpen.value = false;
}

function closeReferCalendarOnOutsideClick(event: MouseEvent) {
    if (!referCalendarOpen.value) return;
    const target = event.target as Node;
    if (referCalendarRef.value?.contains(target)) return;
    referCalendarOpen.value = false;
}

function openTaskModal(categoryId: number) {
    editingTask.value = null;
    selectedCategory.value = categoryId;
    newTask.value = { title: '', planned_start_time: '', planned_end_time: '', estimated_minutes: '', priority: 'medium', description: '', subtasks: '', task_group_id: '' };
    modalSubtasks.value = [];
    modalSubtaskDraft.value = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    modalSubtasksOpen.value = false;
    categoryPickerModal.value = false;
    taskModal.value = true;
}

function openEditTaskModal(task: Task) {
    editingTask.value = task;
    selectedCategory.value = task.category_id;
    newTask.value = {
        title: task.title,
        planned_start_time: task.planned_start_time || '',
        planned_end_time: task.planned_end_time || '',
        estimated_minutes: task.estimated_minutes ? String(task.estimated_minutes) : '',
        priority: task.priority || 'medium',
        description: task.description || '',
        subtasks: '',
        task_group_id: task.task_group_id ? String(task.task_group_id) : '',
    };
    modalSubtasks.value = task.subtasks.map((subtask) => ({
        id: subtask.id,
        title: subtask.title,
        planned_start_time: subtask.planned_start_time || '',
        planned_end_time: subtask.planned_end_time || '',
        priority: subtask.priority || 'medium',
    }));
    modalSubtaskDraft.value = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    modalSubtasksOpen.value = Boolean(modalSubtasks.value.length);
    categoryPickerModal.value = false;
    taskModal.value = true;
}

function closeTaskModal() {
    taskModal.value = false;
    editingTask.value = null;
    selectedCategory.value = null;
    newTask.value = { title: '', planned_start_time: '', planned_end_time: '', estimated_minutes: '', priority: 'medium', description: '', subtasks: '', task_group_id: '' };
    modalSubtasks.value = [];
    modalSubtaskDraft.value = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    modalSubtasksOpen.value = false;
}

function openDescriptionModal(task: Task) {
    if (!task.description?.trim()) return;
    descriptionModalTask.value = task;
}

function openReferModal(task: Task) {
    referTaskTarget.value = task;
    referDateInput.value = isoToJalaliInput(shiftYmd(date.value, 1));
    referDateError.value = '';
    referCalendarOpen.value = false;
    referModal.value = true;
}

function closeReferModal() {
    if (referSubmitting.value) return;
    referModal.value = false;
    referTaskTarget.value = null;
    referDateError.value = '';
    referCalendarOpen.value = false;
}

function openReferCalendar() {
    const targetDate = jalaliInputToIso(referDateInput.value) ?? shiftYmd(date.value, 1);
    const [gy, gm, gd] = targetDate.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    calendarYear.value = j.jy;
    calendarMonth.value = j.jm;
    referCalendarOpen.value = true;
}

function selectReferJalaliDay(day: number) {
    referDateInput.value = fa(`${calendarYear.value}/${String(calendarMonth.value).padStart(2, '0')}/${String(day).padStart(2, '0')}`);
    referDateError.value = '';
    referCalendarOpen.value = false;
}

function isSelectedReferJalaliDay(day: number) {
    const targetDate = jalaliInputToIso(referDateInput.value);
    if (!targetDate) return false;
    const [gy, gm, gd] = targetDate.split('-').map(Number);
    const selected = toJalaali(gy, gm, gd);
    return selected.jy === calendarYear.value && selected.jm === calendarMonth.value && selected.jd === day;
}

function openCategoryPicker() {
    if (categories.value.length === 1) {
        openTaskModal(categories.value[0].id);
        return;
    }

    categoryPickerModal.value = true;
}

function addModalSubtask() {
    const title = modalSubtaskDraft.value.title.trim();
    if (!title) return;
    modalSubtasks.value.push({
        title,
        planned_start_time: modalSubtaskDraft.value.planned_start_time,
        planned_end_time: modalSubtaskDraft.value.planned_end_time,
        priority: modalSubtaskDraft.value.priority,
    });
    modalSubtaskDraft.value = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
}

function removeModalSubtask(index: number) {
    modalSubtasks.value.splice(index, 1);
}

function moveModalSubtask(fromIndex: number, toIndex: number) {
    if (fromIndex === toIndex || toIndex < 0 || toIndex >= modalSubtasks.value.length) return;
    const [item] = modalSubtasks.value.splice(fromIndex, 1);
    modalSubtasks.value.splice(toIndex, 0, item);
}

function dropModalSubtask(toIndex: number) {
    if (draggedModalSubtaskIndex.value === null) return;
    moveModalSubtask(draggedModalSubtaskIndex.value, toIndex);
    draggedModalSubtaskIndex.value = null;
}

async function createTask() {
    if (!newTask.value.title || !selectedCategory.value) return;
    const { data } = await api.post('/tasks', {
        ...newTask.value,
        category_id: selectedCategory.value,
        task_group_id: selectedCategoryGroups.value.some((group) => String(group.id) === newTask.value.task_group_id) ? newTask.value.task_group_id : null,
        task_date: date.value,
        estimated_minutes: newTask.value.estimated_minutes || null,
        planned_start_time: newTask.value.planned_start_time || null,
        planned_end_time: newTask.value.planned_end_time || null,
        subtasks: modalSubtasks.value,
    });
    closeTaskModal();
    tasks.value.push(data);
    inlineSubtasks.value[data.id] = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
}

async function updateTask() {
    const task = editingTask.value;
    if (!task || !newTask.value.title || !selectedCategory.value) return;

    const { data } = await api.put(`/tasks/${task.id}`, {
        ...newTask.value,
        category_id: selectedCategory.value,
        task_group_id: selectedCategoryGroups.value.some((group) => String(group.id) === newTask.value.task_group_id) ? newTask.value.task_group_id : null,
        estimated_minutes: newTask.value.estimated_minutes || null,
        planned_start_time: newTask.value.planned_start_time || null,
        planned_end_time: newTask.value.planned_end_time || null,
        subtasks: modalSubtasks.value,
    });

    tasks.value = tasks.value.map((item) => item.id === task.id ? data : item);
    if (activeTimer.value?.task_id === task.id) {
        const category = categories.value.find((item) => item.id === data.category_id);
        activeTimer.value = {
            ...activeTimer.value,
            task_title: data.title,
            category_id: data.category_id,
            category_name: category?.name ?? activeTimer.value.category_name,
            category_color: category?.color ?? activeTimer.value.category_color,
        };
    }
    closeTaskModal();
}

function submitTaskModal() {
    if (editingTask.value) {
        void updateTask();
        return;
    }

    void createTask();
}

async function referTask() {
    const task = referTaskTarget.value;
    if (!task) return;

    referDateError.value = '';
    const targetDate = jalaliInputToIso(referDateInput.value);
    if (!targetDate) {
        referDateError.value = 'تاریخ شمسی معتبر نیست.';
        return;
    }

    referSubmitting.value = true;
    try {
        const { data } = await api.post(`/tasks/${task.id}/refer`, { task_date: targetDate });
        tasks.value = tasks.value.map((item) => item.id === task.id ? data.source : item);
        if (targetDate === date.value) {
            tasks.value.push(data.copy);
            inlineSubtasks.value[data.copy.id] = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
        }
        referSubmitting.value = false;
        closeReferModal();
    } finally {
        referSubmitting.value = false;
    }
}

function isReferred(task: Task) {
    return Boolean(task.metadata?.was_referred || task.metadata?.is_referred_copy);
}

async function toggleTask(task: Task) {
    const shouldComplete = task.status !== 'done';
    if (shouldComplete && isTaskTimerActive(task)) {
        await timer(task, 'stop', true);
        return;
    }

    const { data } = await api.post(`/tasks/${task.id}/complete`, { done: shouldComplete });
    tasks.value = tasks.value.map((item) => item.id === task.id ? data : item);
}

async function reorderTask(categoryId: number, targetTaskId: number) {
    if (!draggedTaskId.value || draggedTaskId.value === targetTaskId) return;

    const current = categoryTasks(categoryId);
    const fromIndex = current.findIndex((task) => task.id === draggedTaskId.value);
    const toIndex = current.findIndex((task) => task.id === targetTaskId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...current];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);

    const otherTasks = tasks.value.filter((task) => task.category_id !== categoryId);
    tasks.value = [...otherTasks, ...reordered];
    draggedTaskId.value = null;

    try {
        await api.post('/tasks/reorder', { task_ids: reordered.map((task) => task.id) });
    } catch (error) {
        await loadPlanner();
        throw error;
    }
}

async function toggleSubtask(subtask: Task) {
    const { data } = await api.post(`/tasks/${subtask.id}/complete`, { done: subtask.status !== 'done' });
    tasks.value = tasks.value.map((task) => task.id === subtask.parent_id ? {
        ...task,
        subtasks: task.subtasks.map((item) => item.id === subtask.id ? data : item),
    } : task);
}

async function reorderSubtask(parent: Task, targetSubtaskId: number) {
    const dragged = draggedSubtask.value;
    if (!dragged || dragged.parentId !== parent.id || dragged.subtaskId === targetSubtaskId) return;

    const fromIndex = parent.subtasks.findIndex((subtask) => subtask.id === dragged.subtaskId);
    const toIndex = parent.subtasks.findIndex((subtask) => subtask.id === targetSubtaskId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...parent.subtasks];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);

    tasks.value = tasks.value.map((task) => task.id === parent.id ? { ...task, subtasks: reordered } : task);
    draggedSubtask.value = null;

    try {
        await api.post('/tasks/reorder', { task_ids: reordered.map((subtask) => subtask.id) });
    } catch (error) {
        await loadPlanner();
        throw error;
    }
}

async function createInlineSubtask(task: Task) {
    const draft = inlineSubtasks.value[task.id] || { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    const title = draft.title.trim();
    if (!title) return;

    const { data } = await api.post('/tasks', {
        category_id: task.category_id,
        parent_id: task.id,
        title,
        task_date: task.task_date ?? date.value,
        priority: draft.priority || 'medium',
        planned_start_time: draft.planned_start_time || null,
        planned_end_time: draft.planned_end_time || null,
    });

    inlineSubtasks.value[task.id] = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    inlineSubtaskOpen.value[task.id] = false;
    tasks.value = tasks.value.map((item) => item.id === task.id ? { ...item, subtasks: [...item.subtasks, data] } : item);
}

async function createNutritionTask() {
    const category = nutritionCategory.value;
    const title = newNutritionTask.value.title.trim();
    if (!category || !title) return;

    const { data } = await api.post('/tasks', {
        category_id: category.id,
        title,
        task_date: date.value,
        priority: newNutritionTask.value.priority,
        planned_start_time: newNutritionTask.value.planned_start_time || null,
        planned_end_time: newNutritionTask.value.planned_end_time || null,
    });

    tasks.value.push(data);
    inlineSubtasks.value[data.id] = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
    newNutritionTask.value = { title: '', planned_start_time: '', planned_end_time: '', priority: 'medium' };
}

async function deleteTask(task: Task, parent?: Task) {
    const message = parent ? 'این زیروظیفه حذف شود؟' : 'این وظیفه و زیروظیفه‌هایش حذف شود؟';
    if (!window.confirm(message)) return;

    await api.delete(`/tasks/${task.id}`);

    if (parent) {
        tasks.value = tasks.value.map((item) => item.id === parent.id ? { ...item, subtasks: item.subtasks.filter((subtask) => subtask.id !== task.id) } : item);
        return;
    }

    tasks.value = tasks.value.filter((item) => item.id !== task.id);
}

async function timer(task: Task, action: string, completeOnStop = false) {
    if (action === 'start') {
        activeTimer.value = {
            task_id: task.id,
            task_title: task.title,
            task_date: task.task_date,
            category_id: task.category_id,
            category_name: categories.value.find((category) => category.id === task.category_id)?.name,
            category_color: categories.value.find((category) => category.id === task.category_id)?.color,
            duration_seconds: 0,
            status: 'running',
            local_started_at: Date.now(),
        };
    }

    if (action === 'pause' && activeTimer.value?.task_id === task.id) {
        activeTimer.value = {
            ...activeTimer.value,
            duration_seconds: taskTimerSeconds(task),
            status: 'paused',
            local_started_at: Date.now(),
        };
    }

    if (action === 'resume' && activeTimer.value?.task_id === task.id) {
        activeTimer.value = {
            ...activeTimer.value,
            status: 'running',
            local_started_at: Date.now(),
        };
    }

    if (action === 'stop' && activeTimer.value?.task_id === task.id) {
        activeTimer.value = null;
    }

    const { data } = await api.post(`/tasks/${task.id}/timer/${action}`, { complete: action === 'stop' && completeOnStop });
    if (data.task) {
        tasks.value = tasks.value.map((item) => item.id === task.id ? data.task : item);
    } else {
        await loadPlanner();
    }
}

async function stopActiveTimer() {
    const timer = activeTimer.value;
    if (!timer) return;

    activeTimer.value = null;
    const { data } = await api.post(`/tasks/${timer.task_id}/timer/stop`, { complete: false });
    if (data.task) {
        tasks.value = tasks.value.map((item) => item.id === timer.task_id ? data.task : item);
    }
    await loadPlanner();
}

async function focusActiveTimerTask() {
    const timer = activeTimer.value;
    if (!timer) return;
    if (timer.task_date && timer.task_date !== date.value) {
        await setPlannerDate(timer.task_date, true);
        await nextTick();
    }

    const target = document.getElementById(`task-${timer.task_id}`);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('due-focus');
        window.setTimeout(() => target.classList.remove('due-focus'), 1800);
    }
}

async function createFollowUp() {
    if (!followTitle.value) return;
    const { data } = await api.post('/follow-ups', { title: followTitle.value, person_name: followPerson.value, follow_up_time: followTime.value, follow_up_date: date.value });
    followTitle.value = '';
    followPerson.value = '';
    followTime.value = '';
    followUps.value = [...followUps.value, data].sort((a, b) => (a.follow_up_time || '99:99').localeCompare(b.follow_up_time || '99:99'));
    syncFollowDraft(data);
}

async function toggleFollowUp(id: number) {
    const { data } = await api.post(`/follow-ups/${id}/toggle`);
    followUps.value = followUps.value.map((followUp) => followUp.id === id ? data : followUp);
    syncFollowDraft(data);
}

async function updateFollowUp(followUp: FollowUp) {
    const draft = followDrafts.value[followUp.id];
    if (!draft?.title.trim()) return;

    const { data } = await api.put(`/follow-ups/${followUp.id}`, {
        title: draft.title.trim(),
        person_name: draft.person_name.trim() || null,
        follow_up_time: draft.follow_up_time || null,
    });

    followUps.value = followUps.value
        .map((item) => item.id === followUp.id ? data : item)
        .sort((a, b) => (a.follow_up_time || '99:99').localeCompare(b.follow_up_time || '99:99'));
    syncFollowDraft(data);
    editingFollowUpId.value = null;
}

async function deleteFollowUp(followUp: FollowUp) {
    if (!window.confirm('این پیگیری حذف شود؟')) return;

    await api.delete(`/follow-ups/${followUp.id}`);
    followUps.value = followUps.value.filter((item) => item.id !== followUp.id);
    delete followDrafts.value[followUp.id];
}

async function createExpense() {
    const title = newExpense.value.title.trim();
    const amount = Number(en(newExpense.value.amount).replace(/[,\s]/g, ''));
    const categoryId = Number(newExpense.value.expense_category_id || expenseCategories.value[0]?.id);
    const accountId = Number(newExpense.value.financial_account_id || financialAccounts.value[0]?.id);

    if (!title || !amount || !categoryId || !accountId) return;

    const { data } = await api.post('/expenses', {
        title,
        amount,
        type: newExpense.value.type,
        expense_category_id: categoryId,
        financial_account_id: accountId,
        expense_date: date.value,
        note: newExpense.value.note || null,
    });

    expenses.value = [data, ...expenses.value];
    newExpense.value = {
        title: '',
        amount: '',
        expense_category_id: String(categoryId),
        financial_account_id: String(accountId),
        note: '',
        type: newExpense.value.type,
    };
    expenseModal.value = false;
    await loadPlanner();
}

async function deleteExpense(expense: Expense) {
    if (!window.confirm('این هزینه حذف شود؟')) return;
    const scrollY = window.scrollY;

    await api.delete(`/expenses/${expense.id}`);
    expenses.value = expenses.value.filter((item) => item.id !== expense.id);
    await loadPlanner();
    await nextTick();
    requestAnimationFrame(() => window.scrollTo({ top: scrollY, left: 0, behavior: 'auto' }));

    financeNotice.value = 'حذف شد';
    if (financeNoticeTimer) window.clearTimeout(financeNoticeTimer);
    financeNoticeTimer = window.setTimeout(() => {
        financeNotice.value = '';
    }, 2200);
}

async function createMeal() {
    const title = newMeal.value.title.trim();
    if (!title) return;

    const { data } = await api.post('/meals', {
        title,
        meal_date: date.value,
        meal_time: newMeal.value.meal_time || null,
        meal_type: newMeal.value.meal_type,
        note: newMeal.value.note || null,
    });

    meals.value = [...meals.value, data];
    syncMealDraft(data);
    newMeal.value = { title: '', meal_time: '', meal_type: 'meal', note: '' };
}

async function toggleMeal(meal: MealEntry) {
    const { data } = await api.post(`/meals/${meal.id}/toggle`);
    meals.value = meals.value.map((item) => item.id === meal.id ? data : item);
    syncMealDraft(data);
}

async function updateMeal(meal: MealEntry) {
    const draft = mealDrafts.value[meal.id];
    if (!draft?.title?.trim()) return;

    const { data } = await api.put(`/meals/${meal.id}`, {
        title: draft.title.trim(),
        meal_time: draft.meal_time || null,
        meal_type: draft.meal_type,
        note: draft.note || null,
    });

    meals.value = meals.value.map((item) => item.id === meal.id ? data : item);
    syncMealDraft(data);
    editingMealId.value = null;
}

async function deleteMeal(meal: MealEntry) {
    if (!window.confirm('این وعده غذایی حذف شود؟')) return;

    await api.delete(`/meals/${meal.id}`);
    meals.value = meals.value.filter((item) => item.id !== meal.id);
    delete mealDrafts.value[meal.id];
}

async function reorderMeal(targetMealId: number) {
    if (!draggedMealId.value || draggedMealId.value === targetMealId) return;

    const fromIndex = meals.value.findIndex((meal) => meal.id === draggedMealId.value);
    const toIndex = meals.value.findIndex((meal) => meal.id === targetMealId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...meals.value];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    meals.value = reordered;
    draggedMealId.value = null;

    await api.post('/meals/reorder', { meal_ids: reordered.map((meal) => meal.id) });
}

async function saveRoutineTimes() {
    const { data } = await api.put('/routine', {
        routine_date: date.value,
        wake_time: routineDraft.value.wake_time || null,
        sleep_time: routineDraft.value.sleep_time || null,
    });
    routine.value = data;
    routineDraft.value = {
        wake_time: data.wake_time ?? '',
        sleep_time: data.sleep_time ?? '',
    };
}

async function toggleRoutine(item: RoutineItem) {
    const { data } = await api.post(`/routine-items/${item.id}/toggle`, {
        routine_date: date.value,
        done: !item.done,
    });
    routine.value = data;
}

async function createRoutineItem() {
    const title = newRoutineTitle.value.trim();
    if (!title) return;

    const { data } = await api.post('/routine-items', { title, routine_date: date.value });
    routine.value = data;
    newRoutineTitle.value = '';
}

async function deleteRoutineItem(item: RoutineItem) {
    if (!window.confirm('این مورد از روتین حذف شود؟')) return;

    const { data } = await api.delete(`/routine-items/${item.id}`, { params: { date: date.value } });
    routine.value = data;
}

async function submitReview() {
    const autoReview = {
        satisfaction_score: Math.max(1, Math.min(10, Math.round(dayScore.value / 10))),
        energy_score: Math.max(1, Math.min(10, Math.round((summary.value.percent * 0.7 + mealSummary.value.percent * 0.3) / 10))),
        focus_score: Math.max(1, Math.min(10, Math.round((summary.value.done * 2 + Math.min(summary.value.actual / 1800, 5))))),
    };
    const { data } = await api.post('/daily-reviews', { ...review.value, ...autoReview, review_date: date.value, completion_percentage: dayScore.value });
    review.value = {
        achievement: data.achievement ?? '',
        improvement_note: data.improvement_note ?? '',
        satisfaction_score: data.satisfaction_score ?? 7,
        energy_score: data.energy_score ?? 7,
        focus_score: data.focus_score ?? 7,
    };
    reviewSubmitted.value = true;
}

watch(() => newExpense.value.type, () => {
    syncFinanceCategorySelection();
});

watch(tableCategoryFilter, () => {
    tableGroupFilter.value = '';
});

watch(selectedCategory, () => {
    if (!taskModal.value) return;
    if (!selectedCategoryGroups.value.some((group) => String(group.id) === newTask.value.task_group_id)) {
        newTask.value.task_group_id = '';
    }
});

watch(dueNotifications, (items) => {
    items.forEach((item) => seenDueIds.value.add(item.id));
});

watch(dailyNote, async () => {
    await nextTick();
    resizeDailyNote();
});

watch(() => route.query.date, (value) => {
    if (!isIsoDate(value) || value === date.value) return;
    date.value = value;
    void loadPlanner();
});

onMounted(() => {
    document.addEventListener('input', normalizeLiveInput, true);
    document.addEventListener('click', closeCalendarOnOutsideClick, true);
    document.addEventListener('click', closeReferCalendarOnOutsideClick, true);
    document.addEventListener('click', closeViewMenuOnOutsideClick, true);
    void syncDateQuery(date.value);
    loadPlanner();
    timerInterval = window.setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    document.removeEventListener('input', normalizeLiveInput, true);
    document.removeEventListener('click', closeCalendarOnOutsideClick, true);
    document.removeEventListener('click', closeReferCalendarOnOutsideClick, true);
    document.removeEventListener('click', closeViewMenuOnOutsideClick, true);
    if (timerInterval) window.clearInterval(timerInterval);
    if (financeNoticeTimer) window.clearTimeout(financeNoticeTimer);
});
</script>

<template>
    <div class="notebook-shell" :class="{ 'has-active-timer': activeTimerDockVisible }" dir="rtl">
        <div v-if="financeNotice" class="finance-toast" role="status">{{ financeNotice }}</div>
        <div class="notebook-page">
            <i class="tape tape-yellow"></i>
            <i class="tape tape-cyan"></i>
            <i class="tape tape-pink"></i>

            <header class="notebook-header">
                <div class="mobile-top-clock">
                    {{ fa(currentClock) }}
                </div>
                <div class="notebook-brand">
                    <div class="notebook-logo brand-icon-mark">
                        <img :src="'/brand/bejelo-mark.png'" alt="" />
                        <span>ر</span>
                    </div>
                    <strong>دفتر یادداشت</strong>
                </div>
                <div ref="drawerRef" class="top-tools">
                    <div class="view-switch" :class="{ open: viewMenuOpen }">
                        <span>نمایش</span>
                        <button class="view-switch-trigger" type="button" :aria-expanded="viewMenuOpen" @click="viewMenuOpen = !viewMenuOpen">
                            <i>
                                <svg viewBox="0 0 24 24"><path :d="iconMap[currentViewOption.icon]" /></svg>
                            </i>
                            <b>{{ currentViewOption.label }}</b>
                            <em></em>
                        </button>
                        <div v-if="viewMenuOpen" class="view-switch-menu">
                            <button
                                v-for="option in viewOptions"
                                :key="option.value"
                                type="button"
                                :class="{ active: viewMode === option.value }"
                                @click="chooseViewMode(option.value)"
                            >
                                <i>
                                    <svg viewBox="0 0 24 24"><path :d="iconMap[option.icon]" /></svg>
                                </i>
                                <strong>{{ option.label }}</strong>
                                <small>{{ option.hint }}</small>
                            </button>
                        </div>
                    </div>
                    <AppMenu />
                </div>
            </header>

            <div class="dot-row">
                <span
                    v-for="(color, index) in ['#FFD93D','#FF6FA5','#22D3D0','#FF8A3D','#9B5DE5','#2563EB','#16A34A','#D63384','#FFD93D','#22D3D0','#FF6FA5','#9B5DE5','#FF8A3D','#2563EB','#16A34A','#D63384']"
                    :key="`${color}-${index}`"
                    :style="{ background: color }"
                ></span>
            </div>

            <main>
                <div class="top-clock">
                    {{ fa(currentClock) }}
                </div>

                <section v-if="dueNotifications.length" class="due-alert-board">
                    <div class="due-alert-head">
                        <span></span>
                        <div>
                            <strong>{{ fa(dueNotifications.length) }} مورد موعد گذشته و انجام‌نشده</strong>
                            <small>این کارها از زمان برنامه عقب افتاده‌اند. روی هر مورد بزن تا همان بخش باز شود.</small>
                        </div>
                    </div>
                    <div class="due-alert-list">
                        <button v-for="item in dueNotifications" :key="item.id" :style="{ '--due': item.color, '--due-soft': item.softColor }" @click="openDueNotification(item)">
                            <b>{{ fa(item.time) }}</b>
                            <span>{{ item.title }}</span>
                            <small><mark>عقب‌افتاده</mark>{{ item.meta }}</small>
                        </button>
                    </div>
                </section>

                <section class="day-card">
                    <div><h1>{{ dayPlanTitle }}</h1><p>{{ persianDate }}</p></div>
                    <div ref="calendarRef" class="date-actions">
                        <button title="روز قبل" @click="shiftDate(-1)">‹</button>
                        <button @click="goToday">امروز</button>
                        <button title="روز بعد" @click="shiftDate(1)">›</button>
                        <label class="jalali-picker">
                            <span>تاریخ شمسی</span>
                            <PersianDatePicker :model-value="date" placeholder="۱۴۰۵/۰۵/۰۴" @update:model-value="setPlannerDate($event)" />
                        </label>
                    </div>
                    <p v-if="dateError" class="date-error">{{ dateError }}</p>
                </section>

                <section v-if="loading" class="empty-card">در حال دریافت برنامه...</section>

                <template v-else>
                    <section class="summary-grid">
                        <div class="progress-card">
                            <div class="ring" :style="{ '--p': `${summary.percent * 3.6}deg` }"><span>{{ fa(summary.percent) }}٪</span></div>
                            <p>پیشرفت روز</p>
                        </div>
                        <div class="stat-card"><span>کل وظایف</span><strong>{{ fa(summary.total) }}</strong></div>
                        <div class="stat-card green"><span>انجام‌شده</span><strong>{{ fa(summary.done) }}</strong></div>
                        <div class="stat-card orange"><span>باقی‌مانده</span><strong>{{ fa(summary.remaining) }}</strong></div>
                        <div class="stat-card"><span>زمان واقعی</span><strong>{{ timeLabel(summary.actual) }}</strong></div>
                    </section>

                    <section class="routine-card">
                        <div class="routine-glow"></div>
                        <div class="routine-head">
                            <div>
                                <span>روتین روزانه</span>
                                <strong>{{ fa(routineSummary.done) }} از {{ fa(routineSummary.total) }} انجام شده</strong>
                            </div>
                            <div class="routine-ring" :style="{ '--p': `${routineSummary.percent * 3.6}deg` }">
                                <b>{{ fa(routineSummary.percent) }}٪</b>
                            </div>
                        </div>

                        <div class="routine-times">
                            <label>
                                بیداری
                                <input v-model="routineDraft.wake_time" type="time" @change="saveRoutineTimes" />
                            </label>
                            <label>
                                خواب
                                <input v-model="routineDraft.sleep_time" type="time" @change="saveRoutineTimes" />
                            </label>
                        </div>

                        <div class="routine-checks">
                            <button
                                v-for="item in routine.items"
                                :key="`routine-${item.id}`"
                                type="button"
                                class="routine-check"
                                :class="{ done: item.done }"
                                :style="{ '--c': item.color }"
                                @click="toggleRoutine(item)"
                            >
                                <i></i>
                                <span>{{ item.title }}</span>
                            </button>
                        </div>

                        <div class="routine-manager">
                            <button type="button" @click="routineManagerOpen = !routineManagerOpen">
                                {{ routineManagerOpen ? 'بستن مدیریت' : 'مدیریت روتین‌ها' }}
                            </button>
                            <div v-if="routineManagerOpen" class="routine-manage-panel">
                                <div class="routine-add">
                                    <input v-model="newRoutineTitle" placeholder="مورد جدید..." @keyup.enter="createRoutineItem" />
                                    <button type="button" @click="createRoutineItem">افزودن</button>
                                </div>
                                <article v-for="item in routine.items" :key="`routine-manage-${item.id}`" :style="{ '--c': item.color }">
                                    <i></i>
                                    <span>{{ item.title }}</span>
                                    <small v-if="item.is_default">پیش‌فرض</small>
                                    <button type="button" @click="deleteRoutineItem(item)">حذف</button>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section v-if="viewMode === 'table'" class="table-view-card">
                        <div class="section-head table-head">
                            <strong>نمای جدولی وظیفه‌ها</strong>
                            <div class="table-filter" role="group" aria-label="فیلتر وضعیت وظیفه‌ها">
                                <button :class="{ active: tableStatusFilter === 'all' }" @click="tableStatusFilter = 'all'">همه</button>
                                <button :class="{ active: tableStatusFilter === 'pending' }" @click="tableStatusFilter = 'pending'">مانده</button>
                                <button :class="{ active: tableStatusFilter === 'done' }" @click="tableStatusFilter = 'done'">تمام شده</button>
                            </div>
                            <button class="table-filter-toggle" :class="{ active: tableFilterOpen || tableCategoryFilter || tableGroupFilter }" @click="tableFilterOpen = !tableFilterOpen">
                                فیلتر
                                <b v-if="tableCategoryFilter || tableGroupFilter">•</b>
                            </button>
                            <span>{{ fa(tableFilteredTaskCount) }} وظیفه</span>
                        </div>
                        <div v-if="tableFilterOpen" class="table-advanced-filter">
                            <label>
                                <span>بخش</span>
                                <select v-model="tableCategoryFilter">
                                    <option value="">همه بخش‌ها</option>
                                    <option v-for="category in categories" :key="`table-filter-category-${category.id}`" :value="String(category.id)">{{ category.name }}</option>
                                </select>
                            </label>
                            <label>
                                <span>گروه‌بندی</span>
                                <select v-model="tableGroupFilter" :disabled="!tableCategoryFilter || !tableAvailableGroups.length">
                                    <option value="">{{ tableCategoryFilter ? 'همه گروه‌ها' : 'اول بخش را انتخاب کن' }}</option>
                                    <option v-for="group in tableAvailableGroups" :key="`table-filter-group-${group.id}`" :value="String(group.id)">{{ group.name }}</option>
                                </select>
                            </label>
                            <button type="button" :disabled="!tableCategoryFilter && !tableGroupFilter && tableStatusFilter === 'all'" @click="resetTableFilters">پاک کردن</button>
                        </div>
                        <div v-for="category in tableFilteredCategories" :key="`table-${category.id}`" class="simple-task-table">
                            <div class="simple-table-title" :style="{ '--c': category.color, '--soft': category.soft_color }">
                                <span></span>
                                <strong>{{ category.name }}</strong>
                                <small>{{ fa(tableCategoryTasks(category.id).length) }} مورد</small>
                            </div>
                            <div class="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>عنوان</th>
                                            <th>زمان</th>
                                            <th>اولویت</th>
                                            <th>زیر‌وظیفه</th>
                                            <th>وضعیت</th>
                                            <th>ارجاع</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="(task, index) in tableCategoryTasks(category.id)" :key="`row-group-${task.id}`">
                                            <tr :class="{ done: task.status === 'done', referred: isReferred(task), 'has-subtasks': task.subtasks.length }">
                                                <td>{{ fa(index + 1) }}</td>
                                                <td class="table-task-title">
                                                    <button class="check-btn table-task-check" :class="{ checked: task.status === 'done' }" aria-label="تغییر وضعیت وظیفه" @click.stop="toggleTask(task)">✓</button>
                                                    <strong>{{ task.title }}</strong>
                                                    <span v-if="taskGroupLabel(task)" class="task-group-pill" :style="taskGroupStyle(task)">{{ taskGroupLabel(task) }}</span>
                                                    <small v-if="task.description">{{ task.description }}</small>
                                                    <button v-if="task.description" class="info-icon table-info" title="مشاهده توضیحات" aria-label="مشاهده توضیحات" @click.stop="openDescriptionModal(task)">i</button>
                                                </td>
                                                <td>{{ task.planned_start_time ? `${task.planned_start_time} تا ${task.planned_end_time || '--:--'}` : 'بدون زمان' }}</td>
                                                <td><span class="priority-pill" :style="priorityStyle(task.priority)">{{ priorityLabel(task.priority) }}</span></td>
                                                <td>{{ task.subtasks.length ? `${fa(task.subtasks.filter(s => s.status === 'done').length)} از ${fa(task.subtasks.length)}` : '-' }}</td>
                                                <td>{{ task.status === 'done' ? 'انجام شده' : task.status === 'in_progress' ? 'در حال انجام' : 'مانده' }}</td>
                                                <td><button v-if="!isReferred(task)" class="refer-icon table-refer" title="ارجاع به روز دیگر" aria-label="ارجاع به روز دیگر" @click="openReferModal(task)"><svg viewBox="0 0 24 24"><path d="M7 17L17 7"></path><path d="M10 7h7v7"></path></svg></button><small v-else class="refer-text-badge">ارجاع شد</small></td>
                                            </tr>
                                            <tr v-if="task.subtasks.length" class="table-subtasks-row">
                                                <td></td>
                                                <td colspan="6">
                                                    <div class="table-subtasks">
                                                        <button
                                                            v-for="subtask in task.subtasks"
                                                            :key="`table-subtask-${subtask.id}`"
                                                            :id="`subtask-${subtask.id}`"
                                                            class="table-subtask"
                                                            :class="{ done: subtask.status === 'done' }"
                                                            @click="toggleSubtask(subtask)"
                                                        >
                                                            <span class="table-subtask-check"></span>
                                                            <strong>{{ subtask.title }}</strong>
                                                            <small>{{ subtask.planned_start_time ? `${subtask.planned_start_time} تا ${subtask.planned_end_time || '--:--'}` : 'بدون زمان' }}</small>
                                                            <em class="priority-pill" :style="priorityStyle(subtask.priority)">{{ priorityLabel(subtask.priority) }}</em>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr v-if="!tableCategoryTasks(category.id).length" class="empty-row">
                                            <td colspan="7">برای این بخش وظیفه‌ای ثبت نشده.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section v-if="viewMode === 'trello'" class="trello-board-card">
                        <div class="section-head">
                            <strong>برد ترلو</strong>
                            <span>{{ fa(tasks.length) }} کارت</span>
                        </div>
                        <div class="trello-board" dir="rtl">
                            <section
                                v-for="category in categories"
                                :key="`trello-${category.id}`"
                                class="trello-list"
                                :style="{ '--c': category.color, '--soft': category.soft_color }"
                            >
                                <header class="trello-list-head">
                                    <div>
                                        <span></span>
                                        <strong>{{ category.name }}</strong>
                                    </div>
                                    <small>{{ fa(categoryStats(category.id).done) }}/{{ fa(categoryStats(category.id).total) }}</small>
                                </header>

                                <div class="trello-cards">
                                    <article
                                        v-for="task in categoryTasks(category.id)"
                                        :key="`trello-card-${task.id}`"
                                        :id="`task-${task.id}`"
                                        class="trello-card"
                                        :class="{ done: task.status === 'done', dragging: draggedTaskId === task.id, running: isTaskTimerRunning(task), paused: isTaskTimerPaused(task), referred: isReferred(task) }"
                                        draggable="true"
                                        @dragstart="draggedTaskId = task.id"
                                        @dragover.prevent
                                        @drop="reorderTask(category.id, task.id)"
                                        @dragend="draggedTaskId = null"
                                    >
                                        <div class="trello-card-labels">
                                            <i :style="{ background: category.color }"></i>
                                            <span class="priority-pill" :style="priorityStyle(task.priority)">{{ priorityLabel(task.priority) }}</span>
                                            <span v-if="taskGroupLabel(task)" class="task-group-pill" :style="taskGroupStyle(task)">{{ taskGroupLabel(task) }}</span>
                                            <mark v-if="task.status === 'done'">انجام شد</mark>
                                            <mark v-if="isReferred(task)" class="refer-text-badge" title="ارجاع داده شده">ارجاع شد</mark>
                                            <button v-else class="refer-icon" title="ارجاع به روز دیگر" aria-label="ارجاع به روز دیگر" @click="openReferModal(task)"><svg viewBox="0 0 24 24"><path d="M7 17L17 7"></path><path d="M10 7h7v7"></path></svg></button>
                                            <button v-if="task.description" class="info-icon" title="مشاهده توضیحات" aria-label="مشاهده توضیحات" @click="openDescriptionModal(task)">i</button>
                                        </div>

                                        <div class="trello-card-title">
                                            <button class="check-btn" :class="{ checked: task.status === 'done' }" @click="toggleTask(task)">✓</button>
                                            <strong>{{ task.title }}</strong>
                                        </div>

                                        <p>{{ task.planned_start_time ? `${task.planned_start_time} تا ${task.planned_end_time || ''}` : 'بدون زمان مشخص' }}</p>

                                        <div v-if="task.subtasks.length" class="trello-checklist">
                                            <div>
                                                <span>{{ fa(task.subtasks.filter(s => s.status === 'done').length) }} از {{ fa(task.subtasks.length) }}</span>
                                                <i :style="{ width: `${task.subtasks.length ? Math.round((task.subtasks.filter(s => s.status === 'done').length / task.subtasks.length) * 100) : 0}%` }"></i>
                                            </div>
                                            <button
                                                v-for="subtask in task.subtasks"
                                                :key="`trello-subtask-${subtask.id}`"
                                                :id="`subtask-${subtask.id}`"
                                                :class="{ done: subtask.status === 'done' }"
                                                @click="toggleSubtask(subtask)"
                                            >
                                                <b></b>
                                                <span>{{ subtask.title }}</span>
                                            </button>
                                        </div>

                                        <div class="inline-subtask trello-inline-subtask">
                                            <input v-model="inlineSubtasks[task.id].title" placeholder="زیروظیفه جدید..." @keyup.enter="createInlineSubtask(task)" />
                                            <button @click="createInlineSubtask(task)">＋</button>
                                        </div>

                                        <footer class="trello-card-actions">
                                            <span>{{ timeLabel(taskTotalSeconds(task)) }}</span>
                                            <div>
                                                <button v-if="task.status !== 'done' && !isTaskTimerActive(task)" @click="timer(task, 'start')">شروع</button>
                                                <button v-if="isTaskTimerPaused(task)" @click="timer(task, 'resume')">ادامه</button>
                                                <button v-if="isTaskTimerRunning(task)" @click="timer(task, 'pause')">توقف</button>
                                                <button v-if="isTaskTimerActive(task)" class="stop-btn" @click="timer(task, 'stop')">Stop</button>
                                                <button class="delete-mini" @click="deleteTask(task)">حذف</button>
                                            </div>
                                        </footer>
                                    </article>

                                    <button class="trello-add-card" @click="openTaskModal(category.id)">＋ افزودن کارت</button>
                                    <div v-if="!categoryTasks(category.id).length" class="trello-empty">کارت تازه‌ای اینجا بساز.</div>
                                </div>
                            </section>
                        </div>
                    </section>

                    <template v-if="viewMode === 'notebook'">
                    <section
                        v-for="(category, catIndex) in categories"
                        :key="category.id"
                        class="category-card"
                        :style="{ background: category.color, transform: `rotate(${[-0.6, 0.5, -0.3, 0.7, -0.5][catIndex % 5]}deg)` }"
                    >
                        <div class="category-head" @click="collapsed[category.id] = !collapsed[category.id]">
                            <div class="category-icon" :style="{ background: category.soft_color, color: category.color }">
                                <svg viewBox="0 0 24 24"><path :d="iconMap[category.icon]" /></svg>
                            </div>
                            <div class="category-title">
                                <strong>{{ category.name }}</strong>
                                <span>{{ fa(categoryStats(category.id).done) }} از {{ fa(categoryStats(category.id).total) }} وظیفه انجام شده</span>
                                <div class="bar"><i :style="{ width: `${categoryStats(category.id).percent}%`, background: category.color }"></i></div>
                            </div>
                            <div class="cat-time">
                                <span><b>کارکرد</b><strong>{{ timeLabel(categoryStats(category.id).seconds) }}</strong></span>
                                <span><b>برنامه</b><strong>{{ fa(categoryStats(category.id).plannedMinutes) }} دقیقه</strong></span>
                            </div>
                            <button class="add-btn" :style="{ background: category.soft_color, color: category.color }" @click.stop="openTaskModal(category.id)">＋</button>
                        </div>

                        <div v-if="!collapsed[category.id]" class="task-list">
                            <article
                                v-for="(task, index) in categoryTasks(category.id)"
                                :key="task.id"
                                :id="`task-${task.id}`"
                                class="task-card"
                                :class="{ done: task.status === 'done', dragging: draggedTaskId === task.id, running: isTaskTimerRunning(task), paused: isTaskTimerPaused(task), referred: isReferred(task) }"
                                :style="{ borderRightColor: category.color }"
                                draggable="true"
                                @dragstart="draggedTaskId = task.id"
                                @dragover.prevent
                                @drop="reorderTask(category.id, task.id)"
                                @dragend="draggedTaskId = null"
                            >
                                <div class="task-time-chip" :class="{ live: isTaskTimerRunning(task), paused: isTaskTimerPaused(task) }">
                                    <button type="button" class="time-chip-action" @click="timeLogTask = task"><b>کارکرد</b><strong>{{ timeLabel(taskTotalSeconds(task)) }}</strong></button>
                                    <span><b>برنامه</b><em>{{ taskPlannedLabel(task) }}</em></span>
                                </div>
                                <span class="task-number" :style="{ background: category.color }">{{ fa(index + 1) }}</span>
                                <div class="task-content">
                                    <div class="task-title">
                                        <button class="check-btn title-check" :class="{ checked: task.status === 'done' }" @click="toggleTask(task)">✓</button>
                                        <strong>{{ task.title }}</strong>
                                        <span class="priority-pill" :style="priorityStyle(task.priority)">{{ priorityLabel(task.priority) }}</span>
                                        <span v-if="taskGroupLabel(task)" class="task-group-pill" :style="taskGroupStyle(task)">{{ taskGroupLabel(task) }}</span>
                                        <button class="edit-icon" title="ویرایش وظیفه" aria-label="ویرایش وظیفه" @click.stop="openEditTaskModal(task)"><svg viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16z"></path><path d="M13 7l4 4"></path></svg></button>
                                        <mark v-if="task.status === 'done'" class="done-badge">انجام شد</mark>
                                        <mark v-if="isReferred(task)" class="refer-text-badge" title="ارجاع داده شده">ارجاع شد</mark>
                                        <button v-else class="refer-icon" title="ارجاع به روز دیگر" aria-label="ارجاع به روز دیگر" @click="openReferModal(task)"><svg viewBox="0 0 24 24"><path d="M7 17L17 7"></path><path d="M10 7h7v7"></path></svg></button>
                                        <button v-if="task.description" class="info-icon" title="مشاهده توضیحات" aria-label="مشاهده توضیحات" @click="openDescriptionModal(task)">i</button>
                                    </div>
                                    <p v-if="task.planned_start_time || task.subtasks.length">
                                        <template v-if="task.planned_start_time">{{ `${task.planned_start_time} تا ${task.planned_end_time || ''}` }}</template>
                                        <template v-if="task.planned_start_time && task.subtasks.length"> · </template>
                                        <template v-if="task.subtasks.length">{{ `${fa(task.subtasks.filter(s => s.status === 'done').length)} از ${fa(task.subtasks.length)} زیروظیفه` }}</template>
                                    </p>
                                    <div v-if="task.subtasks.length" class="subtasks subtask-area">
                                        <article
                                            v-for="subtask in task.subtasks"
                                            :key="subtask.id"
                                            :id="`subtask-${subtask.id}`"
                                            class="subtask-card"
                                            :class="{ done: subtask.status === 'done', dragging: draggedSubtask?.subtaskId === subtask.id }"
                                            draggable="true"
                                            @dragstart.stop="draggedSubtask = { parentId: task.id, subtaskId: subtask.id }"
                                            @dragover.prevent
                                            @drop.stop="reorderSubtask(task, subtask.id)"
                                            @dragend="draggedSubtask = null"
                                        >
                                            <button class="check-btn sub-check" :class="{ checked: subtask.status === 'done' }" aria-label="تغییر وضعیت زیروظیفه" @click="toggleSubtask(subtask)"></button>
                                            <div>
                                                <strong>{{ subtask.title }}</strong>
                                                <span>{{ subtask.planned_start_time ? `${subtask.planned_start_time} تا ${subtask.planned_end_time || ''}` : 'بدون زمان' }}</span>
                                            </div>
                                            <em class="priority-pill" :style="priorityStyle(subtask.priority)">{{ priorityLabel(subtask.priority) }}</em>
                                            <button class="delete-dot" title="حذف زیروظیفه" aria-label="حذف زیروظیفه" @click="deleteTask(subtask, task)"></button>
                                        </article>
                                    </div>
                                    <button v-if="task.status !== 'done' && !inlineSubtaskOpen[task.id]" class="subtask-toggle subtask-area" @click="inlineSubtaskOpen[task.id] = true">
                                        <i>+</i><span>زیر وظیفه</span>
                                    </button>
                                    <div v-if="task.status !== 'done' && inlineSubtaskOpen[task.id]" class="inline-subtask subtask-area">
                                        <input v-model="inlineSubtasks[task.id].title" placeholder="زیروظیفه جدید..." @keyup.enter="createInlineSubtask(task)" />
                                        <input v-model="inlineSubtasks[task.id].planned_start_time" type="time" title="شروع" />
                                        <input v-model="inlineSubtasks[task.id].planned_end_time" type="time" title="پایان" />
                                        <select v-model="inlineSubtasks[task.id].priority" title="اولویت">
                                            <option v-for="priority in priorities" :key="`inline-priority-${priority.key}`" :value="priority.key">{{ priority.label }}</option>
                                        </select>
                                        <button @click="createInlineSubtask(task)">＋</button>
                                        <button class="subtask-collapse" title="بستن" aria-label="بستن فرم زیروظیفه" @click="inlineSubtaskOpen[task.id] = false">×</button>
                                    </div>
                                    <div class="task-actions">
                                        <button v-if="task.status !== 'done' && !isTaskTimerActive(task)" class="action-icon start-icon" title="شروع" aria-label="شروع" @click="timer(task, 'start')">
                                            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"></path></svg>
                                        </button>
                                        <span v-if="isTaskTimerActive(task)" class="live-timer"><i></i>{{ clockLabel(taskTimerSeconds(task)) }}</span>
                                        <button v-if="isTaskTimerPaused(task)" class="action-icon start-icon" title="ادامه" aria-label="ادامه" @click="timer(task, 'resume')">
                                            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"></path></svg>
                                        </button>
                                        <button v-if="isTaskTimerActive(task)" class="action-icon stop-btn" title="توقف کامل" aria-label="توقف کامل" @click="timer(task, 'stop')">
                                            <svg viewBox="0 0 24 24"><rect x="7" y="7" width="10" height="10" rx="2"></rect></svg>
                                        </button>
                                        <button v-if="isTaskTimerRunning(task)" class="action-icon pause-icon" title="توقف موقت" aria-label="توقف موقت" @click="timer(task, 'pause')">
                                            <svg viewBox="0 0 24 24"><path d="M8 5v14"></path><path d="M16 5v14"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                <button class="action-icon delete-mini task-delete-edge" title="حذف" aria-label="حذف" @click="deleteTask(task)">
                                    <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                </button>
                            </article>
                        </div>
                    </section>
                    </template>

                    <section class="nutrition-board">
                        <div class="nutrition-hero">
                            <div>
                                <span>تغذیه امروز</span>
                                <strong>{{ fa(mealSummary.eaten) }} از {{ fa(mealSummary.total) }} وعده خورده شده</strong>
                            </div>
                            <div class="nutrition-plate" :style="{ '--p': `${mealSummary.percent * 3.6}deg` }">
                                <b>{{ fa(mealSummary.percent) }}٪</b>
                            </div>
                        </div>

                        <div class="nutrition-stack">
                            <div class="nutrition-panel nutrition-task-panel">
                                <div class="mini-head"><strong>تسک‌های تغذیه</strong><span>{{ fa(nutritionTasks.length) }} مورد</span></div>
                                <div class="nutrition-task-form">
                                    <input v-model="newNutritionTask.title" placeholder="تسک تغذیه جدید..." @keyup.enter="createNutritionTask" />
                                    <input v-model="newNutritionTask.planned_start_time" type="time" title="شروع" />
                                    <input v-model="newNutritionTask.planned_end_time" type="time" title="پایان" />
                                    <select v-model="newNutritionTask.priority" title="اولویت">
                                        <option v-for="priority in priorities" :key="`nutrition-priority-${priority.key}`" :value="priority.key">{{ priority.label }}</option>
                                    </select>
                                    <button @click="createNutritionTask">افزودن تسک</button>
                                </div>
                                <div class="nutrition-task-list">
                                    <article v-for="(task, index) in nutritionTasks" :id="`nutrition-task-${task.id}`" :key="task.id" class="nutrition-task" :class="{ done: task.status === 'done' }">
                                        <span class="nutrition-task-number">{{ fa(index + 1) }}</span>
                                        <button class="check-btn" :class="{ checked: task.status === 'done' }" @click="toggleTask(task)">✓</button>
                                        <div>
                                            <strong>{{ task.title }}</strong>
                                            <span>{{ task.planned_start_time ? `${task.planned_start_time} تا ${task.planned_end_time || ''}` : 'بدون زمان' }} · {{ priorityLabel(task.priority) }}</span>
                                        </div>
                                        <div class="micro-actions">
                                            <button class="micro-icon edit" title="ویرایش تسک تغذیه" aria-label="ویرایش تسک تغذیه" @click="openEditTaskModal(task)">
                                                <svg viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16z"></path><path d="M13 7l4 4"></path></svg>
                                            </button>
                                            <button class="micro-icon danger" title="حذف تسک تغذیه" aria-label="حذف تسک تغذیه" @click="deleteTask(task)">
                                                <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                            </button>
                                        </div>
                                    </article>
                                </div>
                                <div v-if="!nutritionTasks.length" class="nutrition-empty">تسک تغذیه‌ای برای امروز ثبت نشده.</div>
                            </div>

                            <div class="nutrition-panel meal-panel">
                                <div class="mini-head"><strong>وعده‌های غذایی</strong><span>{{ fa(mealSummary.eaten) }} خورده شده</span></div>
                                <div class="meal-form">
                                    <input v-model="newMeal.title" placeholder="مثلاً: سالاد مرغ" @keyup.enter="createMeal" />
                                    <input v-model="newMeal.meal_time" type="time" />
                                    <select v-model="newMeal.meal_type">
                                        <option value="breakfast">صبحانه</option>
                                        <option value="lunch">ناهار</option>
                                        <option value="dinner">شام</option>
                                        <option value="snack">میان‌وعده</option>
                                        <option value="water">آب</option>
                                        <option value="meal">وعده</option>
                                    </select>
                                    <input v-model="newMeal.note" placeholder="یادداشت" @keyup.enter="createMeal" />
                                    <button @click="createMeal">افزودن وعده</button>
                                </div>
                                <div class="meal-list">
                                <article
                                    v-for="meal in meals"
                                    :key="meal.id"
                                    :id="`meal-${meal.id}`"
                                    class="meal-item"
                                    :class="{ eaten: meal.status === 'eaten', dragging: draggedMealId === meal.id }"
                                    draggable="true"
                                    @dragstart="draggedMealId = meal.id"
                                    @dragover.prevent
                                    @drop="reorderMeal(meal.id)"
                                    @dragend="draggedMealId = null"
                                >
                                    <button class="meal-check" @click="toggleMeal(meal)">✓</button>
                                    <i>{{ mealTypeIcon[meal.meal_type] }}</i>
                                    <div v-if="editingMealId !== meal.id" class="meal-summary">
                                        <strong>{{ meal.title }}</strong>
                                        <span>{{ mealTypeLabels[meal.meal_type] || 'وعده' }} · {{ meal.meal_time || 'بدون ساعت' }}<template v-if="meal.note"> · {{ meal.note }}</template></span>
                                    </div>
                                    <div v-else class="meal-edit-grid">
                                        <input v-model="mealDrafts[meal.id].title" placeholder="عنوان وعده" @keyup.enter="updateMeal(meal)" />
                                        <input v-model="mealDrafts[meal.id].meal_time" type="time" @change="updateMeal(meal)" />
                                        <select v-model="mealDrafts[meal.id].meal_type" @change="updateMeal(meal)">
                                            <option value="breakfast">صبحانه</option>
                                            <option value="lunch">ناهار</option>
                                            <option value="dinner">شام</option>
                                            <option value="snack">میان‌وعده</option>
                                            <option value="water">آب</option>
                                            <option value="meal">وعده</option>
                                        </select>
                                        <input v-model="mealDrafts[meal.id].note" placeholder="یادداشت" @keyup.enter="updateMeal(meal)" />
                                        <button class="micro-icon save" title="ذخیره وعده" aria-label="ذخیره وعده" @click="updateMeal(meal)">
                                            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"></path></svg>
                                        </button>
                                    </div>
                                    <div class="micro-actions">
                                        <button v-if="editingMealId !== meal.id" class="micro-icon edit" title="ویرایش وعده" aria-label="ویرایش وعده" @click="editingMealId = meal.id">
                                            <svg viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16z"></path><path d="M13 7l4 4"></path></svg>
                                        </button>
                                        <button v-else class="micro-icon" title="بستن ویرایش" aria-label="بستن ویرایش" @click="editingMealId = null; syncMealDraft(meal)">
                                            <svg viewBox="0 0 24 24"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
                                        </button>
                                        <button class="micro-icon danger" title="حذف وعده" aria-label="حذف وعده" @click="deleteMeal(meal)">
                                            <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                        </button>
                                    </div>
                                </article>
                                </div>
                                <div v-if="!meals.length" class="nutrition-empty">برنامه غذایی امروز را اینجا اضافه کن.</div>
                            </div>
                        </div>
                    </section>

                    <section class="follow-card">
                        <div class="section-head"><strong>زنگ‌ها و پیگیری‌ها</strong><span>{{ fa(followUps.length) }} مورد</span></div>
                        <div class="quick-row">
                            <input v-model="followTitle" placeholder="عنوان پیگیری..." />
                            <input v-model="followPerson" placeholder="شخص/موضوع" />
                            <input v-model="followTime" type="time" title="ساعت پیگیری" />
                            <button @click="createFollowUp">افزودن</button>
                        </div>
                        <div v-for="followUp in followUps" :id="`follow-${followUp.id}`" :key="followUp.id" class="follow-item" :class="{ done: followUp.status === 'done' }">
                            <button class="check-btn" :class="{ checked: followUp.status === 'done' }" @click="toggleFollowUp(followUp.id)">✓</button>
                            <div v-if="editingFollowUpId !== followUp.id" class="follow-summary"><strong>{{ followUp.title }}</strong><span>{{ followUp.person_name || 'بدون شخص' }} · {{ followUp.follow_up_time || 'بدون ساعت' }}</span></div>
                            <div v-else class="follow-edit-grid">
                                <input v-model="followDrafts[followUp.id].title" placeholder="عنوان" @keyup.enter="updateFollowUp(followUp)" />
                                <input v-model="followDrafts[followUp.id].person_name" placeholder="شخص/موضوع" @keyup.enter="updateFollowUp(followUp)" />
                                <input v-model="followDrafts[followUp.id].follow_up_time" type="time" @change="updateFollowUp(followUp)" />
                                <button class="micro-icon save" title="ذخیره پیگیری" aria-label="ذخیره پیگیری" @click="updateFollowUp(followUp)">
                                    <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"></path></svg>
                                </button>
                            </div>
                            <div class="micro-actions">
                                <button v-if="editingFollowUpId !== followUp.id" class="micro-icon edit" title="ویرایش پیگیری" aria-label="ویرایش پیگیری" @click="editingFollowUpId = followUp.id">
                                    <svg viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16z"></path><path d="M13 7l4 4"></path></svg>
                                </button>
                                <button v-else class="micro-icon" title="بستن ویرایش" aria-label="بستن ویرایش" @click="editingFollowUpId = null; syncFollowDraft(followUp)">
                                    <svg viewBox="0 0 24 24"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
                                </button>
                                <button class="micro-icon danger" title="حذف پیگیری" aria-label="حذف پیگیری" @click="deleteFollowUp(followUp)">
                                    <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="expense-card">
                        <div class="expense-head">
                            <div>
                                <span>مالی امروز</span>
                                <strong :class="{ negative: financeBalance < 0 }">{{ moneyLabel(financeBalance) }}</strong>
                            </div>
                            <div class="expense-head-actions">
                                <small>{{ fa(expenses.length) }} تراکنش ثبت شده</small>
                                <button class="expense-circle-btn" type="button" aria-label="ثبت هزینه جدید" @click="expenseModal = true">＋</button>
                            </div>
                        </div>

                        <div class="finance-mini-totals">
                            <div class="income">
                                <span>درآمد</span>
                                <strong>{{ moneyLabel(incomeTotal) }}</strong>
                            </div>
                            <div class="expense">
                                <span>هزینه</span>
                                <strong>{{ moneyLabel(expenseTotal) }}</strong>
                            </div>
                        </div>

                        <div v-if="dailyAccountCards.length" class="finance-section-label">
                            <span>حساب‌های فعال امروز</span>
                            <small>{{ fa(dailyAccountCards.length) }} حساب</small>
                        </div>
                        <div v-if="dailyAccountCards.length" class="finance-account-strip">
                            <div v-for="account in dailyAccountCards" :key="account.id" :style="{ '--c': account.color }">
                                <span>{{ account.name }}</span>
                                <strong>{{ moneyLabel(account.current_balance) }}</strong>
                                <small>واریز {{ moneyLabel(account.dailyIncome) }} · برداشت {{ moneyLabel(account.dailyExpense) }}</small>
                            </div>
                        </div>

                        <div v-if="activeFinanceGroupCards.length" class="finance-section-label">
                            <span>دسته‌بندی امروز</span>
                            <small>{{ fa(activeFinanceGroupCards.length) }} دسته فعال</small>
                        </div>
                        <div v-if="activeFinanceGroupCards.length" class="expense-groups">
                            <div v-for="group in activeFinanceGroupCards" :key="group.id" :class="group.type" :style="{ '--c': group.color, '--soft': group.soft_color }">
                                <i></i>
                                <span>{{ group.name }}</span>
                                <strong>{{ moneyLabel(group.type === 'income' ? group.incomeTotal : group.total) }}</strong>
                                <small>{{ fa(group.type === 'income' ? group.incomeCount : group.count) }} مورد · {{ group.type === 'income' ? 'درآمد' : 'هزینه' }}</small>
                            </div>
                        </div>

                        <div v-if="expenses.length" class="finance-section-label">
                            <span>آخرین تراکنش‌ها</span>
                            <small>{{ fa(expenses.length) }} مورد</small>
                        </div>
                        <div v-if="expenses.length" class="expense-list">
                            <article v-for="expense in expenses" :key="expense.id" :style="{ '--c': expense.category?.color || '#14B8A6', '--soft': expense.category?.soft_color || '#DDFCF7' }">
                                <div>
                                    <strong>{{ expense.title }}</strong>
                                    <span>{{ expense.type === 'income' ? 'درآمد' : 'هزینه' }} · {{ expense.category?.name || 'بدون گروه' }} · {{ expense.account?.name || 'بدون حساب' }}<template v-if="expense.note"> · {{ expense.note }}</template></span>
                                </div>
                                <div class="expense-row-actions">
                                    <b :class="expense.type === 'income' ? 'income' : 'expense'">{{ expense.type === 'income' ? '+' : '-' }} {{ moneyLabel(expense.amount) }}</b>
                                    <button title="حذف هزینه" @click="deleteExpense(expense)">×</button>
                                </div>
                            </article>
                        </div>
                        <div v-else class="expense-empty">هنوز هزینه‌ای برای این روز ثبت نشده.</div>
                        <div class="expense-footer">
                            <span>مانده روز</span>
                            <strong :class="{ negative: financeBalance < 0 }">{{ moneyLabel(financeBalance) }}</strong>
                        </div>
                    </section>

                    <section class="report-card">
                        <h2>گزارش امروز</h2>
                        <div class="report-stats">
                            <div v-for="stat in reportStats" :key="stat.label">
                                <span>{{ stat.label }}</span>
                                <strong :style="{ color: stat.color }">{{ stat.value }}</strong>
                            </div>
                        </div>

                        <div class="report-charts">
                            <div>
                                <p>تقسیم زمان بین دسته‌ها</p>
                                <div class="pie-row">
                                    <div class="time-pie" :style="{ background: pieGradient }"></div>
                                    <div class="pie-legend">
                                        <span v-for="item in categoryTimeShares" :key="item.id">
                                            <i :style="{ background: item.color }"></i>{{ item.name }} · {{ item.timeLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p>زمان هر دسته</p>
                                <div class="bar-chart">
                                    <div v-for="bar in barChart" :key="bar.name">
                                        <div class="bar-pair">
                                            <i class="planned" :style="{ height: `${bar.plannedH}%` }"></i>
                                            <i class="actual" :style="{ height: `${bar.actualH}%`, background: bar.color }"></i>
                                        </div>
                                        <span>{{ bar.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="routine-report">
                            <div class="routine-report-head">
                                <div>
                                    <span>گزارش روتین امروز</span>
                                    <strong>{{ fa(routineSummary.done) }} از {{ fa(routineSummary.total) }} · {{ fa(routineSummary.percent) }}٪</strong>
                                </div>
                                <div class="routine-report-times">
                                    <span>بیداری: <b>{{ routine.wake_time ? fa(routine.wake_time) : 'ثبت نشده' }}</b></span>
                                    <span>خواب: <b>{{ routine.sleep_time ? fa(routine.sleep_time) : 'ثبت نشده' }}</b></span>
                                </div>
                            </div>
                            <div class="routine-report-bar">
                                <i :style="{ width: `${routineSummary.percent}%` }"></i>
                            </div>
                            <div class="routine-report-items">
                                <span
                                    v-for="item in routine.items"
                                    :key="`routine-report-${item.id}`"
                                    :class="{ done: item.done }"
                                    :style="{ '--c': item.color }"
                                >
                                    <i></i>{{ item.title }}
                                </span>
                            </div>
                        </div>

                        <div class="finance-report">
                            <div class="finance-report-head">
                                <div>
                                    <span>گزارش مالی امروز</span>
                                    <strong>{{ moneyLabel(expenseTotal) }}</strong>
                                </div>
                                <small>{{ fa(incomeItems.length) }} درآمد · {{ fa(expenseItems.length) }} هزینه</small>
                            </div>

                            <div class="finance-summary-grid">
                                <div v-for="stat in financeSummaryStats" :key="`finance-stat-${stat.label}`">
                                    <span>{{ stat.label }}</span>
                                    <strong :style="{ color: stat.color }">{{ stat.value }}</strong>
                                </div>
                            </div>

                            <div class="finance-report-grid">
                                <div class="finance-donut-panel">
                                    <p>سهم هر دسته از هزینه‌ها</p>
                                    <div class="finance-donut-row">
                                        <div class="finance-donut" :style="{ background: expensePieGradient }">
                                            <span>{{ expenseTotal ? fa(expenseReportGroups.length) : '۰' }}</span>
                                        </div>
                                        <div class="finance-legend">
                                            <span v-for="group in expenseReportGroups" :key="`finance-legend-${group.id}`">
                                                <i :style="{ background: group.color }"></i>
                                                <b>{{ group.name }}</b>
                                                <em>{{ fa(group.percent) }}٪</em>
                                            </span>
                                            <span v-if="!expenseReportGroups.length" class="finance-empty-line">هزینه‌ای ثبت نشده.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="finance-bars-panel">
                                    <p>مبلغ هر دسته</p>
                                    <div class="finance-bars" :class="{ empty: !expenseBarChart.length }">
                                        <div v-for="group in expenseBarChart" :key="`finance-bar-${group.id}`">
                                            <i :style="{ height: `${group.height}%`, background: group.color }"></i>
                                            <span>{{ group.name }}</span>
                                            <b>{{ group.amountLabel }}</b>
                                        </div>
                                        <div v-if="!expenseBarChart.length" class="finance-empty-bars">بدون هزینه</div>
                                    </div>
                                </div>
                            </div>

                            <div class="finance-breakdown">
                                <article v-for="group in expenseReportGroups" :key="`finance-row-${group.id}`" :style="{ '--c': group.color, '--soft': group.soft_color }">
                                    <div>
                                        <i></i>
                                        <strong>{{ group.name }}</strong>
                                        <span>{{ fa(group.count) }} مورد · {{ fa(group.percent) }}٪ از کل</span>
                                    </div>
                                    <b>{{ group.amountLabel }}</b>
                                </article>
                                <article v-if="!expenseReportGroups.length" class="finance-breakdown-empty">
                                    <span>برای امروز هنوز هزینه‌ای ثبت نشده.</span>
                                </article>
                            </div>

                            <div v-if="largestExpenses.length" class="largest-expenses">
                                <p>بزرگ‌ترین هزینه‌ها</p>
                                <article v-for="expense in largestExpenses" :key="`largest-expense-${expense.id}`">
                                    <span>{{ expense.title }}</span>
                                    <small>{{ expense.type === 'income' ? 'درآمد' : 'هزینه' }} · {{ expense.category?.name || 'بدون گروه' }}</small>
                                    <b :class="expense.type === 'income' ? 'income' : 'expense'">{{ expense.type === 'income' ? '+' : '-' }} {{ moneyLabel(expense.amount) }}</b>
                                </article>
                            </div>
                        </div>

                        <div v-if="reviewSubmitted" class="review-done">
                            <div class="review-ring" :style="{ '--p': `${dayScore * 3.6}deg` }"><span>{{ fa(dayScore) }}</span></div>
                            <div>
                                <strong>امتیاز کامل امروز: {{ fa(dayScore) }} از ۱۰۰ - {{ dayGrade }}</strong>
                                <span>{{ fa(summary.done) }} تسک انجام شده، {{ fa(summary.remaining) }} تسک مانده، {{ fa(mealSummary.eaten) }} وعده خورده شده.</span>
                            </div>
                        </div>

                        <div v-else class="review-form">
                            <div class="review-two">
                                <label>مهم‌ترین دستاورد امروز<input v-model="review.achievement" placeholder="مثلاً: تکمیل صفحه ورود" /></label>
                                <label>چه چیزی باید فردا بهتر شود؟<input v-model="review.improvement_note" placeholder="مثلاً: شروع زودتر تایمر" /></label>
                            </div>
                            <div class="review-grid">
                                <label>رضایت: {{ fa(review.satisfaction_score) }}<input v-model="review.satisfaction_score" type="range" min="1" max="10" /></label>
                                <label>انرژی: {{ fa(review.energy_score) }}<input v-model="review.energy_score" type="range" min="1" max="10" /></label>
                                <label>تمرکز: {{ fa(review.focus_score) }}<input v-model="review.focus_score" type="range" min="1" max="10" /></label>
                            </div>
                            <button class="primary-btn" @click="submitReview">ثبت جمع‌بندی روز</button>
                        </div>
                    </section>

                    <section class="daily-note-card">
                        <div class="daily-note-head">
                            <div>
                                <span>Note</span>
                                <strong>یادداشت آزاد امروز</strong>
                            </div>
                            <button type="button" :disabled="noteSaving" @click="saveDailyNote">
                                {{ noteSaving ? 'در حال ذخیره...' : 'ذخیره' }}
                            </button>
                        </div>
                        <textarea
                            ref="dailyNoteRef"
                            v-model="dailyNote"
                            placeholder="هر چیزی که دوست داری برای امروز بنویس..."
                            rows="1"
                            @input="handleDailyNoteInput"
                            @keydown.meta.enter.prevent="saveDailyNote"
                        ></textarea>
                        <div class="daily-note-foot">
                            <span>{{ noteSaved ? 'ذخیره شده' : 'آماده نوشتن' }}</span>
                            <small>{{ fa(dailyNote.length) }} حرف</small>
                        </div>
                    </section>
                </template>
            </main>
        </div>

        <button class="fab" @click="openCategoryPicker">＋</button>

        <div v-if="categoryPickerModal" class="modal-backdrop">
            <section class="modal-card category-picker-card">
                <div class="category-picker-head">
                    <h2>تسک برای کدام بخش؟</h2>
                    <button type="button" aria-label="بستن" @click="categoryPickerModal = false">×</button>
                </div>
                <div class="category-picker-grid">
                    <button
                        v-for="category in categories"
                        :key="`pick-${category.id}`"
                        type="button"
                        class="category-pick"
                        :style="{ '--c': category.color, '--soft': category.soft_color }"
                        @click="openTaskModal(category.id)"
                    >
                        <span>
                            <svg viewBox="0 0 24 24"><path :d="iconMap[category.icon]" /></svg>
                        </span>
                        <strong>{{ category.name }}</strong>
                        <small>{{ fa(categoryTasks(category.id).length) }} تسک</small>
                    </button>
                </div>
            </section>
        </div>

        <div v-if="taskModal" class="modal-backdrop">
            <form class="modal-card task-modal-card" @submit.prevent="submitTaskModal">
                <h2>{{ editingTask ? 'ویرایش وظیفه' : 'افزودن وظیفه جدید' }}</h2>
                <input v-model="newTask.title" placeholder="عنوان وظیفه" required />
                <select v-model="selectedCategory" required>
                    <option v-for="category in categories" :key="`task-modal-category-${category.id}`" :value="category.id">{{ category.name }}</option>
                </select>
                <select v-if="selectedCategoryGroups.length" v-model="newTask.task_group_id" class="task-group-select">
                    <option value="">بدون گروه‌بندی</option>
                    <option v-for="group in selectedCategoryGroups" :key="`task-modal-group-${group.id}`" :value="String(group.id)">{{ group.name }}</option>
                </select>
                <textarea v-model="newTask.description" placeholder="توضیح کوتاه..." />
                <div class="two-cols"><input v-model="newTask.planned_start_time" type="time" /><input v-model="newTask.planned_end_time" type="time" /></div>
                <div class="two-cols"><input v-model="newTask.estimated_minutes" type="number" placeholder="مدت تخمینی" /><select v-model="newTask.priority"><option v-for="priority in priorities" :key="`task-priority-${priority.key}`" :value="priority.key">{{ priority.label }}</option></select></div>
                <div class="modal-subtasks" :class="{ 'mobile-open': modalSubtasksOpen || modalSubtasks.length }">
                    <div class="modal-subtasks-head">
                        <label>زیروظیفه‌ها</label>
                        <button type="button" class="modal-subtask-toggle" @click="modalSubtasksOpen = !modalSubtasksOpen">
                            <i>{{ modalSubtasksOpen || modalSubtasks.length ? '×' : '+' }}</i>
                            <span>{{ modalSubtasksOpen || modalSubtasks.length ? 'بستن' : 'زیر وظیفه' }}</span>
                        </button>
                    </div>
                    <div
                        v-for="(subtask, index) in modalSubtasks"
                        :key="`${subtask.title}-${index}`"
                        class="modal-subtask-item"
                        draggable="true"
                        @dragstart="draggedModalSubtaskIndex = index"
                        @dragover.prevent
                        @drop="dropModalSubtask(index)"
                        @dragend="draggedModalSubtaskIndex = null"
                    >
                        <b>{{ fa(index + 1) }}</b>
                        <span>{{ subtask.title }}</span>
                        <small>{{ subtask.planned_start_time || 'بدون شروع' }} تا {{ subtask.planned_end_time || 'بدون پایان' }} · {{ priorityLabel(subtask.priority) }}</small>
                        <div class="modal-subtask-move">
                            <button type="button" :disabled="index === 0" @click="moveModalSubtask(index, index - 1)">↑</button>
                            <button type="button" :disabled="index === modalSubtasks.length - 1" @click="moveModalSubtask(index, index + 1)">↓</button>
                        </div>
                        <button type="button" class="modal-subtask-remove" @click="removeModalSubtask(index)">×</button>
                    </div>
                    <div v-if="modalSubtasksOpen || modalSubtasks.length" class="modal-subtask-add">
                        <input v-model="modalSubtaskDraft.title" class="subtask-title-input" placeholder="عنوان زیروظیفه" @keydown.enter.prevent="addModalSubtask" />
                        <div class="subtask-meta-row">
                            <label>
                                شروع
                                <input v-model="modalSubtaskDraft.planned_start_time" type="time" />
                            </label>
                            <label>
                                پایان
                                <input v-model="modalSubtaskDraft.planned_end_time" type="time" />
                            </label>
                            <label>
                                اولویت
                                <select v-model="modalSubtaskDraft.priority">
                                    <option v-for="priority in priorities" :key="`modal-sub-priority-${priority.key}`" :value="priority.key">{{ priority.label }}</option>
                                </select>
                            </label>
                            <button type="button" class="subtask-add-btn" @click="addModalSubtask">افزودن زیروظیفه</button>
                        </div>
                    </div>
                </div>
                <div class="modal-actions"><button type="button" @click="closeTaskModal">انصراف</button><button type="submit">{{ editingTask ? 'ذخیره تغییرات' : 'افزودن وظیفه' }}</button></div>
            </form>
        </div>

        <div v-if="descriptionModalTask" class="modal-backdrop">
            <section class="modal-card description-modal-card">
                <button type="button" class="description-close" aria-label="بستن" @click="descriptionModalTask = null">×</button>
                <div class="description-icon">i</div>
                <h2>{{ descriptionModalTask.title }}</h2>
                <p>{{ descriptionModalTask.description }}</p>
            </section>
        </div>

        <div v-if="timeLogTask" class="modal-backdrop">
            <section class="modal-card time-log-modal">
                <header>
                    <div>
                        <span>گزارش کارکرد</span>
                        <h2>{{ timeLogTask.title }}</h2>
                    </div>
                    <button type="button" aria-label="بستن" @click="timeLogTask = null">×</button>
                </header>

                <div v-if="timeLogTask.time_sessions?.length" class="time-log-table">
                    <div class="time-log-row head"><span>شروع</span><span>پایان</span><span>جمع</span></div>
                    <div v-for="session in timeLogTask.time_sessions" :key="session.id" class="time-log-row">
                        <span>{{ sessionClock(session.started_at) }}</span>
                        <span>{{ sessionClock(session.ended_at) }}</span>
                        <strong>{{ timeLabel(session.duration_seconds) }}</strong>
                    </div>
                    <div class="time-log-total">
                        <span>جمع کل</span>
                        <strong>{{ timeLabel(timeSessionTotal(timeLogTask)) }}</strong>
                    </div>
                </div>
                <div v-else class="time-log-empty">هنوز کارکرد ثبت‌شده‌ای برای این تسک نیست.</div>
            </section>
        </div>

        <div v-if="referModal" class="modal-backdrop">
            <form class="modal-card refer-modal-card" @submit.prevent="referTask">
                <button type="button" class="refer-close" aria-label="بستن" @click="closeReferModal">×</button>
                <div class="refer-modal-icon">
                    <svg viewBox="0 0 24 24"><path d="M7 17L17 7"></path><path d="M10 7h7v7"></path></svg>
                </div>
                <h2>می‌خواهید تسک را به چه روزی منتقل کنید و ارجاع دهید؟</h2>
                <p v-if="referTaskTarget">{{ referTaskTarget.title }}</p>
                <label ref="referCalendarRef" class="refer-date-picker">
                    تاریخ
                    <input v-model="referDateInput" inputmode="numeric" placeholder="۱۴۰۵/۰۵/۰۴" readonly required @click="openReferCalendar" />
                    <div v-if="referCalendarOpen" class="jalali-calendar refer-calendar">
                        <div class="jalali-calendar-head">
                            <button type="button" title="ماه قبل" @click="changeJalaliMonth(-1)">‹</button>
                            <strong>{{ jalaliMonthName }} {{ fa(calendarYear) }}</strong>
                            <button type="button" title="ماه بعد" @click="changeJalaliMonth(1)">›</button>
                        </div>
                        <div class="jalali-weekdays">
                            <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                        </div>
                        <div class="jalali-days">
                            <button v-for="day in calendarDays" :key="`refer-day-${day}`" type="button" :class="{ selected: isSelectedReferJalaliDay(day) }" @click="selectReferJalaliDay(day)">
                                {{ fa(day) }}
                            </button>
                        </div>
                    </div>
                </label>
                <small v-if="referDateError" class="refer-date-error">{{ referDateError }}</small>
                <div class="modal-actions refer-actions">
                    <button type="button" @click="closeReferModal">انصراف</button>
                    <button type="submit" :disabled="referSubmitting">{{ referSubmitting ? 'در حال ثبت...' : 'ثبت ارجاع' }}</button>
                </div>
            </form>
        </div>

        <div v-if="expenseModal" class="modal-backdrop">
            <form class="modal-card expense-modal-card" @submit.prevent="createExpense">
                <div class="expense-modal-head">
                <h2>ثبت تراکنش مالی</h2>
                <button type="button" aria-label="بستن" @click="expenseModal = false">×</button>
            </div>
                <div class="transaction-type-toggle">
                    <button type="button" :class="{ active: newExpense.type === 'expense' }" @click="newExpense.type = 'expense'">هزینه</button>
                    <button type="button" :class="{ active: newExpense.type === 'income' }" @click="newExpense.type = 'income'">درآمد</button>
                </div>
                <label>
                    عنوان
                    <input v-model="newExpense.title" :placeholder="newExpense.type === 'income' ? 'مثلاً حقوق یا فروش' : 'مثلاً خرید روزانه'" required />
                </label>
                <label>
                    مبلغ
                    <span class="money-input modal-money-input">
                        <input v-model="newExpense.amount" inputmode="numeric" placeholder="0" required @input="updateExpenseAmount" />
                        <span>تومان</span>
                    </span>
                </label>
                <label>
                    دسته
                    <select v-model="newExpense.expense_category_id" required>
                        <option v-for="category in activeFinanceCategories" :key="category.id" :value="String(category.id)">
                            {{ category.name }}
                        </option>
                    </select>
                </label>
                <label>
                    حساب
                    <select v-model="newExpense.financial_account_id" required>
                        <option v-for="account in financialAccounts" :key="account.id" :value="String(account.id)">
                            {{ account.name }} - {{ moneyLabel(account.current_balance) }}
                        </option>
                    </select>
                </label>
                <label>
                    توضیحات کامل
                    <textarea v-model="newExpense.note" placeholder="جزئیات کامل این تراکنش..." rows="5"></textarea>
                </label>
                <div class="modal-actions">
                    <button type="button" @click="expenseModal = false">انصراف</button>
                    <button type="submit">ثبت</button>
                </div>
            </form>
        </div>

        <aside
            v-if="activeTimerDockVisible && activeTimer"
            class="active-timer-dock"
            :class="{ warning: activeTimerWarning }"
            :style="{ '--timer-color': activeTimerCategory?.color || '#16a34a' }"
        >
            <div class="timer-dock-main">
                <i>{{ activeTimerTaskNumber }}</i>
                <div>
                    <strong>{{ activeTimer.task_title }}</strong>
                    <small>{{ activeTimerCategory?.name || 'بدون گروه' }}</small>
                </div>
                <b>{{ clockLabel(activeTimerSeconds) }}</b>
            </div>
            <p v-if="activeTimerWarning">{{ activeTimerIsFromAnotherDay ? 'از روز قبل' : '+۳ ساعت' }}</p>
            <div class="timer-dock-actions">
                <button type="button" :title="activeTimerIsFromAnotherDay ? 'رفتن به روز تسک' : 'دیدن تسک'" @click="focusActiveTimerTask">
                    <svg viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"></path></svg>
                </button>
                <button type="button" class="stop" title="توقف و ثبت" @click="stopActiveTimer">
                    <svg viewBox="0 0 24 24"><rect x="7" y="7" width="10" height="10" rx="2"></rect></svg>
                </button>
            </div>
        </aside>
    </div>
</template>
