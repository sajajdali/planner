<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js';
import api from '../api';
import { useAuthStore } from '../stores/auth';

type Account = { id: number; name: string; color: string; current_balance: number; expense_total: number; is_default?: boolean };
type FinanceCategory = { id: number; name: string; type: 'expense' | 'income' };
type Tx = { id: number; title: string; amount: number; type: 'expense' | 'income'; expense_date: string; note?: string | null; category?: { name: string } | null; account?: { name: string } | null };
type Obligation = { id: number; type: 'installment' | 'debt'; title: string; party_name?: string | null; total_amount: number; paid_amount: number; remaining_amount: number; installment_amount: number; installments_total?: number | null; paid_count: number; due_day?: number | null; due_date?: string | null; color: string; progress: number; current_due: number; account?: Account | null };

const router = useRouter();
const auth = useAuthStore();
const loading = ref(true);
const drawerOpen = ref(false);
const calendarOpen = ref(false);
const dayModal = ref<{ date: string; day: number } | null>(null);
const modal = ref<'transaction' | 'installment' | 'debt' | null>(null);
const jy = ref(1405);
const jm = ref(1);
const accounts = ref<Account[]>([]);
const categories = ref<FinanceCategory[]>([]);
const transactions = ref<Tx[]>([]);
const installments = ref<Obligation[]>([]);
const debts = ref<Obligation[]>([]);
const totals = ref({ income: 0, expense: 0, debt: 0, due_this_month: 0 });
const txForm = ref({ type: 'expense' as 'expense' | 'income', title: '', amount: '', account_id: '', category_id: '', date: '', note: '' });
const obligationForm = ref({ title: '', party_name: '', total_amount: '', installment_amount: '', installments_total: '', due_day: '', date: '', account_id: '', note: '' });

const monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
const weekNames = ['ش','ی','د','س','چ','پ','ج'];
const faDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
const palette = ['#2563EB', '#F97316', '#16A34A', '#9B5DE5'];
const monthLabel = computed(() => `${monthNames[jm.value - 1]} ${fa(jy.value)}`);
const startEnd = computed(() => {
    const start = toGregorian(jy.value, jm.value, 1);
    const end = toGregorian(jy.value, jm.value, jalaaliMonthLength(jy.value, jm.value));
    return { start: ymd(start.gy, start.gm, start.gd), end: ymd(end.gy, end.gm, end.gd) };
});
const totalBalance = computed(() => accounts.value.reduce((sum, account) => sum + account.current_balance, 0));
const walletBalance = computed(() => accounts.value.find((account) => account.is_default)?.current_balance ?? accounts.value[0]?.current_balance ?? 0);
const deposits = computed(() => transactions.value.filter((item) => item.type === 'income').slice(0, 5));
const withdrawals = computed(() => transactions.value.filter((item) => item.type === 'expense').slice(0, 5));
const txByDate = computed(() => {
    const map = new Map<string, Tx[]>();
    transactions.value.forEach((item) => map.set(item.expense_date, [...(map.get(item.expense_date) ?? []), item]));
    return map;
});
const calendarCells = computed(() => {
    const first = new Date(startEnd.value.start);
    const offset = (first.getDay() + 1) % 7;
    const cells: Array<{ empty?: boolean; day?: number; date?: string; income?: number; expense?: number; today?: boolean }> = [];
    for (let i = 0; i < offset; i++) cells.push({ empty: true });
    const today = new Date();
    const tj = toJalaali(today.getFullYear(), today.getMonth() + 1, today.getDate());
    for (let day = 1; day <= jalaaliMonthLength(jy.value, jm.value); day++) {
        const g = toGregorian(jy.value, jm.value, day);
        const date = ymd(g.gy, g.gm, g.gd);
        const items = txByDate.value.get(date) ?? [];
        cells.push({
            day,
            date,
            income: items.filter((item) => item.type === 'income').reduce((sum, item) => sum + item.amount, 0),
            expense: items.filter((item) => item.type === 'expense').reduce((sum, item) => sum + item.amount, 0),
            today: tj.jy === jy.value && tj.jm === jm.value && tj.jd === day,
        });
    }
    return cells;
});
const selectedDayItems = computed(() => dayModal.value ? (txByDate.value.get(dayModal.value.date) ?? []) : []);
const selectedDayIncome = computed(() => selectedDayItems.value.filter((item) => item.type === 'income').reduce((sum, item) => sum + item.amount, 0));
const selectedDayExpense = computed(() => selectedDayItems.value.filter((item) => item.type === 'expense').reduce((sum, item) => sum + item.amount, 0));

onMounted(() => {
    const now = new Date();
    const j = toJalaali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    jy.value = j.jy;
    jm.value = j.jm;
    txForm.value.date = ymd(now.getFullYear(), now.getMonth() + 1, now.getDate());
    obligationForm.value.date = txForm.value.date;
    void load();
});

async function load() {
    loading.value = true;
    const { data } = await api.get('/finance-dashboard', { params: startEnd.value });
    accounts.value = data.accounts ?? [];
    categories.value = data.expenseCategories ?? [];
    transactions.value = data.transactions ?? [];
    installments.value = data.installments ?? [];
    debts.value = data.debts ?? [];
    totals.value = data.totals ?? totals.value;
    txForm.value.account_id ||= String(accounts.value[0]?.id ?? '');
    txForm.value.category_id ||= String(categories.value.find((c) => c.type === txForm.value.type)?.id ?? '');
    obligationForm.value.account_id ||= String(accounts.value[0]?.id ?? '');
    loading.value = false;
}

function fa(value: string | number) {
    return String(value).replace(/\d/g, (digit) => faDigits[Number(digit)]);
}
function en(value: string) {
    return String(value).replace(/[۰-۹]/g, (digit) => String(faDigits.indexOf(digit)));
}
function ymd(y: number, m: number, d: number) {
    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}
function money(value: number | string) {
    return `${fa(Number(value || 0).toLocaleString('en-US'))} تومان`;
}
function shortMoney(value: number) {
    if (Math.abs(value) >= 1000000) return `${fa((value / 1000000).toFixed(value % 1000000 ? 1 : 0))}م`;
    if (Math.abs(value) >= 1000) return `${fa(Math.round(value / 1000))}ه`;
    return fa(value);
}
function moneyNumber(value: string) {
    return Number(en(value).replace(/[,\s]/g, '')) || 0;
}
function moneyInput(value: string) {
    return en(value).replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function dateLabel(date?: string | null) {
    if (!date) return 'بدون تاریخ';
    const d = new Date(date);
    const j = toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
    return `${fa(j.jd)} ${monthNames[j.jm - 1]} ${fa(j.jy)}`;
}
function gregorianDateLabel(date?: string) {
    if (!date) return '';
    const [year, month, day] = date.split('-');
    return `${month}/${day}/${year}`;
}
function shiftMonth(delta: number) {
    let total = jy.value * 12 + (jm.value - 1) + delta;
    jy.value = Math.floor(total / 12);
    jm.value = (total % 12) + 1;
    void load();
}
function setThisMonth() {
    const now = new Date();
    const j = toJalaali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    jy.value = j.jy;
    jm.value = j.jm;
    void load();
}
function openModal(type: 'transaction' | 'installment' | 'debt') {
    modal.value = type;
}
function closeModal() {
    modal.value = null;
}
function categoryOptions(type = txForm.value.type) {
    return categories.value.filter((item) => item.type === type);
}
async function createTransaction() {
    const amount = moneyNumber(txForm.value.amount);
    if (!txForm.value.title.trim() || !amount || !txForm.value.account_id || !txForm.value.category_id) return;
    await api.post('/expenses', {
        title: txForm.value.title.trim(),
        amount,
        type: txForm.value.type,
        expense_category_id: Number(txForm.value.category_id),
        financial_account_id: Number(txForm.value.account_id),
        expense_date: txForm.value.date,
        note: txForm.value.note || null,
    });
    txForm.value.title = '';
    txForm.value.amount = '';
    txForm.value.note = '';
    closeModal();
    await load();
}
async function createObligation(type: 'installment' | 'debt') {
    const total = moneyNumber(obligationForm.value.total_amount);
    if (!obligationForm.value.title.trim() || !total) return;
    await api.post('/finance-obligations', {
        type,
        title: obligationForm.value.title.trim(),
        party_name: obligationForm.value.party_name || null,
        total_amount: total,
        installment_amount: type === 'installment' ? moneyNumber(obligationForm.value.installment_amount) : null,
        installments_total: type === 'installment' ? Number(en(obligationForm.value.installments_total || '1')) : null,
        due_day: type === 'installment' ? Number(en(obligationForm.value.due_day || '1')) : null,
        due_date: type === 'debt' ? obligationForm.value.date : null,
        start_date: type === 'installment' ? obligationForm.value.date : null,
        financial_account_id: obligationForm.value.account_id ? Number(obligationForm.value.account_id) : null,
        note: obligationForm.value.note || null,
    });
    obligationForm.value = { title: '', party_name: '', total_amount: '', installment_amount: '', installments_total: '', due_day: '', date: txForm.value.date, account_id: String(accounts.value[0]?.id ?? ''), note: '' };
    closeModal();
    await load();
}
async function payObligation(item: Obligation) {
    await api.post(`/finance-obligations/${item.id}/pay`, {
        paid_date: txForm.value.date,
        amount: item.current_due || item.remaining_amount,
        financial_account_id: item.account?.id ?? accounts.value[0]?.id,
    });
    await load();
}
async function logout() {
    await auth.logout();
    window.location.href = '/login';
}
</script>

<template>
    <div class="finance-shell" dir="rtl">
        <div class="finance-page">
            <i class="tape yellow"></i><i class="tape cyan"></i>
            <header class="finance-header">
                <div>
                    <h1>مدیریت مالی شخصی</h1>
                    <p>حساب‌ها، بدهی‌ها، اقساط و تراکنش‌ها در یک نگاه</p>
                </div>
                <div class="finance-actions">
                    <button class="green" @click="openModal('transaction')">+ ثبت تراکنش</button>
                    <button class="menu" @click="drawerOpen = !drawerOpen"><span></span><span></span><span></span></button>
                </div>
            </header>
            <aside v-if="drawerOpen" class="finance-drawer">
                <button @click="router.push('/app')">برنامه امروز</button>
                <button @click="router.push('/reports/monthly')">گزارش‌ها</button>
                <button @click="router.push('/settings')">تنظیمات</button>
                <button class="danger" @click="logout">خروج از حساب {{ auth.user?.name }}</button>
            </aside>

            <main v-if="!loading">
                <div class="month-nav">
                    <button class="cyan" @click="shiftMonth(-1)">‹</button>
                    <strong>{{ monthLabel }}</strong>
                    <button class="yellow" @click="shiftMonth(1)">›</button>
                    <button class="pink" @click="setThisMonth">ماه جاری</button>
                </div>

                <section class="finance-stats">
                    <article class="green"><span>موجودی کل</span><b>{{ money(totalBalance) }}</b></article>
                    <article class="orange"><span>موجودی کیف پول</span><b>{{ money(walletBalance) }}</b></article>
                    <article class="red"><span>جمع کل بدهی‌ها</span><b>{{ money(totals.debt) }}</b></article>
                    <article class="purple"><span>اقساط باقی‌مانده این ماه</span><b>{{ money(totals.due_this_month) }}</b></article>
                </section>

                <div class="section-title"><i style="background:#2563EB"></i><b>حساب‌های من</b></div>
                <section class="account-cards">
                    <article v-for="(account, index) in accounts" :key="account.id" :style="{ '--c': account.color, '--bg': `linear-gradient(135deg, ${account.color}, ${palette[index % palette.length]})` }">
                        <i>▣</i><small>{{ account.is_default ? 'پیش‌فرض' : 'حساب' }}</small>
                        <div><span>{{ account.name }}</span><b>{{ money(account.current_balance) }}</b></div>
                    </article>
                    <button @click="router.push('/settings')">+ افزودن حساب</button>
                </section>

                <section class="finance-block-head">
                    <div class="section-title"><i style="background:#7C3AED"></i><b>اقساط این ماه</b></div>
                    <button @click="openModal('installment')">+ ثبت قسط جدید</button>
                </section>
                <section class="installment-list">
                    <article v-for="item in installments" :key="item.id">
                        <i :style="{ background: item.color }">◷</i>
                        <div>
                            <strong>{{ item.title }} <em>{{ item.remaining_amount ? 'فعال' : 'پرداخت شده' }}</em></strong>
                            <span>سررسید: {{ fa(item.due_day || '-') }} ام هر ماه · {{ fa(item.paid_count) }} از {{ fa(item.installments_total || 1) }} قسط پرداخت شده</span>
                            <mark><b :style="{ width: `${item.progress}%`, background: item.color }"></b></mark>
                        </div>
                        <aside><b>{{ money(item.installment_amount) }}</b><button v-if="item.remaining_amount" @click="payObligation(item)">ثبت پرداخت</button></aside>
                    </article>
                    <div v-if="!installments.length" class="finance-empty-table installment-empty">
                        <span>عنوان قسط</span><span>سررسید</span><span>مبلغ</span><span>وضعیت</span>
                        <b>قسط فعالی برای این ماه ثبت نشده.</b>
                    </div>
                </section>

                <section class="finance-block-head">
                    <div class="section-title"><i style="background:#DC2626"></i><b>بدهی‌ها به تفکیک گروه</b></div>
                    <button class="pink" @click="openModal('debt')">+ ثبت بدهی جدید</button>
                </section>
                <section class="debt-list">
                    <article v-for="item in debts" :key="item.id">
                        <header><span>{{ item.title }}</span><b>{{ money(item.remaining_amount) }}</b></header>
                        <div><span>طرف حساب: {{ item.party_name || 'ثبت نشده' }} · موعد: {{ dateLabel(item.due_date) }}</span><button v-if="item.remaining_amount" @click="payObligation(item)">پرداخت</button></div>
                        <mark><b :style="{ width: `${item.progress}%` }"></b></mark>
                    </article>
                    <div v-if="!debts.length" class="finance-empty-table debt-empty">
                        <span>عنوان بدهی</span><span>طرف حساب</span><span>مانده</span><span>پرداخت</span>
                        <b>بدهی فعالی ثبت نشده.</b>
                    </div>
                </section>

                <section class="finance-block-head">
                    <div class="section-title"><i style="background:#16A34A"></i><b>آخرین تراکنش‌ها</b></div>
                    <button @click="calendarOpen = !calendarOpen">مشاهده تقویم روزانه</button>
                </section>
                <p class="tx-summary">{{ fa(transactions.length) }} تراکنش · واریز {{ money(totals.income) }} · برداشت {{ money(totals.expense) }}</p>
                <section class="tx-grid">
                    <div><h3>آخرین واریزها</h3><article v-for="item in deposits" :key="item.id"><i></i><span>{{ item.title }}<small>{{ item.account?.name }} · {{ dateLabel(item.expense_date) }}</small></span><b>+ {{ money(item.amount) }}</b></article><div v-if="!deposits.length" class="finance-empty-table tx-empty"><span>عنوان</span><span>حساب</span><span>تاریخ</span><span>مبلغ</span><b>واریزی ثبت نشده.</b></div></div>
                    <div><h3 class="red">آخرین برداشت‌ها</h3><article v-for="item in withdrawals" :key="item.id" class="out"><i></i><span>{{ item.title }}<small>{{ item.account?.name }} · {{ dateLabel(item.expense_date) }}</small></span><b>- {{ money(item.amount) }}</b></article><div v-if="!withdrawals.length" class="finance-empty-table tx-empty"><span>عنوان</span><span>حساب</span><span>تاریخ</span><span>مبلغ</span><b>برداشتی ثبت نشده.</b></div></div>
                </section>

                <section v-if="calendarOpen" class="finance-calendar">
                    <div class="calendar-stats"><b>{{ money(totals.income) }}</b><b class="out">{{ money(totals.expense) }}</b><b>{{ money(totals.income - totals.expense) }}</b></div>
                    <div class="weekdays"><span v-for="wd in weekNames" :key="wd">{{ wd }}</span></div>
                    <div class="calendar-grid">
                        <button v-for="(cell, index) in calendarCells" :key="index" :class="{ empty: cell.empty, today: cell.today }" @click="cell.date && (dayModal = { date: cell.date, day: cell.day! })">
                            <template v-if="!cell.empty"><strong>{{ fa(cell.day!) }}</strong><span v-if="cell.income">+{{ shortMoney(cell.income) }}</span><em v-if="cell.expense">-{{ shortMoney(cell.expense) }}</em></template>
                        </button>
                    </div>
                </section>
            </main>
            <div v-else class="finance-loading">در حال بارگذاری مدیریت مالی...</div>
        </div>

        <div v-if="dayModal" class="finance-modal-backdrop"><section class="finance-modal">
            <header><h2>تراکنش‌های {{ fa(dayModal.day) }} {{ monthNames[jm - 1] }}</h2><button @click="dayModal = null">×</button></header>
            <div class="day-totals"><span>واریز {{ money(selectedDayIncome) }}</span><span>برداشت {{ money(selectedDayExpense) }}</span></div>
            <article v-for="item in selectedDayItems" :key="item.id" class="modal-tx" :class="item.type"><i></i><span>{{ item.title }}<small>{{ item.account?.name }}</small></span><b>{{ item.type === 'income' ? '+' : '-' }} {{ money(item.amount) }}</b></article>
            <p v-if="!selectedDayItems.length">تراکنشی در این روز ثبت نشده.</p>
        </section></div>

        <div v-if="modal" class="finance-modal-backdrop"><section class="finance-modal">
            <header><h2>{{ modal === 'transaction' ? 'ثبت تراکنش' : modal === 'installment' ? 'ثبت قسط جدید' : 'ثبت بدهی جدید' }}</h2><button @click="closeModal">×</button></header>
            <template v-if="modal === 'transaction'">
                <div class="seg"><button :class="{ active: txForm.type === 'income' }" @click="txForm.type = 'income'; txForm.category_id = String(categoryOptions('income')[0]?.id ?? '')">واریز</button><button :class="{ active: txForm.type === 'expense' }" @click="txForm.type = 'expense'; txForm.category_id = String(categoryOptions('expense')[0]?.id ?? '')">برداشت</button></div>
                <input v-model="txForm.title" placeholder="عنوان تراکنش" />
                <input v-model="txForm.amount" inputmode="numeric" placeholder="مبلغ" @input="txForm.amount = moneyInput(txForm.amount)" />
                <select v-model="txForm.account_id"><option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option></select>
                <select v-model="txForm.category_id"><option v-for="cat in categoryOptions()" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option></select>
                <label class="finance-date-field">
                    <span>{{ gregorianDateLabel(txForm.date) }}</span><i>▣</i>
                    <input v-model="txForm.date" type="date" />
                </label>
                <input v-model="txForm.note" placeholder="یادداشت اختیاری" />
                <button class="submit" @click="createTransaction">ثبت تراکنش</button>
            </template>
            <template v-else>
                <input v-model="obligationForm.title" placeholder="عنوان" />
                <input v-model="obligationForm.party_name" placeholder="طرف حساب" />
                <input v-model="obligationForm.total_amount" inputmode="numeric" placeholder="مبلغ کل" @input="obligationForm.total_amount = moneyInput(obligationForm.total_amount)" />
                <input v-if="modal === 'installment'" v-model="obligationForm.installment_amount" inputmode="numeric" placeholder="مبلغ هر قسط" @input="obligationForm.installment_amount = moneyInput(obligationForm.installment_amount)" />
                <input v-if="modal === 'installment'" v-model="obligationForm.installments_total" inputmode="numeric" placeholder="تعداد اقساط" />
                <input v-if="modal === 'installment'" v-model="obligationForm.due_day" inputmode="numeric" placeholder="روز سررسید در ماه" />
                <label class="finance-date-field">
                    <span>{{ gregorianDateLabel(obligationForm.date) }}</span><i>▣</i>
                    <input v-model="obligationForm.date" type="date" />
                </label>
                <select v-model="obligationForm.account_id"><option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option></select>
                <input v-model="obligationForm.note" placeholder="یادداشت اختیاری" />
                <button class="submit" @click="createObligation(modal)">{{ modal === 'installment' ? 'ثبت قسط' : 'ثبت بدهی' }}</button>
            </template>
        </section></div>
    </div>
</template>

<style scoped>
.finance-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 10%,#2e2140 0%,#1a1424 65%);padding:36px 20px 60px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}.finance-page{width:1060px;max-width:100%;margin:auto;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:10px;box-shadow:0 30px 60px rgba(0,0,0,.5);position:relative;padding:34px}.tape{position:absolute;top:-15px;width:100px;height:30px;opacity:.85}.tape.yellow{right:70px;background:#ffd93d;transform:rotate(-6deg)}.tape.cyan{left:90px;background:#22d3d0;transform:rotate(5deg)}.finance-header,.finance-actions,.month-nav,.finance-block-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.finance-header h1{font-family:Lalezar,Vazirmatn,sans-serif;margin:0;font-size:30px}.finance-header p,.tx-summary{margin:3px 0 0;color:#7a6a4f;font-size:12.5px;font-weight:800}.finance-actions button,.month-nav button,.finance-block-head button,.account-cards>button,.installment-list button,.debt-list button{height:38px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f;padding:0 13px;font-weight:900;cursor:pointer}.finance-actions .green,.installment-list button{background:#34d399;color:#fff}.finance-actions .menu{width:40px;display:grid;gap:4px;padding:8px}.finance-actions .menu span{height:3px;background:#3a2e1f;border-radius:4px}.finance-drawer{position:absolute;left:34px;top:84px;z-index:5;background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:4px 4px 0 #3a2e1f;padding:10px;display:grid;gap:8px;min-width:220px}.finance-drawer button{border:0;border-radius:10px;padding:10px;background:#dbeafe;font-weight:900;text-align:right}.finance-drawer .danger{background:#fee2e2;color:#991b1b}.month-nav{justify-content:flex-end;margin:20px 0}.month-nav strong{height:36px;min-width:140px;display:grid;place-items:center;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f}.month-nav .cyan{background:#22d3d0}.month-nav .yellow{background:#ffd93d}.month-nav .pink,.finance-block-head .pink{background:#ff6fa5;color:#fff}.finance-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:28px}.finance-stats article{border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;color:#fff}.finance-stats span{display:block;font-size:11px;opacity:.9}.finance-stats b{display:block;margin-top:6px;font-size:18px}.green{background:linear-gradient(135deg,#34d399,#059669)}.orange{background:linear-gradient(135deg,#ffb55e,#f97316)}.red{background:linear-gradient(135deg,#f87171,#b91c1c)}.purple{background:linear-gradient(135deg,#c084fc,#7c3aed)}.account-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:28px}.account-cards article{min-height:130px;background:var(--bg);border:2px solid #3a2e1f;border-radius:16px;padding:16px;box-shadow:4px 4px 0 #3a2e1f;color:#fff;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;position:relative}.account-cards i{width:32px;height:32px;border-radius:9px;background:rgba(255,255,255,.28);display:grid;place-items:center}.account-cards small{position:absolute;top:18px;left:16px}.account-cards span,.account-cards b{display:block}.account-cards button{border-style:dashed;height:auto;min-height:130px;color:#7a6a4f}.finance-block-head{margin:10px 0 12px}.section-title{display:flex;align-items:center;gap:8px}.section-title i{width:5px;height:20px;border-radius:3px}.section-title b{font-family:Lalezar,Vazirmatn,sans-serif;font-size:21px}.installment-list,.debt-list{display:grid;gap:10px;margin-bottom:28px}.installment-list article{background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:12px 16px;box-shadow:3px 3px 0 #3a2e1f;display:flex;gap:14px;align-items:center}.installment-list i{width:38px;height:38px;border-radius:10px;color:#fff;display:grid;place-items:center;flex-shrink:0}.installment-list div{flex:1}.installment-list strong{display:block}.installment-list em{font-style:normal;background:#34d399;color:#fff;border-radius:999px;padding:2px 8px;font-size:10px}.installment-list span{display:block;color:#9a8b6a;font-size:11px;margin-top:3px}.installment-list mark,.debt-list mark{display:block;height:6px;background:#f0ebd8;border-radius:4px;margin-top:7px;overflow:hidden}.installment-list mark b,.debt-list mark b{display:block;height:100%;border-radius:4px}.installment-list aside{text-align:left}.installment-list aside b{display:block;white-space:nowrap}.installment-list aside button{height:28px;margin-top:6px;font-size:11px}.debt-list article{background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:3px 3px 0 #3a2e1f;padding:12px}.debt-list header,.debt-list div{display:flex;justify-content:space-between;gap:10px;align-items:center}.debt-list header{font-weight:900}.debt-list header b{color:#dc2626}.debt-list div span{font-size:11px;color:#9a8b6a}.debt-list mark b{background:#dc2626}.tx-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.tx-grid h3{font-size:13px;color:#16a34a}.tx-grid h3.red{color:#dc2626}.tx-grid article,.modal-tx{display:flex;align-items:center;gap:9px;background:#fff;border:2px solid #3a2e1f;border-radius:11px;padding:9px 12px;box-shadow:2px 2px 0 #3a2e1f;margin-bottom:7px}.tx-grid i,.modal-tx i{width:8px;height:8px;border-radius:50%;background:#16a34a}.tx-grid .out i,.modal-tx.expense i{background:#dc2626}.tx-grid span,.modal-tx span{flex:1;font-size:12px;font-weight:800}.tx-grid small,.modal-tx small{display:block;color:#9a8b6a;font-size:10px}.tx-grid b{color:#16a34a}.tx-grid .out b,.modal-tx.expense b{color:#dc2626}.finance-calendar{margin-top:18px;background:#fff;border:2px solid #3a2e1f;border-radius:16px;box-shadow:4px 4px 0 #3a2e1f;padding:16px}.calendar-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}.calendar-stats b{padding:10px;border-radius:10px;background:#dcfce7;color:#16a34a;text-align:center}.calendar-stats .out{background:#fee2e2;color:#dc2626}.weekdays,.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.weekdays span{text-align:center;color:#9a8b6a;font-size:11px;font-weight:900}.calendar-grid button{min-height:74px;border:2px solid #eadfbe;border-radius:11px;background:#fff;display:flex;flex-direction:column;align-items:flex-start;gap:3px;padding:7px;cursor:pointer}.calendar-grid button.today{border-color:#ff6fa5}.calendar-grid button.empty{visibility:hidden}.calendar-grid span{color:#16a34a;font-size:10px;font-weight:900}.calendar-grid em{color:#dc2626;font-size:10px;font-style:normal;font-weight:900}.finance-modal-backdrop{position:fixed;inset:0;background:rgba(20,14,10,.55);z-index:1000;display:grid;place-items:center;padding:20px}.finance-modal{width:430px;max-width:100%;max-height:88vh;overflow:auto;background:#fffbf0;border:2px solid #3a2e1f;border-radius:18px;box-shadow:6px 6px 0 #3a2e1f;padding:22px;display:grid;gap:10px}.finance-modal header{display:flex;justify-content:space-between;align-items:center}.finance-modal h2{font-family:Lalezar,Vazirmatn,sans-serif;margin:0}.finance-modal header button{width:30px;height:30px;border:2px solid #3a2e1f;border-radius:9px;background:#fff}.finance-modal input,.finance-modal select{height:42px;border:2px solid #3a2e1f;border-radius:11px;background:#fff;padding:0 12px;font-weight:800}.finance-modal .submit{height:44px;border:2px solid #3a2e1f;border-radius:11px;background:#34d399;color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-weight:900}.seg{display:flex;gap:8px}.seg button{flex:1;height:38px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;font-weight:900}.seg button.active{background:#ffd93d}.day-totals{display:grid;grid-template-columns:1fr 1fr;gap:8px}.day-totals span{background:#dcfce7;border:2px solid #16a34a;border-radius:10px;padding:8px;text-align:center;font-size:12px;font-weight:900}.day-totals span+span{background:#fee2e2;border-color:#dc2626;color:#dc2626}.empty-line,.finance-loading{text-align:center;color:#9a8b6a;font-weight:900;padding:18px}
.finance-modal{width:560px;border-radius:26px;padding:30px 30px 34px;gap:14px;background:#fffbf0;box-shadow:10px 10px 0 #3a2e1f}.finance-modal header{margin-bottom:4px}.finance-modal h2{font-size:27px}.finance-modal header button{width:42px;height:42px;border-radius:14px;font-size:24px;font-weight:900}.finance-modal input,.finance-modal select,.finance-date-field{height:58px;border:3px solid #3a2e1f;border-radius:15px;background:#fff;padding:0 18px;font-size:20px;font-weight:900;color:#1f2937}.finance-modal input::placeholder{color:#9ca3af}.seg{gap:12px}.seg button{height:58px;border:3px solid #3a2e1f;border-radius:15px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;box-shadow:none}.seg button.active{background:#ffd93d}.finance-date-field{position:relative;display:flex;align-items:center;justify-content:space-between;direction:ltr;cursor:pointer}.finance-date-field span{font-family:Vazirmatn,sans-serif}.finance-date-field i{font-style:normal;font-size:21px}.finance-date-field input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;padding:0}.finance-modal .submit{height:62px;border:3px solid #3a2e1f;border-radius:15px;background:#34d399;font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;box-shadow:4px 4px 0 #3a2e1f}.finance-empty-table{display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden;border:2px solid #eadfbe;border-radius:14px;background:#fff;box-shadow:2px 2px 0 rgba(58,46,31,.18);margin-bottom:8px}.finance-empty-table span{padding:10px 12px;border-bottom:1px solid #eadfbe;color:#7a6a4f;font-size:11px;font-weight:900;background:#fffaf0}.finance-empty-table b{grid-column:1/-1;min-height:74px;display:grid;place-items:center;color:#9a8b6a;font-size:13px;background:rgba(255,255,255,.74)}.tx-empty{margin-top:0}.installment-empty,.debt-empty{margin-bottom:28px}.account-cards{margin-bottom:36px}.finance-block-head{margin-top:26px}.tx-grid>div{min-width:0}.tx-grid h3{margin:0 0 8px}
@media(max-width:900px){.finance-stats,.account-cards,.tx-grid{grid-template-columns:1fr 1fr}.finance-page{padding:24px 16px}.calendar-stats{grid-template-columns:1fr}.calendar-grid button{min-height:64px}}
@media(max-width:560px){.finance-shell{padding:18px 8px}.finance-stats,.account-cards,.tx-grid{grid-template-columns:1fr}.installment-list article{align-items:flex-start;flex-wrap:wrap}.weekdays,.calendar-grid{gap:4px}.calendar-grid button{min-height:58px;padding:5px}.finance-header{align-items:flex-start}}
</style>
