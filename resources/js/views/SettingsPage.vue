<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type Category = { id: number; name: string; color: string; soft_color: string; icon: string; is_default?: boolean; is_active: boolean };
type CategoryDraft = { name: string; color: string; icon: string; is_active: boolean };
type TaskGroup = { id: number; category_id: number; name: string; color: string; soft_color: string; sort_order?: number; is_active: boolean };
type TaskGroupDraft = { name: string; color: string; is_active: boolean };
type PrioritySetting = { id: number; key: string; label: string; color: string; soft_color: string; is_default?: boolean };
type PriorityDraft = { label: string; color: string };
type FinancialAccount = { id: number; name: string; color: string; initial_balance: number; card_number?: string | null; sheba_number?: string | null; income_total: number; expense_total: number; current_balance: number; is_default?: boolean; is_active: boolean };
type AccountDraft = { name: string; color: string; initial_balance: string; card_number: string; sheba_number: string; is_active: boolean };
type FinanceCategory = { id: number; name: string; color: string; soft_color: string; type: 'expense' | 'income'; is_default?: boolean; is_active: boolean };
type FinanceCategoryDraft = { name: string; color: string; is_active: boolean };
type SupportTicket = { id: number; subject: string; body: string; status: 'open' | 'answered'; admin_reply?: string | null; created_at: string; replied_at?: string | null };

const router = useRouter();
const route = useRoute();
const loading = ref(true);
const savingId = ref<number | null>(null);
const savingPriorityId = ref<number | null>(null);
const categories = ref<Category[]>([]);
const taskGroups = ref<TaskGroup[]>([]);
const priorities = ref<PrioritySetting[]>([]);
const accounts = ref<FinancialAccount[]>([]);
const financeCategories = ref<FinanceCategory[]>([]);
const supportTickets = ref<SupportTicket[]>([]);
const drafts = ref<Record<number, CategoryDraft>>({});
const taskGroupDrafts = ref<Record<number, TaskGroupDraft>>({});
const priorityDrafts = ref<Record<number, PriorityDraft>>({});
const accountDrafts = ref<Record<number, AccountDraft>>({});
const financeCategoryDrafts = ref<Record<number, FinanceCategoryDraft>>({});
const draggedCategoryId = ref<number | null>(null);
const draggedTaskGroupId = ref<number | null>(null);
const draggedAccountId = ref<number | null>(null);
const draggedFinanceCategoryId = ref<number | null>(null);
const expandedIconPicker = ref<Record<string, boolean>>({});
const newCategory = ref<CategoryDraft>({ name: '', color: '#D63384', icon: 'briefcase', is_active: true });
const taskGroupModalCategory = ref<Category | null>(null);
const taskGroupCreateModal = ref(false);
const newTaskGroup = ref({ name: '', color: '#2563EB' });
const newPriority = ref<PriorityDraft>({ label: '', color: '#0F766E' });
const newAccount = ref<AccountDraft>({ name: '', color: '#22D3D0', initial_balance: '', card_number: '', sheba_number: '', is_active: true });
const newFinanceCategory = ref<Record<'expense' | 'income', FinanceCategoryDraft>>({
    expense: { name: '', color: '#F43F5E', is_active: true },
    income: { name: '', color: '#16A34A', is_active: true },
});
const supportForm = ref({ subject: '', body: '' });
const supportSaving = ref(false);
const supportSent = ref(false);
const supportLoading = ref(false);
const supportError = ref('');

const palette = ['#2563EB', '#F97316', '#16A34A', '#9B5DE5', '#22D3D0', '#D63384', '#F43F5E', '#0F766E', '#0891B2', '#7C3AED', '#EA580C', '#65A30D', '#BE123C', '#475569'];
const financeTypes = ['expense', 'income'] as const;
const iconOptions = [
    { key: 'briefcase', label: 'کاری', path: 'M10 6h4M5 9h14v10H5zM8 9V7a2 2 0 012-2h4a2 2 0 012 2v2' },
    { key: 'activity', label: 'ورزش', path: 'M22 12h-4l-3 8-6-16-3 8H2' },
    { key: 'leaf', label: 'تغذیه', path: 'M5 21c8 0 14-6 14-14V4h-3C8 4 4 8 4 16c0 2 1 4 1 5z' },
    { key: 'book', label: 'آموزش', path: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v20H6.5A2.5 2.5 0 014 19.5z' },
    { key: 'home', label: 'زندگی', path: 'M3 11l9-8 9 8v10H3z' },
    { key: 'target', label: 'هدف', path: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 18a6 6 0 100-12 6 6 0 000 12zM12 14a2 2 0 100-4 2 2 0 000 4z' },
    { key: 'calendar', label: 'تقویم', path: 'M7 3v4M17 3v4M4 9h16M5 5h14v16H5z' },
    { key: 'clock', label: 'زمان', path: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6v6l4 2' },
    { key: 'star', label: 'ستاره', path: 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.9 6.4 21.2 7.5 15 3 10.6l6.2-.9z' },
    { key: 'heart', label: 'قلب', path: 'M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 000-7.8z' },
    { key: 'wallet', label: 'مالی', path: 'M3 7h15a3 3 0 013 3v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM16 12h3' },
    { key: 'cart', label: 'خرید', path: 'M4 6h2l2 11h11l2-8H7M9 21a1 1 0 100-2 1 1 0 000 2zM18 21a1 1 0 100-2 1 1 0 000 2z' },
    { key: 'code', label: 'کدنویسی', path: 'M8 9l-4 3 4 3M16 9l4 3-4 3M14 5l-4 14' },
    { key: 'pen', label: 'نوشتن', path: 'M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16zM13 7l4 4' },
    { key: 'phone', label: 'تماس', path: 'M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7l.5 2.6a2 2 0 01-.6 1.9L7.8 9.4a16 16 0 006.8 6.8l1.2-1.2a2 2 0 011.9-.6l2.6.5a2 2 0 011.7 2z' },
    { key: 'users', label: 'تیم', path: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8' },
    { key: 'music', label: 'موسیقی', path: 'M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zM21 16a3 3 0 11-6 0 3 3 0 016 0z' },
    { key: 'camera', label: 'عکس', path: 'M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2zM12 17a4 4 0 100-8 4 4 0 000 8z' },
    { key: 'plane', label: 'سفر', path: 'M22 2L11 13M22 2l-7 20-4-9-9-4z' },
    { key: 'gift', label: 'هدیه', path: 'M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 100-5C13 2 12 7 12 7z' },
    { key: 'shield', label: 'امنیت', path: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' },
    { key: 'coffee', label: 'استراحت', path: 'M17 8h1a4 4 0 010 8h-1M3 8h14v5a6 6 0 01-6 6H9a6 6 0 01-6-6zM6 2v3M10 2v3M14 2v3' },
    { key: 'sparkles', label: 'ایده', path: 'M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5zM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8zM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8z' },
    { key: 'map', label: 'مسیر', path: 'M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3zM9 3v15M15 6v15' },
    { key: 'folder', label: 'پرونده', path: 'M3 5h7l2 3h9v11H3z' },
    { key: 'zap', label: 'سریع', path: 'M13 2L3 14h8l-1 8 10-12h-8z' },
    { key: 'sun', label: 'روزانه', path: 'M12 18a6 6 0 100-12 6 6 0 000 12zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4' },
    { key: 'moon', label: 'شب', path: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z' },
    { key: 'check', label: 'انجام', path: 'M20 6L9 17l-5-5' },
    { key: 'flag', label: 'مرحله', path: 'M4 22V4h12l-1 4 1 4H4' },
];

const activePreview = computed(() => categories.value.map((category) => ({
    ...category,
    iconPath: iconOptions.find((icon) => icon.key === category.icon)?.path ?? iconOptions[0].path,
})).filter((category) => category.is_active));
const allCategoryCount = computed(() => categories.value.length);
const activeCategoryCount = computed(() => categories.value.filter((category) => category.is_active).length);
const activeTaskGroupCount = computed(() => taskGroups.value.filter((group) => group.is_active).length);
const financeCategoryGroups = computed(() => ({
    expense: financeCategories.value.filter((category) => category.type === 'expense'),
    income: financeCategories.value.filter((category) => category.type === 'income'),
}));

onMounted(loadCategories);

async function loadCategories() {
    loading.value = true;
    const [{ data: categoryData }, { data: taskGroupData }, { data: priorityData }, { data: accountData }, { data: financeCategoryData }] = await Promise.all([
        api.get('/categories', { params: { include_inactive: 1 } }),
        api.get('/task-groups', { params: { include_inactive: 1 } }),
        api.get('/priorities'),
        api.get('/financial-accounts', { params: { include_inactive: 1 } }),
        api.get('/expense-categories', { params: { include_inactive: 1 } }),
    ]);
    categories.value = categoryData;
    taskGroups.value = taskGroupData;
    priorities.value = priorityData;
    accounts.value = accountData;
    financeCategories.value = financeCategoryData;
    drafts.value = Object.fromEntries(categories.value.map((category) => [category.id, draftFrom(category)]));
    taskGroupDrafts.value = Object.fromEntries(taskGroups.value.map((group) => [group.id, taskGroupDraftFrom(group)]));
    priorityDrafts.value = Object.fromEntries(priorities.value.map((priority) => [priority.id, priorityDraftFrom(priority)]));
    accountDrafts.value = Object.fromEntries(accounts.value.map((account) => [account.id, accountDraftFrom(account)]));
    financeCategoryDrafts.value = Object.fromEntries(financeCategories.value.map((category) => [category.id, financeCategoryDraftFrom(category)]));
    loading.value = false;
    openRequestedTaskGroupModal();
    void loadSupportTickets();
}

function openRequestedTaskGroupModal() {
    const categoryId = Number(route.query.taskGroupCategory || 0);
    if (!categoryId) return;
    const category = categories.value.find((item) => item.id === categoryId);
    if (!category) return;
    openTaskGroupModal(category);
    if (route.query.createTaskGroup) {
        openTaskGroupCreateModal();
    }
}

async function loadSupportTickets() {
    supportLoading.value = true;
    supportError.value = '';
    try {
        const { data } = await api.get('/support-tickets');
        supportTickets.value = data;
    } catch {
        supportError.value = 'تیکت‌ها فعلاً بارگذاری نشدند.';
    } finally {
        supportLoading.value = false;
    }
}

function ticketDate(value?: string | null) {
    if (!value) return '';
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function draftFrom(category: Category): CategoryDraft {
    return { name: category.name, color: category.color, icon: category.icon, is_active: category.is_active };
}

function priorityDraftFrom(priority: PrioritySetting): PriorityDraft {
    return { label: priority.label, color: priority.color };
}

function accountDraftFrom(account: FinancialAccount): AccountDraft {
    return {
        name: account.name,
        color: account.color,
        initial_balance: moneyPlain(account.initial_balance),
        card_number: account.card_number ?? '',
        sheba_number: account.sheba_number ?? '',
        is_active: account.is_active,
    };
}

function financeCategoryDraftFrom(category: FinanceCategory): FinanceCategoryDraft {
    return { name: category.name, color: category.color, is_active: category.is_active };
}

function taskGroupDraftFrom(group: TaskGroup): TaskGroupDraft {
    return { name: group.name, color: group.color, is_active: group.is_active };
}

function moneyPlain(value: number | string) {
    return String(value || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function moneyNumber(value: string) {
    return Number(value.replace(/[,\s]/g, '')) || 0;
}

function iconPath(key: string) {
    return iconOptions.find((icon) => icon.key === key)?.path ?? iconOptions[0].path;
}

function visibleIcons(scope: string) {
    return expandedIconPicker.value[scope] ? iconOptions : iconOptions.slice(0, 10);
}

function toggleIcons(scope: string) {
    expandedIconPicker.value[scope] = !expandedIconPicker.value[scope];
}

function hasChanged(category: Category) {
    const draft = drafts.value[category.id];
    return draft && (draft.name !== category.name || draft.color !== category.color || draft.icon !== category.icon || draft.is_active !== category.is_active);
}

function priorityChanged(priority: PrioritySetting) {
    const draft = priorityDrafts.value[priority.id];
    return draft && (draft.label !== priority.label || draft.color !== priority.color);
}

function accountChanged(account: FinancialAccount) {
    const draft = accountDrafts.value[account.id];
    return draft && (
        draft.name !== account.name ||
        draft.color !== account.color ||
        moneyNumber(draft.initial_balance) !== account.initial_balance ||
        cleanCard(draft.card_number) !== (account.card_number ?? '') ||
        cleanSheba(draft.sheba_number) !== (account.sheba_number ?? '') ||
        draft.is_active !== account.is_active
    );
}

function financeCategoryChanged(category: FinanceCategory) {
    const draft = financeCategoryDrafts.value[category.id];
    return draft && (draft.name !== category.name || draft.color !== category.color || draft.is_active !== category.is_active);
}

function taskGroupsForCategory(categoryId: number) {
    return taskGroups.value.filter((group) => group.category_id === categoryId);
}

function activeGroupsForCategory(categoryId: number) {
    return taskGroupsForCategory(categoryId).filter((group) => group.is_active);
}

function taskGroupChanged(group: TaskGroup) {
    const draft = taskGroupDrafts.value[group.id];
    return draft && (draft.name !== group.name || draft.color !== group.color || draft.is_active !== group.is_active);
}

function cleanCard(value: string) {
    return value.replace(/\D/g, '');
}

function cleanSheba(value: string) {
    return value.replace(/\s/g, '').toUpperCase();
}

async function saveCategory(category: Category) {
    const draft = drafts.value[category.id];
    if (!draft?.name.trim()) return;

    savingId.value = category.id;
    const { data } = await api.put(`/categories/${category.id}`, { ...draft, name: draft.name.trim() });
    categories.value = categories.value.map((item) => item.id === category.id ? data : item);
    drafts.value[data.id] = draftFrom(data);
    savingId.value = null;
}

async function createCategory() {
    if (!newCategory.value.name.trim()) return;

    const { data } = await api.post('/categories', { ...newCategory.value, name: newCategory.value.name.trim() });
    categories.value.push(data);
    drafts.value[data.id] = draftFrom(data);
    newCategory.value = { name: '', color: '#D63384', icon: 'briefcase', is_active: true };
}

async function deleteCategory(category: Category) {
    await api.delete(`/categories/${category.id}`);
    categories.value = categories.value.filter((item) => item.id !== category.id);
}

async function toggleCategory(category: Category) {
    drafts.value[category.id].is_active = !drafts.value[category.id].is_active;
    await saveCategory(category);
}

async function dropCategory(targetId: number) {
    const sourceId = draggedCategoryId.value;
    draggedCategoryId.value = null;
    if (!sourceId || sourceId === targetId) return;

    const fromIndex = categories.value.findIndex((category) => category.id === sourceId);
    const toIndex = categories.value.findIndex((category) => category.id === targetId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...categories.value];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    categories.value = reordered;

    const { data } = await api.post('/categories/reorder', { category_ids: categories.value.map((category) => category.id) });
    categories.value = data;
    drafts.value = Object.fromEntries(categories.value.map((category) => [category.id, draftFrom(category)]));
}

function openTaskGroupModal(category: Category) {
    taskGroupModalCategory.value = category;
    draggedTaskGroupId.value = null;
}

function closeTaskGroupModal() {
    taskGroupModalCategory.value = null;
    taskGroupCreateModal.value = false;
    draggedTaskGroupId.value = null;
}

function openTaskGroupCreateModal() {
    const category = taskGroupModalCategory.value;
    if (!category) return;
    newTaskGroup.value = { name: '', color: category.color };
    taskGroupCreateModal.value = true;
}

function closeTaskGroupCreateModal() {
    taskGroupCreateModal.value = false;
}

async function createTaskGroup() {
    const category = taskGroupModalCategory.value;
    if (!category || !newTaskGroup.value.name.trim()) return;

    const { data } = await api.post('/task-groups', {
        category_id: category.id,
        name: newTaskGroup.value.name.trim(),
        color: newTaskGroup.value.color,
    });
    taskGroups.value.push(data);
    taskGroupDrafts.value[data.id] = taskGroupDraftFrom(data);
    newTaskGroup.value = { name: '', color: category.color };
    taskGroupCreateModal.value = false;
}

async function saveTaskGroup(group: TaskGroup) {
    const draft = taskGroupDrafts.value[group.id];
    if (!draft?.name.trim()) return;

    const { data } = await api.put(`/task-groups/${group.id}`, { ...draft, name: draft.name.trim() });
    taskGroups.value = taskGroups.value.map((item) => item.id === group.id ? data : item);
    taskGroupDrafts.value[data.id] = taskGroupDraftFrom(data);
}

async function toggleTaskGroup(group: TaskGroup) {
    taskGroupDrafts.value[group.id].is_active = !taskGroupDrafts.value[group.id].is_active;
    await saveTaskGroup(group);
}

async function deleteTaskGroup(group: TaskGroup) {
    await api.delete(`/task-groups/${group.id}`);
    taskGroups.value = taskGroups.value.map((item) => item.id === group.id ? { ...item, is_active: false } : item);
    if (taskGroupDrafts.value[group.id]) {
        taskGroupDrafts.value[group.id].is_active = false;
    }
}

async function dropTaskGroup(categoryId: number, targetId: number) {
    const sourceId = draggedTaskGroupId.value;
    draggedTaskGroupId.value = null;
    if (!sourceId || sourceId === targetId) return;

    const sameCategory = taskGroupsForCategory(categoryId);
    const fromIndex = sameCategory.findIndex((group) => group.id === sourceId);
    const toIndex = sameCategory.findIndex((group) => group.id === targetId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...sameCategory];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    taskGroups.value = [
        ...taskGroups.value.filter((group) => group.category_id !== categoryId),
        ...reordered,
    ].sort((a, b) => a.category_id - b.category_id || (a.sort_order ?? 0) - (b.sort_order ?? 0));

    const { data } = await api.post('/task-groups/reorder', { task_group_ids: reordered.map((group) => group.id) });
    taskGroups.value = data;
    taskGroupDrafts.value = Object.fromEntries(taskGroups.value.map((group) => [group.id, taskGroupDraftFrom(group)]));
}

async function savePriority(priority: PrioritySetting) {
    const draft = priorityDrafts.value[priority.id];
    if (!draft?.label.trim()) return;

    savingPriorityId.value = priority.id;
    const { data } = await api.put(`/priorities/${priority.id}`, { ...draft, label: draft.label.trim() });
    priorities.value = priorities.value.map((item) => item.id === priority.id ? data : item);
    priorityDrafts.value[data.id] = priorityDraftFrom(data);
    savingPriorityId.value = null;
}

async function createPriority() {
    if (!newPriority.value.label.trim()) return;

    const { data } = await api.post('/priorities', { ...newPriority.value, label: newPriority.value.label.trim() });
    priorities.value.push(data);
    priorityDrafts.value[data.id] = priorityDraftFrom(data);
    newPriority.value = { label: '', color: '#0F766E' };
}

async function deletePriority(priority: PrioritySetting) {
    await api.delete(`/priorities/${priority.id}`);
    priorities.value = priorities.value.filter((item) => item.id !== priority.id);
}

async function saveAccount(account: FinancialAccount) {
    const draft = accountDrafts.value[account.id];
    if (!draft?.name.trim()) return;

    const { data } = await api.put(`/financial-accounts/${account.id}`, {
        name: draft.name.trim(),
        color: draft.color,
        initial_balance: moneyNumber(draft.initial_balance),
        card_number: cleanCard(draft.card_number),
        sheba_number: cleanSheba(draft.sheba_number),
        is_active: draft.is_active,
    });
    accounts.value = accounts.value.map((item) => item.id === account.id ? data : item);
    accountDrafts.value[data.id] = accountDraftFrom(data);
}

async function createAccount() {
    if (!newAccount.value.name.trim()) return;

    const { data } = await api.post('/financial-accounts', {
        name: newAccount.value.name.trim(),
        color: newAccount.value.color,
        initial_balance: moneyNumber(newAccount.value.initial_balance),
        card_number: cleanCard(newAccount.value.card_number),
        sheba_number: cleanSheba(newAccount.value.sheba_number),
    });
    accounts.value.push(data);
    accountDrafts.value[data.id] = accountDraftFrom(data);
    newAccount.value = { name: '', color: '#22D3D0', initial_balance: '', card_number: '', sheba_number: '', is_active: true };
}

async function deleteAccount(account: FinancialAccount) {
    if (account.is_default) return;
    await api.delete(`/financial-accounts/${account.id}`);
    accounts.value = accounts.value.filter((item) => item.id !== account.id);
}

async function dropAccount(targetId: number) {
    const sourceId = draggedAccountId.value;
    draggedAccountId.value = null;
    if (!sourceId || sourceId === targetId) return;

    const fromIndex = accounts.value.findIndex((account) => account.id === sourceId);
    const toIndex = accounts.value.findIndex((account) => account.id === targetId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reordered = [...accounts.value];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    accounts.value = reordered;

    const { data } = await api.post('/financial-accounts/reorder', { account_ids: accounts.value.map((account) => account.id) });
    accounts.value = data;
    accountDrafts.value = Object.fromEntries(accounts.value.map((account) => [account.id, accountDraftFrom(account)]));
}

async function saveFinanceCategory(category: FinanceCategory) {
    const draft = financeCategoryDrafts.value[category.id];
    if (!draft?.name.trim()) return;

    const { data } = await api.put(`/expense-categories/${category.id}`, {
        name: draft.name.trim(),
        color: draft.color,
        is_active: draft.is_active,
    });
    financeCategories.value = financeCategories.value.map((item) => item.id === category.id ? data : item);
    financeCategoryDrafts.value[data.id] = financeCategoryDraftFrom(data);
}

async function createFinanceCategory(type: 'expense' | 'income') {
    const draft = newFinanceCategory.value[type];
    if (!draft.name.trim()) return;

    const { data } = await api.post('/expense-categories', { name: draft.name.trim(), type, color: draft.color, is_active: draft.is_active });
    financeCategories.value.push(data);
    financeCategoryDrafts.value[data.id] = financeCategoryDraftFrom(data);
    newFinanceCategory.value[type] = { name: '', color: type === 'income' ? '#16A34A' : '#F43F5E', is_active: true };
}

async function deleteFinanceCategory(category: FinanceCategory) {
    await api.delete(`/expense-categories/${category.id}`);
    financeCategories.value = financeCategories.value.map((item) => item.id === category.id ? { ...item, is_active: false } : item);
    if (financeCategoryDrafts.value[category.id]) {
        financeCategoryDrafts.value[category.id].is_active = false;
    }
}

async function toggleFinanceCategory(category: FinanceCategory) {
    financeCategoryDrafts.value[category.id].is_active = !financeCategoryDrafts.value[category.id].is_active;
    await saveFinanceCategory(category);
}

async function dropFinanceCategory(targetId: number) {
    const sourceId = draggedFinanceCategoryId.value;
    draggedFinanceCategoryId.value = null;
    if (!sourceId || sourceId === targetId) return;

    const source = financeCategories.value.find((category) => category.id === sourceId);
    const target = financeCategories.value.find((category) => category.id === targetId);
    if (!source || !target || source.type !== target.type) return;

    const sameType = financeCategories.value.filter((category) => category.type === source.type);
    const fromIndex = sameType.findIndex((category) => category.id === sourceId);
    const toIndex = sameType.findIndex((category) => category.id === targetId);
    if (fromIndex < 0 || toIndex < 0) return;

    const reorderedType = [...sameType];
    const [moved] = reorderedType.splice(fromIndex, 1);
    reorderedType.splice(toIndex, 0, moved);
    financeCategories.value = [
        ...financeCategories.value.filter((category) => category.type !== source.type),
        ...reorderedType,
    ].sort((a, b) => a.type === b.type ? 0 : a.type.localeCompare(b.type));

    const { data } = await api.post('/expense-categories/reorder', { category_ids: reorderedType.map((category) => category.id) });
    financeCategories.value = data;
    financeCategoryDrafts.value = Object.fromEntries(financeCategories.value.map((category) => [category.id, financeCategoryDraftFrom(category)]));
}

async function createSupportTicket() {
    if (!supportForm.value.subject.trim() || !supportForm.value.body.trim()) return;

    supportSaving.value = true;
    supportSent.value = false;
    supportError.value = '';
    try {
        const { data } = await api.post('/support-tickets', {
            subject: supportForm.value.subject.trim(),
            body: supportForm.value.body.trim(),
        });
        supportTickets.value = [data, ...supportTickets.value];
        supportForm.value = { subject: '', body: '' };
        supportSent.value = true;
    } catch {
        supportError.value = 'ثبت تیکت انجام نشد. دوباره تلاش کن.';
    } finally {
        supportSaving.value = false;
    }
}

async function deleteSupportTicket(ticket: SupportTicket) {
    await api.delete(`/support-tickets/${ticket.id}`);
    supportTickets.value = supportTickets.value.filter((item) => item.id !== ticket.id);
}

</script>

<template>
    <div class="settings-shell" dir="rtl">
        <div class="settings-page">
            <i class="tape tape-yellow"></i>
            <i class="tape tape-cyan"></i>

            <header class="settings-header">
                <div>
                    <div class="settings-title">تنظیمات</div>
                    <p>مدیریت بخش‌بندی برنامه و گزارش‌ها</p>
                </div>
                <div class="settings-actions">
                    <button class="settings-nav today" @click="router.push('/app')">برنامه امروز</button>
                    <AppMenu />
                </div>
            </header>

            <main v-if="!loading">
                <section class="settings-hero">
                    <div>
                        <strong>بخش‌بندی</strong>
                        <p>نام، رنگ، آیکون، ترتیب و فعال‌بودن بخش‌ها از همین‌جا در برنامه امروز و گزارش ماهانه اعمال می‌شود.</p>
                    </div>
                    <div class="settings-preview">
                        <span v-for="category in activePreview" :key="category.id" :style="{ background: category.color }">
                            <svg viewBox="0 0 24 24"><path :d="category.iconPath"></path></svg>
                        </span>
                        <b>{{ activeCategoryCount }} / {{ allCategoryCount }}</b>
                        <b class="group-total">{{ activeTaskGroupCount }} گروه</b>
                    </div>
                </section>

                <section class="category-settings-grid">
                    <article
                        v-for="category in categories"
                        :key="category.id"
                        class="settings-category-card"
                        :class="{ inactive: !category.is_active, dragging: draggedCategoryId === category.id }"
                        :style="{ '--c': drafts[category.id]?.color || category.color, '--soft': category.soft_color }"
                        draggable="true"
                        @dragstart="draggedCategoryId = category.id"
                        @dragover.prevent
                        @drop="dropCategory(category.id)"
                        @dragend="draggedCategoryId = null"
                    >
                        <div class="settings-card-top">
                            <button class="drag-handle" type="button" title="جابجایی">↕</button>
                            <label class="active-toggle">
                                <input :checked="drafts[category.id]?.is_active" type="checkbox" @change="toggleCategory(category)" />
                                <span>فعال</span>
                            </label>
                        </div>
                        <div class="settings-card-ring">
                            <svg viewBox="0 0 24 24"><path :d="iconPath(drafts[category.id]?.icon || category.icon)"></path></svg>
                        </div>

                        <label>
                            <span>نام بخش</span>
                            <input v-model="drafts[category.id].name" maxlength="80" />
                        </label>

                        <div class="settings-row-label">رنگ بخش</div>
                        <div class="settings-swatches">
                            <button
                                v-for="color in palette"
                                :key="`${category.id}-${color}`"
                                :class="{ active: drafts[category.id].color === color }"
                                :style="{ background: color }"
                                :aria-label="`انتخاب رنگ ${color}`"
                                @click="drafts[category.id].color = color"
                            ></button>
                        </div>

                        <div class="settings-row-label">آیکون</div>
                        <div class="settings-icons">
                            <button
                                v-for="icon in visibleIcons(`category-${category.id}`)"
                                :key="`${category.id}-${icon.key}`"
                                :class="{ active: drafts[category.id].icon === icon.key }"
                                :title="icon.label"
                                @click="drafts[category.id].icon = icon.key"
                            >
                                <svg viewBox="0 0 24 24"><path :d="icon.path"></path></svg>
                            </button>
                            <button class="more-icons-btn" type="button" @click="toggleIcons(`category-${category.id}`)">
                                {{ expandedIconPicker[`category-${category.id}`] ? 'کمتر' : 'نمایش بیشتر' }}
                            </button>
                        </div>

                        <button class="group-link" type="button" @click="openTaskGroupModal(category)">
                            گروه‌بندی
                            <b v-if="activeGroupsForCategory(category.id).length">{{ activeGroupsForCategory(category.id).length }}</b>
                        </button>

                        <footer>
                            <small v-if="category.is_default">پیش‌فرض</small>
                            <small v-else>بخش شخصی</small>
                            <button :disabled="!hasChanged(category) || savingId === category.id" @click="saveCategory(category)">
                                {{ savingId === category.id ? '...' : 'ثبت' }}
                            </button>
                            <button v-if="!category.is_default" class="delete" @click="deleteCategory(category)">حذف</button>
                        </footer>
                    </article>

                    <article class="settings-category-card new-card" :style="{ '--c': newCategory.color }">
                        <div class="settings-card-ring">
                            <svg viewBox="0 0 24 24"><path :d="iconPath(newCategory.icon)"></path></svg>
                        </div>
                        <label>
                            <span>بخش جدید</span>
                            <input v-model="newCategory.name" placeholder="مثلاً پروژه شخصی" maxlength="80" @keyup.enter="createCategory" />
                        </label>
                        <div class="settings-row-label">رنگ بخش</div>
                        <div class="settings-swatches">
                            <button v-for="color in palette" :key="`new-${color}`" :class="{ active: newCategory.color === color }" :style="{ background: color }" @click="newCategory.color = color"></button>
                        </div>
                        <div class="settings-row-label">آیکون</div>
                        <div class="settings-icons">
                            <button v-for="icon in visibleIcons('new-category')" :key="`new-${icon.key}`" :class="{ active: newCategory.icon === icon.key }" :title="icon.label" @click="newCategory.icon = icon.key">
                                <svg viewBox="0 0 24 24"><path :d="icon.path"></path></svg>
                            </button>
                            <button class="more-icons-btn" type="button" @click="toggleIcons('new-category')">
                                {{ expandedIconPicker['new-category'] ? 'کمتر' : 'نمایش بیشتر' }}
                            </button>
                        </div>
                        <footer>
                            <small>اضافه کردن به برنامه و گزارش</small>
                            <button :disabled="!newCategory.name.trim()" @click="createCategory">افزودن</button>
                        </footer>
                    </article>
                </section>

                <div v-if="taskGroupModalCategory" class="settings-modal-backdrop">
                    <section class="settings-modal group-management-modal" :style="{ '--c': taskGroupModalCategory.color }">
                        <header>
                            <div>
                                <span>گروه‌بندی بخش</span>
                                <strong>{{ taskGroupModalCategory.name }}</strong>
                            </div>
                            <button class="modal-close-btn" type="button" aria-label="بستن" @click="closeTaskGroupModal">×</button>
                        </header>

                        <div class="group-modal-toolbar">
                            <div>
                                <b>{{ activeGroupsForCategory(taskGroupModalCategory.id).length }}</b>
                                <span>گروه فعال</span>
                            </div>
                            <button type="button" @click="openTaskGroupCreateModal">+ افزودن گروه</button>
                        </div>

                        <div v-if="taskGroupsForCategory(taskGroupModalCategory.id).length" class="group-table-wrap">
                            <table class="group-table">
                                <thead>
                                    <tr>
                                        <th>ترتیب</th>
                                        <th>نام گروه</th>
                                        <th>رنگ</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="group in taskGroupsForCategory(taskGroupModalCategory.id)"
                                        :key="`group-modal-${group.id}`"
                                        :class="{ inactive: !group.is_active, dragging: draggedTaskGroupId === group.id }"
                                        :style="{ '--g': taskGroupDrafts[group.id]?.color || group.color }"
                                        draggable="true"
                                        @dragstart="draggedTaskGroupId = group.id"
                                        @dragover.prevent
                                        @drop="dropTaskGroup(taskGroupModalCategory.id, group.id)"
                                        @dragend="draggedTaskGroupId = null"
                                    >
                                        <td data-label="ترتیب"><button class="mini-drag" type="button" title="جابجایی">↕</button></td>
                                        <td data-label="نام گروه">
                                            <label class="group-name-cell">
                                                <i></i>
                                                <input v-model="taskGroupDrafts[group.id].name" maxlength="100" />
                                            </label>
                                        </td>
                                        <td data-label="رنگ">
                                            <div class="group-color-cell">
                                                <button v-for="color in palette" :key="`modal-group-${group.id}-${color}`" :class="{ active: taskGroupDrafts[group.id].color === color }" :style="{ background: color }" @click="taskGroupDrafts[group.id].color = color"></button>
                                            </div>
                                        </td>
                                        <td data-label="وضعیت">
                                            <label class="active-toggle group-status-toggle">
                                                <input :checked="taskGroupDrafts[group.id]?.is_active" type="checkbox" @change="toggleTaskGroup(group)" />
                                                <span>{{ taskGroupDrafts[group.id]?.is_active ? 'فعال' : 'غیرفعال' }}</span>
                                            </label>
                                        </td>
                                        <td data-label="عملیات">
                                            <div class="group-row-actions">
                                                <button :disabled="!taskGroupChanged(group)" @click="saveTaskGroup(group)">ثبت</button>
                                                <button class="delete" @click="deleteTaskGroup(group)">حذف</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="group-empty-state">
                            <strong>هنوز گروهی برای این بخش نداری.</strong>
                            <button type="button" @click="openTaskGroupCreateModal">اولین گروه را بساز</button>
                        </div>
                    </section>
                </div>

                <div v-if="taskGroupCreateModal && taskGroupModalCategory" class="settings-modal-backdrop top-layer">
                    <form class="settings-modal group-create-modal" :style="{ '--c': newTaskGroup.color }" @submit.prevent="createTaskGroup">
                        <header>
                            <div>
                                <span>گروه جدید برای</span>
                                <strong>{{ taskGroupModalCategory.name }}</strong>
                            </div>
                            <button class="modal-close-btn" type="button" aria-label="بستن" @click="closeTaskGroupCreateModal">×</button>
                        </header>
                        <label>
                            <span>نام گروه</span>
                            <input v-model="newTaskGroup.name" placeholder="مثلاً پروژه دستیار" maxlength="100" autofocus />
                        </label>
                        <div class="settings-row-label">رنگ گروه</div>
                        <div class="group-create-swatches">
                            <button v-for="color in palette" :key="`create-group-${color}`" type="button" :class="{ active: newTaskGroup.color === color }" :style="{ background: color }" @click="newTaskGroup.color = color"></button>
                        </div>
                        <footer>
                            <button type="button" @click="closeTaskGroupCreateModal">انصراف</button>
                            <button type="submit" :disabled="!newTaskGroup.name.trim()">افزودن</button>
                        </footer>
                    </form>
                </div>

                <section class="settings-hero priority-hero">
                    <div>
                        <strong>اولویت‌ها</strong>
                        <p>برچسب و رنگ اولویت‌ها را تغییر بده؛ تسک‌های قبلی با همان کلید ذخیره‌شده می‌مانند و فقط نمایششان به‌روز می‌شود.</p>
                    </div>
                    <div class="priority-preview">
                        <span v-for="priority in priorities" :key="priority.id" :style="{ background: priority.soft_color, color: priority.color, borderColor: priority.color }">{{ priority.label }}</span>
                    </div>
                </section>

                <section class="priority-settings-grid">
                    <article v-for="priority in priorities" :key="priority.id" class="priority-settings-card" :style="{ '--c': priorityDrafts[priority.id]?.color || priority.color }">
                        <div class="priority-chip-preview" :style="{ background: priorityDrafts[priority.id]?.color || priority.color }">{{ priorityDrafts[priority.id]?.label || priority.label }}</div>
                        <label>
                            <span>نام اولویت</span>
                            <input v-model="priorityDrafts[priority.id].label" maxlength="80" />
                        </label>
                        <div class="settings-row-label">رنگ اولویت</div>
                        <div class="settings-swatches">
                            <button
                                v-for="color in palette"
                                :key="`priority-${priority.id}-${color}`"
                                :class="{ active: priorityDrafts[priority.id].color === color }"
                                :style="{ background: color }"
                                @click="priorityDrafts[priority.id].color = color"
                            ></button>
                        </div>
                        <footer>
                            <small v-if="priority.is_default">پیش‌فرض</small>
                            <small v-else>اولویت شخصی</small>
                            <button :disabled="!priorityChanged(priority) || savingPriorityId === priority.id" @click="savePriority(priority)">
                                {{ savingPriorityId === priority.id ? '...' : 'ثبت' }}
                            </button>
                            <button v-if="!priority.is_default" class="delete" @click="deletePriority(priority)">حذف</button>
                        </footer>
                    </article>

                    <article class="priority-settings-card new-card" :style="{ '--c': newPriority.color }">
                        <div class="priority-chip-preview" :style="{ background: newPriority.color }">{{ newPriority.label || 'اولویت جدید' }}</div>
                        <label>
                            <span>اولویت جدید</span>
                            <input v-model="newPriority.label" placeholder="مثلاً خیلی مهم" maxlength="80" @keyup.enter="createPriority" />
                        </label>
                        <div class="settings-row-label">رنگ اولویت</div>
                        <div class="settings-swatches">
                            <button v-for="color in palette" :key="`new-priority-${color}`" :class="{ active: newPriority.color === color }" :style="{ background: color }" @click="newPriority.color = color"></button>
                        </div>
                        <footer>
                            <small>اضافه کردن به انتخاب‌های تسک</small>
                            <button :disabled="!newPriority.label.trim()" @click="createPriority">افزودن</button>
                        </footer>
                    </article>
                </section>

                <section class="settings-hero finance-hero">
                    <div>
                        <strong>تنظیمات مالی</strong>
                        <p>حساب‌ها را مدیریت کن؛ کیف پول حساب پیش‌فرض است و حذف نمی‌شود. موجودی جاری از موجودی اولیه، واریزها و برداشت‌ها محاسبه می‌شود.</p>
                    </div>
                    <div class="priority-preview">
                        <span v-for="account in accounts.filter(item => item.is_active)" :key="account.id" :style="{ background: '#fff', color: account.color, borderColor: account.color }">{{ account.name }}</span>
                    </div>
                </section>

                <section class="account-settings-grid">
                    <article
                        v-for="account in accounts"
                        :key="account.id"
                        class="account-settings-card"
                        :class="{ inactive: !account.is_active, dragging: draggedAccountId === account.id }"
                        :style="{ '--c': accountDrafts[account.id]?.color || account.color }"
                        draggable="true"
                        @dragstart="draggedAccountId = account.id"
                        @dragover.prevent
                        @drop="dropAccount(account.id)"
                        @dragend="draggedAccountId = null"
                    >
                        <div class="account-card-head">
                            <button class="drag-handle" type="button" title="جابجایی">↕</button>
                            <div>
                                <span>حساب</span>
                                <strong>{{ account.name }}</strong>
                            </div>
                            <label class="active-toggle">
                                <input :disabled="account.is_default" v-model="accountDrafts[account.id].is_active" type="checkbox" />
                                <span>فعال</span>
                            </label>
                        </div>
                        <div class="account-balance-box">
                            <span>موجودی فعلی</span>
                            <strong>{{ moneyPlain(account.current_balance) }} تومان</strong>
                            <small>{{ moneyPlain(account.income_total) }} واریز · {{ moneyPlain(account.expense_total) }} برداشت</small>
                        </div>
                        <label>
                            <span>نام حساب</span>
                            <input v-model="accountDrafts[account.id].name" />
                        </label>
                        <label>
                            <span>موجودی اولیه</span>
                            <input v-model="accountDrafts[account.id].initial_balance" inputmode="numeric" @input="accountDrafts[account.id].initial_balance = moneyPlain(accountDrafts[account.id].initial_balance)" />
                        </label>
                        <label>
                            <span>شماره کارت اختیاری</span>
                            <input v-model="accountDrafts[account.id].card_number" inputmode="numeric" maxlength="32" placeholder="۶۲۷۴..." @input="accountDrafts[account.id].card_number = cleanCard(accountDrafts[account.id].card_number)" />
                        </label>
                        <label>
                            <span>شماره شبا اختیاری</span>
                            <input v-model="accountDrafts[account.id].sheba_number" maxlength="34" placeholder="IR..." @input="accountDrafts[account.id].sheba_number = cleanSheba(accountDrafts[account.id].sheba_number)" />
                        </label>
                        <div class="settings-row-label">رنگ حساب</div>
                        <div class="settings-swatches">
                            <button v-for="color in palette" :key="`account-${account.id}-${color}`" :class="{ active: accountDrafts[account.id].color === color }" :style="{ background: color }" @click="accountDrafts[account.id].color = color"></button>
                        </div>
                        <footer>
                            <small v-if="account.is_default">پیش‌فرض و غیرقابل حذف</small>
                            <small v-else>حساب شخصی</small>
                            <button :disabled="!accountChanged(account)" @click="saveAccount(account)">ثبت</button>
                            <button v-if="!account.is_default" class="delete" @click="deleteAccount(account)">حذف</button>
                        </footer>
                    </article>

                    <article class="account-settings-card new-card" :style="{ '--c': newAccount.color }">
                        <div class="account-card-head">
                            <div>
                                <span>حساب جدید</span>
                                <strong>{{ newAccount.name || 'نام حساب' }}</strong>
                            </div>
                        </div>
                        <label>
                            <span>نام حساب</span>
                            <input v-model="newAccount.name" placeholder="مثلاً اقتصاد نوین" @keyup.enter="createAccount" />
                        </label>
                        <label>
                            <span>موجودی اولیه</span>
                            <input v-model="newAccount.initial_balance" inputmode="numeric" placeholder="20,000,000" @input="newAccount.initial_balance = moneyPlain(newAccount.initial_balance)" />
                        </label>
                        <label>
                            <span>شماره کارت اختیاری</span>
                            <input v-model="newAccount.card_number" inputmode="numeric" maxlength="32" placeholder="۶۲۷۴..." @input="newAccount.card_number = cleanCard(newAccount.card_number)" />
                        </label>
                        <label>
                            <span>شماره شبا اختیاری</span>
                            <input v-model="newAccount.sheba_number" maxlength="34" placeholder="IR..." @input="newAccount.sheba_number = cleanSheba(newAccount.sheba_number)" />
                        </label>
                        <div class="settings-row-label">رنگ حساب</div>
                        <div class="settings-swatches">
                            <button v-for="color in palette" :key="`new-account-${color}`" :class="{ active: newAccount.color === color }" :style="{ background: color }" @click="newAccount.color = color"></button>
                        </div>
                        <footer>
                            <small>افزودن به انتخاب حساب تراکنش</small>
                            <button :disabled="!newAccount.name.trim()" @click="createAccount">افزودن</button>
                        </footer>
                    </article>
                </section>

                <section class="finance-category-settings">
                    <article v-for="type in financeTypes" :key="type" class="finance-category-panel" :class="type">
                        <header>
                            <div>
                                <span>{{ type === 'income' ? 'دسته‌بندی درآمد' : 'دسته‌بندی هزینه' }}</span>
                                <strong>{{ financeCategoryGroups[type].filter(item => item.is_active).length }} فعال</strong>
                            </div>
                        </header>

                        <div class="finance-category-new" :style="{ '--c': newFinanceCategory[type].color }">
                            <input v-model="newFinanceCategory[type].name" :placeholder="type === 'income' ? 'دسته درآمد جدید...' : 'دسته هزینه جدید...'" @keyup.enter="createFinanceCategory(type)" />
                            <div class="settings-swatches">
                                <button v-for="color in palette" :key="`new-finance-${type}-${color}`" :class="{ active: newFinanceCategory[type].color === color }" :style="{ background: color }" @click="newFinanceCategory[type].color = color"></button>
                            </div>
                            <button :disabled="!newFinanceCategory[type].name.trim()" @click="createFinanceCategory(type)">افزودن</button>
                        </div>

                        <div class="finance-category-list">
                            <article
                                v-for="category in financeCategoryGroups[type]"
                                :key="`settings-finance-${category.id}`"
                                class="finance-category-settings-card"
                                :class="{ inactive: !category.is_active, dragging: draggedFinanceCategoryId === category.id }"
                                :style="{ '--c': financeCategoryDrafts[category.id]?.color || category.color }"
                                draggable="true"
                                @dragstart="draggedFinanceCategoryId = category.id"
                                @dragover.prevent
                                @drop="dropFinanceCategory(category.id)"
                                @dragend="draggedFinanceCategoryId = null"
                            >
                                <button class="drag-handle" type="button" title="جابجایی">↕</button>
                                <i></i>
                                <input v-model="financeCategoryDrafts[category.id].name" maxlength="100" />
                                <label class="active-toggle">
                                    <input :checked="financeCategoryDrafts[category.id]?.is_active" type="checkbox" @change="toggleFinanceCategory(category)" />
                                    <span>فعال</span>
                                </label>
                                <div class="settings-swatches">
                                    <button v-for="color in palette" :key="`finance-${category.id}-${color}`" :class="{ active: financeCategoryDrafts[category.id].color === color }" :style="{ background: color }" @click="financeCategoryDrafts[category.id].color = color"></button>
                                </div>
                                <footer>
                                    <small v-if="category.is_default">پیش‌فرض</small>
                                    <small v-else>دسته شخصی</small>
                                    <button :disabled="!financeCategoryChanged(category)" @click="saveFinanceCategory(category)">ثبت</button>
                                    <button v-if="!category.is_default" class="delete" @click="deleteFinanceCategory(category)">حذف</button>
                                </footer>
                            </article>
                        </div>
                    </article>
                </section>

                <section class="settings-support-section">
                    <article class="support-intro">
                        <span>پشتیبانی</span>
                        <strong>اگه مشکلی داشتی، از اینجا تیکت بزن</strong>
                        <p>درخواستت برای مدیریت ثبت می‌شود و پاسخ همین‌جا نمایش داده می‌شود.</p>
                        <button type="button" @click="router.push('/support/admin')">پنل مدیریت تیکت‌ها</button>
                    </article>

                    <article class="support-form-card">
                        <label>
                            <span>موضوع تیکت</span>
                            <input v-model="supportForm.subject" maxlength="160" placeholder="مثلاً مشکل در ثبت تراکنش" />
                        </label>
                        <label>
                            <span>توضیحات</span>
                            <textarea v-model="supportForm.body" maxlength="4000" placeholder="مشکل یا سوالت را کامل بنویس..."></textarea>
                        </label>
                        <p v-if="supportSent" class="support-success">تیکت ثبت شد؛ پاسخ مدیریت همین‌جا می‌آید.</p>
                        <p v-if="supportError" class="support-error">{{ supportError }}</p>
                        <button :disabled="supportSaving || !supportForm.subject.trim() || !supportForm.body.trim()" @click="createSupportTicket">
                            {{ supportSaving ? 'در حال ثبت...' : 'ثبت تیکت' }}
                        </button>
                    </article>

                    <div class="support-ticket-list">
                        <div v-if="supportLoading" class="support-empty">در حال بارگذاری تیکت‌ها...</div>
                        <article v-for="ticket in supportTickets" :key="ticket.id" :class="ticket.status">
                            <header>
                                <strong>{{ ticket.subject }}</strong>
                                <div>
                                    <span>{{ ticket.status === 'answered' ? 'پاسخ داده شده' : 'در انتظار پاسخ' }}</span>
                                    <button type="button" title="حذف تیکت" aria-label="حذف تیکت" @click="deleteSupportTicket(ticket)">×</button>
                                </div>
                            </header>
                            <p>{{ ticket.body }}</p>
                            <small>{{ ticketDate(ticket.created_at) }}</small>
                            <div v-if="ticket.admin_reply" class="support-reply">
                                <b>پاسخ مدیریت</b>
                                <p>{{ ticket.admin_reply }}</p>
                                <small>{{ ticketDate(ticket.replied_at) }}</small>
                            </div>
                        </article>
                        <div v-if="!supportLoading && !supportTickets.length" class="support-empty">هنوز تیکتی ثبت نشده.</div>
                    </div>
                </section>
            </main>

            <div v-else class="settings-loading">در حال بارگذاری تنظیمات...</div>
        </div>
    </div>
</template>

<style scoped>
.settings-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 10%,#2e2140 0%,#1a1424 65%);padding:36px 20px 60px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}
.settings-page{width:1040px;max-width:100%;margin:auto;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:10px;box-shadow:0 30px 60px rgba(0,0,0,.5);position:relative;padding:34px 34px 44px}
.tape{position:absolute;width:74px;height:24px;opacity:.9;transform:rotate(-4deg);border:2px solid rgba(58,46,31,.28)}.tape-yellow{right:48px;top:-12px;background:#ffd93d}.tape-cyan{left:64px;top:-10px;background:#22d3d0;transform:rotate(5deg)}
.settings-header,.settings-actions{display:flex;align-items:center}.settings-header{justify-content:space-between;gap:12px;margin-bottom:22px;flex-wrap:wrap}.settings-title{font-family:Lalezar,Vazirmatn,sans-serif;font-size:32px}.settings-header p{margin:2px 0 0;color:#7a6a4f;font-weight:800}.settings-actions{gap:8px}.settings-nav,.settings-menu{height:36px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f;font-weight:900;cursor:pointer}.settings-nav{padding:0 14px}.settings-nav.today{background:#ffd93d}.settings-menu{width:38px;display:flex;flex-direction:column;justify-content:center;gap:4px;padding:0 8px}.settings-menu span{height:3px;background:#3a2e1f;border-radius:3px}
.settings-drawer{position:absolute;top:84px;left:34px;z-index:5;background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:4px 4px 0 #3a2e1f;padding:10px;display:grid;gap:8px;min-width:220px}.settings-drawer button{height:40px;border:0;border-radius:10px;text-align:right;padding:0 12px;font-weight:900;background:#dbeafe;cursor:pointer}.settings-drawer .danger{background:#fee2e2;color:#991b1b}
.settings-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;background:linear-gradient(120deg,#ffd93d,#22d3d0 55%,#9b5de5);border:2px solid #3a2e1f;border-radius:16px;padding:18px 22px;margin-bottom:22px;box-shadow:4px 4px 0 #3a2e1f}.settings-hero strong{font-family:Lalezar,Vazirmatn,sans-serif;font-size:26px}.settings-hero p{margin:4px 0 0;font-size:13px;font-weight:800;line-height:1.9}.settings-preview{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}.settings-preview span{width:42px;height:42px;border:2px solid #3a2e1f;border-radius:13px;display:grid;place-items:center;box-shadow:2px 2px 0 #3a2e1f}.settings-preview svg{width:19px;height:19px;fill:none;stroke:#fff;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round}.settings-preview b{height:32px;display:inline-flex;align-items:center;border:2px solid #3a2e1f;border-radius:999px;background:#fff;padding:0 10px;box-shadow:2px 2px 0 #3a2e1f;font-size:12px}
.category-settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.settings-category-card{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 14%,white),#fff);border:2px solid #3a2e1f;border-radius:16px;padding:14px;box-shadow:4px 4px 0 #3a2e1f;display:grid;gap:10px;position:relative;overflow:hidden}.settings-category-card::before{content:'';position:absolute;inset:auto -24px -40px auto;width:120px;height:120px;border-radius:50%;background:color-mix(in srgb,var(--c) 18%,transparent);pointer-events:none}.settings-card-ring{width:58px;height:58px;border:2px solid #3a2e1f;border-radius:18px;background:var(--c);display:grid;place-items:center;box-shadow:3px 3px 0 #3a2e1f}.settings-card-ring svg{width:25px;height:25px;fill:none;stroke:#fff;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.settings-category-card.inactive{opacity:.58;filter:grayscale(.35);background:#f6f0e2}.settings-category-card.dragging{opacity:.42;transform:scale(.98)}.settings-card-top{display:flex;align-items:center;justify-content:space-between;gap:8px;position:relative;z-index:2}.drag-handle{width:32px;height:30px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f;font-weight:900;cursor:grab}.active-toggle{display:inline-flex!important;grid-template-columns:none!important;align-items:center;gap:6px;background:#fffbf0;border:1.5px solid #eadfbe;border-radius:999px;padding:4px 8px!important;font-size:11px;font-weight:900;color:#3a2e1f;cursor:pointer}.active-toggle input{width:16px!important;height:16px!important;padding:0!important;border:2px solid #3a2e1f!important;border-radius:5px!important;accent-color:#16a34a}.active-toggle span{font-size:11px!important;color:#3a2e1f!important}
.settings-category-card label{display:grid;gap:6px;position:relative;z-index:1}.settings-category-card label span,.settings-row-label{font-size:11px;color:#7a6a4f;font-weight:900}.settings-category-card input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.settings-category-card input:focus{box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 22%,transparent)}
.settings-swatches,.settings-icons{display:flex;gap:7px;flex-wrap:wrap;position:relative;z-index:1}.settings-swatches button{width:28px;height:28px;border:2px solid #3a2e1f;border-radius:9px;box-shadow:1.5px 1.5px 0 #3a2e1f;cursor:pointer}.settings-swatches button.active,.settings-icons button.active{outline:3px solid color-mix(in srgb,var(--c) 30%,white);transform:translateY(-1px)}
.settings-icons button{width:32px;height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;display:grid;place-items:center;cursor:pointer}.settings-icons svg{width:16px;height:16px;fill:none;stroke:#3a2e1f;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}.settings-icons .more-icons-btn{width:auto;min-width:92px;padding:0 10px;background:#fffbf0;color:#3a2e1f;font-family:inherit;font-size:11px;font-weight:900}
.settings-category-card footer{display:flex;align-items:center;gap:8px;position:relative;z-index:1;padding-top:8px;border-top:1px dashed rgba(58,46,31,.25)}.settings-category-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.settings-category-card footer button{height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.settings-category-card footer button:disabled{opacity:.45;cursor:not-allowed}.settings-category-card footer .delete{background:#fff;color:#dc2626}
.settings-preview .group-total{background:#fffbf0;color:#3a2e1f;border:2px solid #3a2e1f;border-radius:999px;padding:6px 10px;font-size:11px}.group-link{position:relative;z-index:2;width:max-content;display:inline-flex;align-items:center;gap:7px;border:1.5px solid #3a2e1f;border-radius:999px;background:#fffbf0;color:#3a2e1f;padding:7px 12px;font-family:inherit;font-size:11px;font-weight:900;box-shadow:2px 2px 0 #3a2e1f;cursor:pointer}.group-link b{min-width:20px;height:20px;border-radius:50%;display:grid;place-items:center;background:var(--c);color:#fff;font-size:10px}.task-group-manager{position:relative;z-index:2;display:grid;gap:10px;border:1.5px solid color-mix(in srgb,var(--c) 34%,#eadfbe);border-radius:14px;background:rgba(255,251,240,.82);padding:10px}.task-group-new{display:grid;grid-template-columns:minmax(0,1fr) 38px;gap:8px}.task-group-new input{height:38px!important;border:1.5px solid #eadfbe!important;border-radius:11px!important;background:#fff!important}.task-group-new button:not(.settings-swatches button){height:38px;border:2px solid #3a2e1f;border-radius:11px;background:var(--g);color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-size:20px;font-weight:900;cursor:pointer}.task-group-new button:disabled{opacity:.45}.task-group-new .settings-swatches{grid-column:1/-1;gap:5px}.task-group-new .settings-swatches button,.task-group-row .settings-swatches button{width:18px;height:18px;border-radius:6px;box-shadow:none}.task-group-list{display:grid;gap:8px}.task-group-row{display:grid;grid-template-columns:28px 12px minmax(0,1fr) auto;gap:8px;align-items:center;border:1.5px solid #eadfbe;border-right:5px solid var(--g);border-radius:12px;background:#fff;padding:8px}.task-group-row.inactive{opacity:.52;filter:grayscale(.25)}.task-group-row.dragging{transform:scale(.99);opacity:.55}.task-group-row i{width:10px;height:10px;border-radius:50%;background:var(--g);box-shadow:0 0 0 4px color-mix(in srgb,var(--g) 14%,white)}.task-group-row input{height:32px!important;border:0!important;border-bottom:1px dashed rgba(58,46,31,.25)!important;border-radius:0!important;background:transparent!important;padding:0 4px!important}.mini-drag{width:28px;height:28px;border:1.5px solid #eadfbe;border-radius:9px;background:#fffbf0;color:#8a7a5b;font-weight:900;cursor:grab}.task-group-row .settings-swatches{grid-column:1/-1;gap:5px;padding-right:34px}.task-group-row footer{grid-column:1/-1}.task-group-row footer button{height:28px!important;border-width:1.5px!important;border-radius:9px!important;box-shadow:none!important;font-size:11px}
.settings-modal-backdrop{position:fixed;inset:0;z-index:5000;display:grid;place-items:center;padding:24px;background:rgba(20,14,28,.58);backdrop-filter:blur(5px)}.settings-modal-backdrop.top-layer{z-index:5010}.settings-modal{width:min(860px,100%);max-height:min(82vh,760px);overflow:auto;border:2px solid #3a2e1f;border-radius:18px;background:#fffbf0;box-shadow:8px 8px 0 rgba(58,46,31,.92);padding:18px;display:grid;gap:14px}.settings-modal header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:12px;border-bottom:1.5px dashed #eadfbe}.settings-modal header span{display:block;color:#7a6a4f;font-size:12px;font-weight:900}.settings-modal header strong{display:block;margin-top:2px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;color:#3a2e1f}.modal-close-btn{width:34px;height:34px;border:2px solid #3a2e1f;border-radius:11px;background:#fff;color:#3a2e1f;box-shadow:2px 2px 0 #3a2e1f;font-size:22px;font-weight:900;line-height:1;cursor:pointer}.group-management-modal{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 8%,#fff),#fffbf0 170px)}.group-modal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px}.group-modal-toolbar>div{display:inline-flex;align-items:center;gap:8px;border:1.5px solid #eadfbe;border-radius:999px;background:#fff;padding:6px 12px;font-size:12px;font-weight:900;color:#7a6a4f}.group-modal-toolbar b{min-width:25px;height:25px;display:grid;place-items:center;border-radius:50%;background:var(--c);color:#fff}.group-modal-toolbar button,.group-empty-state button,.group-row-actions button,.group-create-modal footer button{height:36px;border:2px solid #3a2e1f;border-radius:11px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 13px;font-family:inherit;font-weight:900;color:#3a2e1f;cursor:pointer}.group-table-wrap{overflow:auto;border:1.5px solid #eadfbe;border-radius:14px;background:#fff}.group-table{width:100%;border-collapse:separate;border-spacing:0;min-width:720px}.group-table th{position:sticky;top:0;z-index:1;background:#fff4d4;color:#7a6a4f;font-size:11px;text-align:right;padding:10px;border-bottom:1.5px solid #eadfbe}.group-table td{padding:10px;border-bottom:1px dashed rgba(58,46,31,.16);vertical-align:middle}.group-table tbody tr{background:#fff}.group-table tbody tr.inactive{opacity:.55;filter:grayscale(.3)}.group-table tbody tr.dragging{opacity:.5}.group-name-cell{display:flex!important;grid-template-columns:none!important;align-items:center;gap:9px}.group-name-cell i{width:13px;height:13px;border-radius:50%;background:var(--g);box-shadow:0 0 0 4px color-mix(in srgb,var(--g) 16%,white);flex:none}.group-name-cell input,.group-create-modal input{width:100%;height:38px;border:1.5px solid #eadfbe;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.group-name-cell input:focus,.group-create-modal input:focus{border-color:var(--g,var(--c));box-shadow:0 0 0 3px color-mix(in srgb,var(--g,var(--c)) 16%,transparent)}.group-color-cell,.group-create-swatches{display:flex;gap:6px;flex-wrap:wrap}.group-color-cell button,.group-create-swatches button{width:22px;height:22px;border:1.5px solid #3a2e1f;border-radius:7px;box-shadow:1px 1px 0 #3a2e1f;cursor:pointer}.group-color-cell button.active,.group-create-swatches button.active{outline:3px solid color-mix(in srgb,var(--c) 26%,white);transform:translateY(-1px)}.group-status-toggle{width:max-content}.group-row-actions{display:flex;align-items:center;gap:7px}.group-row-actions button{height:31px;border-width:1.5px;box-shadow:none;background:#fffbf0;color:#0f766e}.group-row-actions .delete{background:#fff;color:#dc2626}.group-row-actions button:disabled,.group-create-modal footer button:disabled{opacity:.45;cursor:not-allowed}.group-empty-state{min-height:180px;display:grid;place-items:center;gap:12px;text-align:center;border:1.5px dashed #eadfbe;border-radius:14px;background:#fff}.group-empty-state strong{font-size:15px}.group-create-modal{width:min(430px,100%);background:linear-gradient(180deg,color-mix(in srgb,var(--c) 10%,white),#fffbf0)}.group-create-modal label{display:grid;gap:7px}.group-create-modal label span{font-size:11px;color:#7a6a4f;font-weight:900}.group-create-modal footer{display:flex;align-items:center;justify-content:flex-start;gap:9px;padding-top:12px;border-top:1.5px dashed #eadfbe}.group-create-modal footer button[type=button]{background:#fff}.group-create-modal footer button[type=submit]{background:var(--c);color:#fff}
.priority-hero{margin-top:28px;background:linear-gradient(120deg,#22d3d0,#ffd93d 55%,#ff6fa5)}.priority-preview{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.priority-preview span{min-height:30px;display:inline-flex;align-items:center;justify-content:center;padding:4px 12px;border:2px solid currentColor;border-radius:999px;background:#fff;font-size:12px;font-weight:900}
.priority-settings-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:10px}.priority-settings-card{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);border:2px solid #3a2e1f;border-radius:16px;padding:14px;box-shadow:4px 4px 0 #3a2e1f;display:grid;gap:10px;position:relative;overflow:hidden}.priority-settings-card::before{content:'';position:absolute;left:-34px;bottom:-44px;width:118px;height:118px;border-radius:50%;background:color-mix(in srgb,var(--c) 16%,transparent)}.priority-chip-preview{width:max-content;max-width:100%;min-height:34px;display:inline-flex;align-items:center;justify-content:center;padding:5px 14px;border:2px solid #3a2e1f;border-radius:999px;box-shadow:2px 2px 0 #3a2e1f;color:#fff;font-weight:900;position:relative;z-index:1}.priority-settings-card label{display:grid;gap:6px;position:relative;z-index:1}.priority-settings-card label span{font-size:11px;color:#7a6a4f;font-weight:900}.priority-settings-card input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.priority-settings-card input:focus{box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 22%,transparent)}.priority-settings-card footer{display:flex;align-items:center;gap:8px;position:relative;z-index:1;padding-top:8px;border-top:1px dashed rgba(58,46,31,.25)}.priority-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.priority-settings-card footer button{height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.priority-settings-card footer button:disabled{opacity:.45;cursor:not-allowed}.priority-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-hero{margin-top:28px;background:linear-gradient(120deg,#34d399,#22d3d0 55%,#2563eb)}.account-settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.account-settings-card{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);border:2px solid #3a2e1f;border-radius:16px;padding:14px;box-shadow:4px 4px 0 #3a2e1f;display:grid;gap:10px;position:relative;overflow:hidden}.account-settings-card.inactive{opacity:.58;filter:grayscale(.35)}.account-settings-card.dragging{opacity:.42;transform:scale(.98)}.account-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.account-card-head>div{margin-left:auto}.account-card-head span,.account-settings-card label span{font-size:11px;color:#7a6a4f;font-weight:900}.account-card-head strong{display:block;color:#3a2e1f;font-size:16px}.account-balance-box{border:1.5px dashed color-mix(in srgb,var(--c) 42%,#eadfbe);border-radius:14px;background:#fffbf0;padding:10px;display:grid;gap:3px}.account-balance-box span,.account-balance-box small{color:#7a6a4f;font-size:10.5px;font-weight:900}.account-balance-box strong{font-size:16px;color:var(--c)}.account-settings-card label{display:grid;gap:6px;position:relative;z-index:1}.account-settings-card input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.account-settings-card footer{display:flex;align-items:center;gap:8px;position:relative;z-index:1;padding-top:8px;border-top:1px dashed rgba(58,46,31,.25)}.account-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.account-settings-card footer button{height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.account-settings-card footer button:disabled{opacity:.45;cursor:not-allowed}.account-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-category-settings{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.finance-category-panel{border:2px solid #3a2e1f;border-radius:16px;background:#fff;box-shadow:4px 4px 0 #3a2e1f;overflow:hidden}.finance-category-panel header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#fff4e6;border-bottom:2px solid #eadfbe}.finance-category-panel.income header{background:#ecfdf5}.finance-category-panel header span{font-size:12px;color:#7a6a4f;font-weight:900}.finance-category-panel header strong{display:block;margin-top:2px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#3a2e1f}.finance-category-new{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;padding:14px 16px;border-bottom:1px dashed rgba(58,46,31,.24);background:linear-gradient(90deg,color-mix(in srgb,var(--c) 8%,white),#fff)}.finance-category-new input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-weight:900;color:#3a2e1f;outline:0}.finance-category-new .settings-swatches{grid-column:1/-1}.finance-category-new>button{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.finance-category-new>button:disabled{opacity:.45}.finance-category-list{display:grid;gap:10px;padding:14px 16px}.finance-category-settings-card{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;gap:9px;align-items:center;border:2px solid #3a2e1f;border-radius:14px;background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);box-shadow:3px 3px 0 #3a2e1f;padding:10px}.finance-category-settings-card.inactive{opacity:.58;filter:grayscale(.35)}.finance-category-settings-card.dragging{opacity:.42}.finance-category-settings-card i{width:16px;height:16px;border-radius:50%;background:var(--c);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 18%,white)}.finance-category-settings-card input{min-width:0;height:36px;border:0;border-bottom:1px dashed rgba(58,46,31,.3);background:transparent;font-weight:900;color:#3a2e1f;outline:0}.finance-category-settings-card .settings-swatches{grid-column:1/-1}.finance-category-settings-card footer{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding-top:8px;border-top:1px dashed rgba(58,46,31,.2)}.finance-category-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.finance-category-settings-card footer button{height:31px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.finance-category-settings-card footer button:disabled{opacity:.45}.finance-category-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-category-settings{gap:18px;margin-top:22px}.finance-category-panel{border:1.5px solid #eadfbe;border-radius:14px;background:rgba(255,255,255,.74);box-shadow:3px 3px 0 rgba(58,46,31,.9);backdrop-filter:blur(3px)}.finance-category-panel header{padding:13px 15px;background:linear-gradient(90deg,rgba(255,217,61,.16),rgba(255,255,255,.78));border-bottom:1px solid #efe3c4}.finance-category-panel.income header{background:linear-gradient(90deg,rgba(22,163,74,.12),rgba(255,255,255,.78))}.finance-category-panel header div{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%}.finance-category-panel header span{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#3a2e1f}.finance-category-panel header span::before{content:'';width:10px;height:10px;border-radius:50%;background:#f97316;box-shadow:0 0 0 4px rgba(249,115,22,.13)}.finance-category-panel.income header span::before{background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.13)}.finance-category-panel header strong{margin:0;padding:4px 10px;border:1px solid #eadfbe;border-radius:999px;background:#fffbf0;font-family:Vazirmatn,sans-serif;font-size:11px;color:#7a6a4f}.finance-category-new{grid-template-columns:minmax(0,1fr) 78px;gap:8px;padding:12px 14px;background:#fff;border-bottom:1px dashed rgba(58,46,31,.16)}.finance-category-new input{height:38px;border:1.5px solid #eadfbe;border-radius:12px;background:#fffbf0;box-shadow:none;font-size:12px}.finance-category-new input:focus{border-color:var(--c);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 15%,transparent)}.finance-category-new .settings-swatches{gap:5px}.finance-category-new .settings-swatches button,.finance-category-settings-card .settings-swatches button{width:18px;height:18px;border:1.5px solid #3a2e1f;border-radius:6px;box-shadow:none}.finance-category-new>button{height:38px;border:1.5px solid #3a2e1f;border-radius:12px;background:var(--c);color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-size:12px}.finance-category-list{gap:8px;padding:12px 14px}.finance-category-settings-card{grid-template-columns:26px 12px minmax(0,1fr) auto;gap:8px;border:1.5px solid #eadfbe;border-right:5px solid var(--c);border-radius:13px;background:#fff;box-shadow:none;padding:9px 10px}.finance-category-settings-card:hover{border-color:color-mix(in srgb,var(--c) 42%,#eadfbe);box-shadow:2px 2px 0 rgba(58,46,31,.16)}.finance-category-settings-card .drag-handle{width:26px;height:26px;border:1.5px solid #eadfbe;border-radius:9px;background:#fffbf0;box-shadow:none;color:#8a7a5b;font-size:12px}.finance-category-settings-card i{width:10px;height:10px;box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 16%,white)}.finance-category-settings-card input{height:30px;border:0;font-size:13px}.finance-category-settings-card .active-toggle{padding:3px 7px!important;border-color:#eadfbe;background:#fffbf0}.finance-category-settings-card .active-toggle input{width:14px!important;height:14px!important}.finance-category-settings-card .settings-swatches{gap:5px;padding:2px 34px 0 0}.finance-category-settings-card footer{padding-top:7px;border-top:1px dashed rgba(58,46,31,.14)}.finance-category-settings-card footer small{font-size:10px;color:#9a8b6a}.finance-category-settings-card footer button{height:28px;border:1.5px solid #3a2e1f;border-radius:9px;box-shadow:none;padding:0 10px;font-size:11px}.finance-category-settings-card footer button:not(.delete){background:#fffbf0;color:#0f766e}.finance-category-settings-card footer .delete{color:#dc2626;background:#fff}.finance-category-settings-card.inactive{opacity:.45}.finance-category-settings-card.dragging{transform:scale(.99)}
.new-card{border-style:dashed;background:#fff}.settings-loading{min-height:320px;display:grid;place-items:center;font-weight:900}
.settings-support-section{display:grid;grid-template-columns:300px minmax(0,1fr);gap:14px;margin-top:30px;padding-top:26px;border-top:2px dashed #eadfbe}.support-intro,.support-form-card,.support-ticket-list article,.support-empty{border:2px solid #3a2e1f;border-radius:16px;background:#fff;box-shadow:4px 4px 0 #3a2e1f}.support-intro{align-self:start;display:grid;gap:8px;padding:18px;background:linear-gradient(135deg,#fff,#eef2ff)}.support-intro span{width:max-content;border:2px solid #7c3aed;border-radius:999px;background:#f3e8ff;color:#6d28d9;padding:4px 11px;font-size:11px;font-weight:900}.support-intro strong{font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;line-height:1.5}.support-intro p{margin:0;color:#7a6a4f;font-size:13px;font-weight:900;line-height:1.9}.support-intro button{height:42px;margin-top:6px;border:2px solid #3a2e1f;border-radius:12px;background:#7c3aed;color:#fff;box-shadow:3px 3px 0 #3a2e1f;font-weight:900}.support-form-card{display:grid;gap:12px;padding:16px;background:linear-gradient(180deg,#fff,#fffbf0)}.support-form-card label{display:grid;gap:7px;color:#4b3b22;font-size:12px;font-weight:900}.support-form-card input,.support-form-card textarea{border:2px solid #3a2e1f;border-radius:13px;background:#fffbf0;font-weight:900;color:#3a2e1f}.support-form-card textarea{min-height:112px;line-height:1.9}.support-form-card>button{height:46px;border:2px solid #3a2e1f;border-radius:13px;background:#22d3d0;color:#fff;box-shadow:3px 3px 0 #3a2e1f;font-weight:900}.support-form-card>button:disabled{opacity:.45}.support-success,.support-error{margin:0;font-size:12px;font-weight:900}.support-success{color:#15803d}.support-error{color:#dc2626}.support-ticket-list{grid-column:1/-1;display:grid;gap:10px}.support-ticket-list article{display:grid;gap:8px;padding:14px}.support-ticket-list article.answered{background:#f8fafc;box-shadow:3px 3px 0 rgba(58,46,31,.72)}.support-ticket-list header{display:flex;align-items:center;justify-content:space-between;gap:10px}.support-ticket-list header>div{display:inline-flex;align-items:center;gap:7px;flex-shrink:0}.support-ticket-list strong{font-family:Lalezar,Vazirmatn,sans-serif;font-size:22px}.support-ticket-list header span{height:28px;display:inline-flex;align-items:center;border-radius:999px;padding:0 10px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:900}.support-ticket-list header button{width:28px;height:28px;display:grid;place-items:center;border:1.5px solid #fecaca;border-radius:999px;background:#fff;color:#dc2626;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:0}.support-ticket-list article.answered header span{background:#dcfce7;color:#15803d}.support-ticket-list p{margin:0;color:#4b3b22;font-size:13px;font-weight:800;line-height:1.9;white-space:pre-wrap}.support-ticket-list small{color:#9a8b6a;font-size:10.5px;font-weight:900}.support-reply{margin-top:4px;padding:12px;border:1.5px dashed #16a34a;border-radius:13px;background:#ecfdf5}.support-reply b{display:block;margin-bottom:5px;color:#15803d}.support-empty{min-height:90px;display:grid;place-items:center;color:#9a8b6a;font-weight:900}
@media(max-width:900px){.category-settings-grid,.account-settings-grid,.finance-category-settings{grid-template-columns:repeat(2,1fr)}.priority-settings-grid{grid-template-columns:repeat(2,1fr)}.settings-hero{align-items:flex-start;flex-direction:column}.settings-preview,.priority-preview{justify-content:flex-start}}
@media(max-width:700px){.finance-category-settings,.settings-support-section{grid-template-columns:1fr}.finance-category-settings-card{grid-template-columns:auto minmax(0,1fr) auto}.finance-category-settings-card i{display:none}.support-ticket-list{grid-column:auto}}
@media(max-width:560px){.settings-shell{padding:18px 8px 34px}.settings-page{padding:24px 12px 30px}.category-settings-grid,.priority-settings-grid,.account-settings-grid,.finance-category-settings{grid-template-columns:1fr}.settings-title{font-size:28px}.settings-header{align-items:flex-start}.settings-actions{width:100%;justify-content:space-between}.settings-hero{padding:14px}.settings-drawer{left:12px;top:88px}}
@media(max-width:640px){.settings-modal-backdrop{padding:10px;align-items:start;overflow:auto}.settings-modal{max-height:none;margin:10px 0;padding:14px;border-radius:16px;box-shadow:5px 5px 0 rgba(58,46,31,.92)}.group-modal-toolbar{align-items:stretch;flex-direction:column}.group-modal-toolbar button{width:max-content}.group-table-wrap{overflow:visible;border:0;background:transparent}.group-table{min-width:0;border-spacing:0;display:block}.group-table thead{display:none}.group-table tbody{display:grid;gap:12px}.group-table tr{display:grid!important;grid-template-columns:1fr;gap:10px;border:1.5px solid #eadfbe;border-right:6px solid var(--g);border-radius:14px;background:#fff;padding:12px;box-shadow:2px 2px 0 rgba(58,46,31,.16)}.group-table td{display:grid;grid-template-columns:82px minmax(0,1fr);align-items:center;gap:10px;padding:0;border:0}.group-table td::before{content:attr(data-label);color:#8a7a5b;font-size:11px;font-weight:900}.group-table td[data-label="نام گروه"]{grid-template-columns:1fr}.group-table td[data-label="نام گروه"]::before{margin-bottom:-4px}.group-name-cell input{height:42px;font-size:14px}.group-color-cell{gap:7px}.group-color-cell button{width:25px;height:25px}.group-row-actions{justify-content:flex-start}.group-row-actions button{height:34px}.group-status-toggle{justify-self:start}.mini-drag{width:34px;height:34px}}
</style>
