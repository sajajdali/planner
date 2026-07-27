<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';
import { useAuthStore } from '../stores/auth';

type Category = { id: number; name: string; color: string; soft_color: string; icon: string; is_default?: boolean; is_active: boolean };
type CategoryDraft = { name: string; color: string; icon: string; is_active: boolean };
type PrioritySetting = { id: number; key: string; label: string; color: string; soft_color: string; is_default?: boolean };
type PriorityDraft = { label: string; color: string };
type FinancialAccount = { id: number; name: string; color: string; initial_balance: number; card_number?: string | null; sheba_number?: string | null; income_total: number; expense_total: number; current_balance: number; is_default?: boolean; is_active: boolean };
type AccountDraft = { name: string; color: string; initial_balance: string; card_number: string; sheba_number: string; is_active: boolean };
type FinanceCategory = { id: number; name: string; color: string; soft_color: string; type: 'expense' | 'income'; is_default?: boolean; is_active: boolean };
type FinanceCategoryDraft = { name: string; color: string; is_active: boolean };

const router = useRouter();
const auth = useAuthStore();
const loading = ref(true);
const savingId = ref<number | null>(null);
const savingPriorityId = ref<number | null>(null);
const categories = ref<Category[]>([]);
const priorities = ref<PrioritySetting[]>([]);
const accounts = ref<FinancialAccount[]>([]);
const financeCategories = ref<FinanceCategory[]>([]);
const drafts = ref<Record<number, CategoryDraft>>({});
const priorityDrafts = ref<Record<number, PriorityDraft>>({});
const accountDrafts = ref<Record<number, AccountDraft>>({});
const financeCategoryDrafts = ref<Record<number, FinanceCategoryDraft>>({});
const draggedCategoryId = ref<number | null>(null);
const draggedAccountId = ref<number | null>(null);
const draggedFinanceCategoryId = ref<number | null>(null);
const expandedIconPicker = ref<Record<string, boolean>>({});
const newCategory = ref<CategoryDraft>({ name: '', color: '#D63384', icon: 'briefcase', is_active: true });
const newPriority = ref<PriorityDraft>({ label: '', color: '#0F766E' });
const newAccount = ref<AccountDraft>({ name: '', color: '#22D3D0', initial_balance: '', card_number: '', sheba_number: '', is_active: true });
const newFinanceCategory = ref<Record<'expense' | 'income', FinanceCategoryDraft>>({
    expense: { name: '', color: '#F43F5E', is_active: true },
    income: { name: '', color: '#16A34A', is_active: true },
});

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
const financeCategoryGroups = computed(() => ({
    expense: financeCategories.value.filter((category) => category.type === 'expense'),
    income: financeCategories.value.filter((category) => category.type === 'income'),
}));

onMounted(loadCategories);

async function loadCategories() {
    loading.value = true;
    const [{ data: categoryData }, { data: priorityData }, { data: accountData }, { data: financeCategoryData }] = await Promise.all([
        api.get('/categories', { params: { include_inactive: 1 } }),
        api.get('/priorities'),
        api.get('/financial-accounts', { params: { include_inactive: 1 } }),
        api.get('/expense-categories', { params: { include_inactive: 1 } }),
    ]);
    categories.value = categoryData;
    priorities.value = priorityData;
    accounts.value = accountData;
    financeCategories.value = financeCategoryData;
    drafts.value = Object.fromEntries(categories.value.map((category) => [category.id, draftFrom(category)]));
    priorityDrafts.value = Object.fromEntries(priorities.value.map((priority) => [priority.id, priorityDraftFrom(priority)]));
    accountDrafts.value = Object.fromEntries(accounts.value.map((account) => [account.id, accountDraftFrom(account)]));
    financeCategoryDrafts.value = Object.fromEntries(financeCategories.value.map((category) => [category.id, financeCategoryDraftFrom(category)]));
    loading.value = false;
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

async function logout() {
    await auth.logout();
    window.location.href = '/login';
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
.priority-hero{margin-top:28px;background:linear-gradient(120deg,#22d3d0,#ffd93d 55%,#ff6fa5)}.priority-preview{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.priority-preview span{min-height:30px;display:inline-flex;align-items:center;justify-content:center;padding:4px 12px;border:2px solid currentColor;border-radius:999px;background:#fff;font-size:12px;font-weight:900}
.priority-settings-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:10px}.priority-settings-card{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);border:2px solid #3a2e1f;border-radius:16px;padding:14px;box-shadow:4px 4px 0 #3a2e1f;display:grid;gap:10px;position:relative;overflow:hidden}.priority-settings-card::before{content:'';position:absolute;left:-34px;bottom:-44px;width:118px;height:118px;border-radius:50%;background:color-mix(in srgb,var(--c) 16%,transparent)}.priority-chip-preview{width:max-content;max-width:100%;min-height:34px;display:inline-flex;align-items:center;justify-content:center;padding:5px 14px;border:2px solid #3a2e1f;border-radius:999px;box-shadow:2px 2px 0 #3a2e1f;color:#fff;font-weight:900;position:relative;z-index:1}.priority-settings-card label{display:grid;gap:6px;position:relative;z-index:1}.priority-settings-card label span{font-size:11px;color:#7a6a4f;font-weight:900}.priority-settings-card input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.priority-settings-card input:focus{box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 22%,transparent)}.priority-settings-card footer{display:flex;align-items:center;gap:8px;position:relative;z-index:1;padding-top:8px;border-top:1px dashed rgba(58,46,31,.25)}.priority-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.priority-settings-card footer button{height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.priority-settings-card footer button:disabled{opacity:.45;cursor:not-allowed}.priority-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-hero{margin-top:28px;background:linear-gradient(120deg,#34d399,#22d3d0 55%,#2563eb)}.account-settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.account-settings-card{background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);border:2px solid #3a2e1f;border-radius:16px;padding:14px;box-shadow:4px 4px 0 #3a2e1f;display:grid;gap:10px;position:relative;overflow:hidden}.account-settings-card.inactive{opacity:.58;filter:grayscale(.35)}.account-settings-card.dragging{opacity:.42;transform:scale(.98)}.account-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.account-card-head>div{margin-left:auto}.account-card-head span,.account-settings-card label span{font-size:11px;color:#7a6a4f;font-weight:900}.account-card-head strong{display:block;color:#3a2e1f;font-size:16px}.account-balance-box{border:1.5px dashed color-mix(in srgb,var(--c) 42%,#eadfbe);border-radius:14px;background:#fffbf0;padding:10px;display:grid;gap:3px}.account-balance-box span,.account-balance-box small{color:#7a6a4f;font-size:10.5px;font-weight:900}.account-balance-box strong{font-size:16px;color:var(--c)}.account-settings-card label{display:grid;gap:6px;position:relative;z-index:1}.account-settings-card input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-family:inherit;font-weight:900;color:#3a2e1f;outline:0}.account-settings-card footer{display:flex;align-items:center;gap:8px;position:relative;z-index:1;padding-top:8px;border-top:1px dashed rgba(58,46,31,.25)}.account-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.account-settings-card footer button{height:32px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.account-settings-card footer button:disabled{opacity:.45;cursor:not-allowed}.account-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-category-settings{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.finance-category-panel{border:2px solid #3a2e1f;border-radius:16px;background:#fff;box-shadow:4px 4px 0 #3a2e1f;overflow:hidden}.finance-category-panel header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#fff4e6;border-bottom:2px solid #eadfbe}.finance-category-panel.income header{background:#ecfdf5}.finance-category-panel header span{font-size:12px;color:#7a6a4f;font-weight:900}.finance-category-panel header strong{display:block;margin-top:2px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#3a2e1f}.finance-category-new{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:9px;padding:14px 16px;border-bottom:1px dashed rgba(58,46,31,.24);background:linear-gradient(90deg,color-mix(in srgb,var(--c) 8%,white),#fff)}.finance-category-new input{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#fffbf0;padding:0 10px;font-weight:900;color:#3a2e1f;outline:0}.finance-category-new .settings-swatches{grid-column:1/-1}.finance-category-new>button{height:40px;border:2px solid #3a2e1f;border-radius:11px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.finance-category-new>button:disabled{opacity:.45}.finance-category-list{display:grid;gap:10px;padding:14px 16px}.finance-category-settings-card{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;gap:9px;align-items:center;border:2px solid #3a2e1f;border-radius:14px;background:linear-gradient(180deg,color-mix(in srgb,var(--c) 12%,white),#fff);box-shadow:3px 3px 0 #3a2e1f;padding:10px}.finance-category-settings-card.inactive{opacity:.58;filter:grayscale(.35)}.finance-category-settings-card.dragging{opacity:.42}.finance-category-settings-card i{width:16px;height:16px;border-radius:50%;background:var(--c);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 18%,white)}.finance-category-settings-card input{min-width:0;height:36px;border:0;border-bottom:1px dashed rgba(58,46,31,.3);background:transparent;font-weight:900;color:#3a2e1f;outline:0}.finance-category-settings-card .settings-swatches{grid-column:1/-1}.finance-category-settings-card footer{grid-column:1/-1;display:flex;align-items:center;gap:8px;padding-top:8px;border-top:1px dashed rgba(58,46,31,.2)}.finance-category-settings-card footer small{margin-left:auto;color:#7a6a4f;font-size:10.5px;font-weight:900}.finance-category-settings-card footer button{height:31px;border:2px solid #3a2e1f;border-radius:10px;background:#ffd93d;box-shadow:2px 2px 0 #3a2e1f;padding:0 12px;font-weight:900;cursor:pointer}.finance-category-settings-card footer button:disabled{opacity:.45}.finance-category-settings-card footer .delete{background:#fff;color:#dc2626}
.finance-category-settings{gap:18px;margin-top:22px}.finance-category-panel{border:1.5px solid #eadfbe;border-radius:14px;background:rgba(255,255,255,.74);box-shadow:3px 3px 0 rgba(58,46,31,.9);backdrop-filter:blur(3px)}.finance-category-panel header{padding:13px 15px;background:linear-gradient(90deg,rgba(255,217,61,.16),rgba(255,255,255,.78));border-bottom:1px solid #efe3c4}.finance-category-panel.income header{background:linear-gradient(90deg,rgba(22,163,74,.12),rgba(255,255,255,.78))}.finance-category-panel header div{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%}.finance-category-panel header span{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#3a2e1f}.finance-category-panel header span::before{content:'';width:10px;height:10px;border-radius:50%;background:#f97316;box-shadow:0 0 0 4px rgba(249,115,22,.13)}.finance-category-panel.income header span::before{background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.13)}.finance-category-panel header strong{margin:0;padding:4px 10px;border:1px solid #eadfbe;border-radius:999px;background:#fffbf0;font-family:Vazirmatn,sans-serif;font-size:11px;color:#7a6a4f}.finance-category-new{grid-template-columns:minmax(0,1fr) 78px;gap:8px;padding:12px 14px;background:#fff;border-bottom:1px dashed rgba(58,46,31,.16)}.finance-category-new input{height:38px;border:1.5px solid #eadfbe;border-radius:12px;background:#fffbf0;box-shadow:none;font-size:12px}.finance-category-new input:focus{border-color:var(--c);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 15%,transparent)}.finance-category-new .settings-swatches{gap:5px}.finance-category-new .settings-swatches button,.finance-category-settings-card .settings-swatches button{width:18px;height:18px;border:1.5px solid #3a2e1f;border-radius:6px;box-shadow:none}.finance-category-new>button{height:38px;border:1.5px solid #3a2e1f;border-radius:12px;background:var(--c);color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-size:12px}.finance-category-list{gap:8px;padding:12px 14px}.finance-category-settings-card{grid-template-columns:26px 12px minmax(0,1fr) auto;gap:8px;border:1.5px solid #eadfbe;border-right:5px solid var(--c);border-radius:13px;background:#fff;box-shadow:none;padding:9px 10px}.finance-category-settings-card:hover{border-color:color-mix(in srgb,var(--c) 42%,#eadfbe);box-shadow:2px 2px 0 rgba(58,46,31,.16)}.finance-category-settings-card .drag-handle{width:26px;height:26px;border:1.5px solid #eadfbe;border-radius:9px;background:#fffbf0;box-shadow:none;color:#8a7a5b;font-size:12px}.finance-category-settings-card i{width:10px;height:10px;box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 16%,white)}.finance-category-settings-card input{height:30px;border:0;font-size:13px}.finance-category-settings-card .active-toggle{padding:3px 7px!important;border-color:#eadfbe;background:#fffbf0}.finance-category-settings-card .active-toggle input{width:14px!important;height:14px!important}.finance-category-settings-card .settings-swatches{gap:5px;padding:2px 34px 0 0}.finance-category-settings-card footer{padding-top:7px;border-top:1px dashed rgba(58,46,31,.14)}.finance-category-settings-card footer small{font-size:10px;color:#9a8b6a}.finance-category-settings-card footer button{height:28px;border:1.5px solid #3a2e1f;border-radius:9px;box-shadow:none;padding:0 10px;font-size:11px}.finance-category-settings-card footer button:not(.delete){background:#fffbf0;color:#0f766e}.finance-category-settings-card footer .delete{color:#dc2626;background:#fff}.finance-category-settings-card.inactive{opacity:.45}.finance-category-settings-card.dragging{transform:scale(.99)}
.new-card{border-style:dashed;background:#fff}.settings-loading{min-height:320px;display:grid;place-items:center;font-weight:900}
@media(max-width:900px){.category-settings-grid,.account-settings-grid,.finance-category-settings{grid-template-columns:repeat(2,1fr)}.priority-settings-grid{grid-template-columns:repeat(2,1fr)}.settings-hero{align-items:flex-start;flex-direction:column}.settings-preview,.priority-preview{justify-content:flex-start}}
@media(max-width:700px){.finance-category-settings{grid-template-columns:1fr}.finance-category-settings-card{grid-template-columns:auto minmax(0,1fr) auto}.finance-category-settings-card i{display:none}}
@media(max-width:560px){.settings-shell{padding:18px 8px 34px}.settings-page{padding:24px 12px 30px}.category-settings-grid,.priority-settings-grid,.account-settings-grid,.finance-category-settings{grid-template-columns:1fr}.settings-title{font-size:28px}.settings-header{align-items:flex-start}.settings-actions{width:100%;justify-content:space-between}.settings-hero{padding:14px}.settings-drawer{left:12px;top:88px}}
</style>
