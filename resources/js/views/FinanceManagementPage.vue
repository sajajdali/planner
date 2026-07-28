<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type Account = { id: number; name: string; color: string; current_balance: number; expense_total: number; is_default?: boolean };
type FinanceCategory = { id: number; name: string; type: 'expense' | 'income' };
type Tx = { id: number; title: string; amount: number; type: 'expense' | 'income'; expense_date: string; note?: string | null; category?: { name: string } | null; account?: { name: string } | null };
type ObligationPayment = { id: number; amount: number; paid_date: string; note?: string | null; account?: Account | null };
type Obligation = { id: number; type: 'installment' | 'debt'; title: string; party_name?: string | null; total_amount: number; paid_amount: number; remaining_amount: number; installment_amount: number; installments_total?: number | null; paid_count: number; due_day?: number | null; due_date?: string | null; start_date?: string | null; status: string; color: string; progress: number; current_due: number; account?: Account | null; payments?: ObligationPayment[] };

const router = useRouter();
const loading = ref(true);
const calendarOpen = ref(false);
const dayModal = ref<{ date: string; day: number } | null>(null);
const modal = ref<'transaction' | 'installment' | 'debt' | null>(null);
const paymentModal = ref<Obligation | null>(null);
const jy = ref(1405);
const jm = ref(1);
const datePicker = ref<{ target: 'transaction' | 'obligation' | 'payment'; jy: number; jm: number } | null>(null);
const accounts = ref<Account[]>([]);
const categories = ref<FinanceCategory[]>([]);
const transactions = ref<Tx[]>([]);
const installments = ref<Obligation[]>([]);
const debts = ref<Obligation[]>([]);
const totals = ref({ income: 0, expense: 0, debt: 0, due_this_month: 0 });
const txForm = ref({ type: 'expense' as 'expense' | 'income', title: '', amount: '', account_id: '', category_id: '', date: '', note: '' });
const obligationForm = ref({ title: '', party_name: '', total_amount: '', installment_amount: '', installments_total: '', due_day: '', date: '', account_id: '', note: '' });
const paymentForm = ref({ amount: '', account_id: '', date: '', note: '' });

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
const datePickerCells = computed(() => {
    if (!datePicker.value) return [];
    const first = toGregorian(datePicker.value.jy, datePicker.value.jm, 1);
    const offset = (new Date(ymd(first.gy, first.gm, first.gd)).getDay() + 1) % 7;
    const cells: Array<{ empty?: boolean; day?: number; date?: string; today?: boolean; selected?: boolean }> = [];
    const selected = selectedPickerDate();
    const today = new Date();
    const tj = toJalaali(today.getFullYear(), today.getMonth() + 1, today.getDate());
    for (let i = 0; i < offset; i++) cells.push({ empty: true });
    for (let day = 1; day <= jalaaliMonthLength(datePicker.value.jy, datePicker.value.jm); day++) {
        const g = toGregorian(datePicker.value.jy, datePicker.value.jm, day);
        const date = ymd(g.gy, g.gm, g.gd);
        cells.push({ day, date, today: tj.jy === datePicker.value.jy && tj.jm === datePicker.value.jm && tj.jd === day, selected: selected === date });
    }
    return cells;
});

onMounted(() => {
    const now = new Date();
    const j = toJalaali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    jy.value = j.jy;
    jm.value = j.jm;
    txForm.value.date = ymd(now.getFullYear(), now.getMonth() + 1, now.getDate());
    obligationForm.value.date = txForm.value.date;
    paymentForm.value.date = txForm.value.date;
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
function selectedPickerDate() {
    if (!datePicker.value) return '';
    if (datePicker.value.target === 'transaction') return txForm.value.date;
    if (datePicker.value.target === 'obligation') return obligationForm.value.date;
    return paymentForm.value.date;
}
function openDatePicker(target: 'transaction' | 'obligation' | 'payment') {
    const date = target === 'transaction' ? txForm.value.date : target === 'obligation' ? obligationForm.value.date : paymentForm.value.date;
    const base = date ? new Date(date) : new Date();
    const j = toJalaali(base.getFullYear(), base.getMonth() + 1, base.getDate());
    datePicker.value = { target, jy: j.jy, jm: j.jm };
}
function shiftDatePicker(delta: number) {
    if (!datePicker.value) return;
    const total = datePicker.value.jy * 12 + (datePicker.value.jm - 1) + delta;
    datePicker.value.jy = Math.floor(total / 12);
    datePicker.value.jm = (total % 12) + 1;
}
function pickDate(date: string) {
    if (!datePicker.value) return;
    if (datePicker.value.target === 'transaction') txForm.value.date = date;
    if (datePicker.value.target === 'obligation') obligationForm.value.date = date;
    if (datePicker.value.target === 'payment') paymentForm.value.date = date;
    datePicker.value = null;
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
    datePicker.value = null;
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
function openPaymentModal(item: Obligation) {
    paymentModal.value = item;
    paymentForm.value = {
        amount: moneyInput(String(item.current_due || item.remaining_amount)),
        account_id: String(item.account?.id ?? accounts.value[0]?.id ?? ''),
        date: txForm.value.date,
        note: item.type === 'installment' ? `پرداخت قسط ${item.title}` : `پرداخت بدهی ${item.title}`,
    };
}
function closePaymentModal() {
    paymentModal.value = null;
    datePicker.value = null;
}
async function payObligation() {
    if (!paymentModal.value) return;
    const amount = moneyNumber(paymentForm.value.amount);
    if (!amount || !paymentForm.value.account_id) return;
    await api.post(`/finance-obligations/${paymentModal.value.id}/pay`, {
        paid_date: paymentForm.value.date,
        amount,
        financial_account_id: Number(paymentForm.value.account_id),
        note: paymentForm.value.note || null,
    });
    closePaymentModal();
    await load();
}
async function undoPayment(payment: ObligationPayment) {
    await api.delete(`/finance-obligation-payments/${payment.id}`);
    await load();
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
                    <button class="dashboard-link" @click="router.push('/app')">داشبورد</button>
                    <button class="green" @click="openModal('transaction')">+ ثبت تراکنش</button>
                    <AppMenu />
                </div>
            </header>

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
                    <article v-for="item in installments" :key="item.id" :class="{ paid: !item.remaining_amount }">
                        <i :style="{ background: item.color }">◷</i>
                        <div>
                            <strong>{{ item.title }} <em>{{ item.remaining_amount ? 'فعال' : 'پرداخت شده' }}</em></strong>
                            <span>سررسید: {{ fa(item.due_day || '-') }} ام هر ماه · {{ fa(item.paid_count) }} از {{ fa(item.installments_total || 1) }} قسط پرداخت شده</span>
                            <small v-if="item.payments?.length" class="payment-foot">آخرین پرداخت: {{ money(item.payments[0].amount) }} از {{ item.payments[0].account?.name || 'حساب نامشخص' }} · {{ dateLabel(item.payments[0].paid_date) }}</small>
                            <mark><b :style="{ width: `${item.progress}%`, background: item.color }"></b></mark>
                        </div>
                        <aside><b>{{ money(item.remaining_amount || item.total_amount) }}</b><button v-if="item.remaining_amount" @click="openPaymentModal(item)">ثبت پرداخت</button><button v-else-if="item.payments?.length" class="ghost icon-only" title="حذف پرداخت و برگشت پول" aria-label="حذف پرداخت و برگشت پول" @click="undoPayment(item.payments[0])"><svg viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 15H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg></button></aside>
                    </article>
                    <div v-if="!installments.length" class="finance-empty-table installment-empty">
                        <span>عنوان قسط</span><span>سررسید</span><span>مبلغ</span><span>وضعیت</span>
                        <b>قسطی برای این ماه ثبت نشده.</b>
                    </div>
                </section>

                <section class="finance-block-head">
                    <div class="section-title"><i style="background:#DC2626"></i><b>بدهی‌ها به تفکیک گروه</b></div>
                    <button class="pink" @click="openModal('debt')">+ ثبت بدهی جدید</button>
                </section>
                <section class="debt-list">
                    <article v-for="item in debts" :key="item.id" :class="{ paid: !item.remaining_amount }">
                        <header><span>{{ item.title }} <em v-if="!item.remaining_amount">پرداخت شده</em></span><b>{{ money(item.remaining_amount) }}</b></header>
                        <div><span>طرف حساب: {{ item.party_name || 'ثبت نشده' }} · موعد: {{ dateLabel(item.due_date) }}<small v-if="item.payments?.length" class="payment-foot">آخرین پرداخت: {{ money(item.payments[0].amount) }} از {{ item.payments[0].account?.name || 'حساب نامشخص' }}</small></span><button v-if="item.remaining_amount" @click="openPaymentModal(item)">پرداخت</button><button v-else-if="item.payments?.length" class="ghost icon-only" title="حذف پرداخت و برگشت پول" aria-label="حذف پرداخت و برگشت پول" @click="undoPayment(item.payments[0])"><svg viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 15H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg></button></div>
                        <mark><b :style="{ width: `${item.progress}%` }"></b></mark>
                    </article>
                    <div v-if="!debts.length" class="finance-empty-card debt-empty" dir="rtl">
                        <i aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 8v5"></path><path d="M12 17h.01"></path><path d="M10.3 4.2 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"></path></svg>
                        </i>
                        <div>
                            <b>بدهی فعالی ثبت نشده</b>
                            <span>وقتی بدهی جدید ثبت شود، عنوان، طرف حساب، مانده و وضعیت پرداخت همین‌جا نمایش داده می‌شود.</span>
                        </div>
                        <button type="button" @click="openModal('debt')">ثبت بدهی</button>
                    </div>
                </section>

                <section class="finance-block-head">
                    <div class="section-title"><i style="background:#16A34A"></i><b>آخرین تراکنش‌ها</b></div>
                    <button @click="calendarOpen = !calendarOpen">مشاهده تقویم روزانه</button>
                </section>
                <p class="tx-summary">{{ fa(transactions.length) }} تراکنش · واریز {{ money(totals.income) }} · برداشت {{ money(totals.expense) }}</p>
                <section class="tx-grid">
                    <div>
                        <h3><i></i><span>آخرین واریزها</span></h3>
                        <article v-for="item in deposits" :key="item.id"><i></i><span>{{ item.title }}<small>{{ item.account?.name }} · {{ dateLabel(item.expense_date) }}</small></span><b>+ {{ money(item.amount) }}</b></article>
                        <div v-if="!deposits.length" class="finance-empty-card tx-empty"><i>+</i><div><b>واریزی ثبت نشده.</b><span>واریزهای جدید اینجا دیده می‌شوند.</span></div></div>
                    </div>
                    <div>
                        <h3 class="red"><i></i><span>آخرین برداشت‌ها</span></h3>
                        <article v-for="item in withdrawals" :key="item.id" class="out"><i></i><span>{{ item.title }}<small>{{ item.account?.name }} · {{ dateLabel(item.expense_date) }}</small></span><b>- {{ money(item.amount) }}</b></article>
                        <div v-if="!withdrawals.length" class="finance-empty-card tx-empty out"><i>-</i><div><b>برداشتی ثبت نشده.</b><span>برداشت‌های جدید اینجا دیده می‌شوند.</span></div></div>
                    </div>
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
                <button type="button" class="finance-date-field" @click="openDatePicker('transaction')"><span>{{ dateLabel(txForm.date) }}</span><i>▣</i></button>
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
                <button type="button" class="finance-date-field" @click="openDatePicker('obligation')"><span>{{ modal === 'installment' ? 'شروع اقساط' : 'موعد بدهی' }}: {{ dateLabel(obligationForm.date) }}</span><i>▣</i></button>
                <select v-model="obligationForm.account_id"><option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option></select>
                <input v-model="obligationForm.note" placeholder="یادداشت اختیاری" />
                <button class="submit" @click="createObligation(modal)">{{ modal === 'installment' ? 'ثبت قسط' : 'ثبت بدهی' }}</button>
            </template>
        </section></div>

        <div v-if="paymentModal" class="finance-modal-backdrop"><section class="finance-modal payment-modal">
            <header><h2>{{ paymentModal.type === 'installment' ? 'ثبت پرداخت قسط' : 'ثبت پرداخت بدهی' }}</h2><button @click="closePaymentModal">×</button></header>
            <div class="payment-summary">
                <strong>{{ paymentModal.title }}</strong>
                <span>مانده: {{ money(paymentModal.remaining_amount) }}</span>
            </div>
            <input v-model="paymentForm.amount" inputmode="numeric" placeholder="مبلغ پرداخت" @input="paymentForm.amount = moneyInput(paymentForm.amount)" />
            <select v-model="paymentForm.account_id"><option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option></select>
            <button type="button" class="finance-date-field" @click="openDatePicker('payment')"><span>تاریخ پرداخت: {{ dateLabel(paymentForm.date) }}</span><i>▣</i></button>
            <input v-model="paymentForm.note" placeholder="توضیحات پرداخت" />
            <button class="submit" @click="payObligation">ثبت پرداخت</button>
        </section></div>

        <div v-if="datePicker" class="finance-modal-backdrop date-picker-backdrop"><section class="finance-modal finance-date-picker">
            <header><h2>{{ monthNames[datePicker.jm - 1] }} {{ fa(datePicker.jy) }}</h2><button @click="datePicker = null">×</button></header>
            <div class="picker-nav">
                <button class="cyan" @click="shiftDatePicker(-1)">‹</button>
                <button class="yellow" @click="shiftDatePicker(1)">›</button>
            </div>
            <div class="weekdays"><span v-for="wd in weekNames" :key="wd">{{ wd }}</span></div>
            <div class="calendar-grid picker-grid">
                <button v-for="(cell, index) in datePickerCells" :key="index" :class="{ empty: cell.empty, today: cell.today, selected: cell.selected }" @click="cell.date && pickDate(cell.date)">
                    <template v-if="!cell.empty"><strong>{{ fa(cell.day!) }}</strong></template>
                </button>
            </div>
        </section></div>
    </div>
</template>

<style scoped>
.finance-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 10%,#2e2140 0%,#1a1424 65%);padding:36px 20px 60px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}.finance-page{width:1060px;max-width:100%;margin:auto;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:10px;box-shadow:0 30px 60px rgba(0,0,0,.5);position:relative;padding:34px}.tape{position:absolute;top:-15px;width:100px;height:30px;opacity:.85}.tape.yellow{right:70px;background:#ffd93d;transform:rotate(-6deg)}.tape.cyan{left:90px;background:#22d3d0;transform:rotate(5deg)}.finance-header,.finance-actions,.month-nav,.finance-block-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.finance-header h1{font-family:Lalezar,Vazirmatn,sans-serif;margin:0;font-size:30px}.finance-header p,.tx-summary{margin:3px 0 0;color:#7a6a4f;font-size:12.5px;font-weight:800}.finance-actions button,.month-nav button,.finance-block-head button,.account-cards>button,.installment-list button,.debt-list button{height:38px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f;padding:0 13px;font-weight:900;cursor:pointer}.finance-actions .green,.installment-list button{background:#34d399;color:#fff}.finance-actions .menu{width:40px;display:grid;gap:4px;padding:8px}.finance-actions .menu span{height:3px;background:#3a2e1f;border-radius:4px}.finance-drawer{position:absolute;left:34px;top:84px;z-index:5;background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:4px 4px 0 #3a2e1f;padding:10px;display:grid;gap:8px;min-width:220px}.finance-drawer button{border:0;border-radius:10px;padding:10px;background:#dbeafe;font-weight:900;text-align:right}.finance-drawer .danger{background:#fee2e2;color:#991b1b}.month-nav{justify-content:flex-end;margin:20px 0}.month-nav strong{height:36px;min-width:140px;display:grid;place-items:center;border:2px solid #3a2e1f;border-radius:10px;background:#fff;box-shadow:2px 2px 0 #3a2e1f}.month-nav .cyan{background:#22d3d0}.month-nav .yellow{background:#ffd93d}.month-nav .pink,.finance-block-head .pink{background:#ff6fa5;color:#fff}.finance-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:28px}.finance-stats article{border:2px solid #3a2e1f;border-radius:14px;padding:14px;box-shadow:3px 3px 0 #3a2e1f;color:#fff}.finance-stats span{display:block;font-size:11px;opacity:.9}.finance-stats b{display:block;margin-top:6px;font-size:18px}.green{background:linear-gradient(135deg,#34d399,#059669)}.orange{background:linear-gradient(135deg,#ffb55e,#f97316)}.red{background:linear-gradient(135deg,#f87171,#b91c1c)}.purple{background:linear-gradient(135deg,#c084fc,#7c3aed)}.account-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:28px}.account-cards article{min-height:130px;background:var(--bg);border:2px solid #3a2e1f;border-radius:16px;padding:16px;box-shadow:4px 4px 0 #3a2e1f;color:#fff;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;position:relative}.account-cards i{width:32px;height:32px;border-radius:9px;background:rgba(255,255,255,.28);display:grid;place-items:center}.account-cards small{position:absolute;top:18px;left:16px}.account-cards span,.account-cards b{display:block}.account-cards button{border-style:dashed;height:auto;min-height:130px;color:#7a6a4f}.finance-block-head{margin:10px 0 12px}.section-title{display:flex;align-items:center;gap:8px}.section-title i{width:5px;height:20px;border-radius:3px}.section-title b{font-family:Lalezar,Vazirmatn,sans-serif;font-size:21px}.installment-list,.debt-list{display:grid;gap:10px;margin-bottom:28px}.installment-list article{background:#fff;border:2px solid #3a2e1f;border-radius:14px;padding:12px 16px;box-shadow:3px 3px 0 #3a2e1f;display:flex;gap:14px;align-items:center}.installment-list i{width:38px;height:38px;border-radius:10px;color:#fff;display:grid;place-items:center;flex-shrink:0}.installment-list div{flex:1}.installment-list strong{display:block}.installment-list em{font-style:normal;background:#34d399;color:#fff;border-radius:999px;padding:2px 8px;font-size:10px}.installment-list span{display:block;color:#9a8b6a;font-size:11px;margin-top:3px}.installment-list mark,.debt-list mark{display:block;height:6px;background:#f0ebd8;border-radius:4px;margin-top:7px;overflow:hidden}.installment-list mark b,.debt-list mark b{display:block;height:100%;border-radius:4px}.installment-list aside{text-align:left}.installment-list aside b{display:block;white-space:nowrap}.installment-list aside button{height:28px;margin-top:6px;font-size:11px}.debt-list article{background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:3px 3px 0 #3a2e1f;padding:12px}.debt-list header,.debt-list div{display:flex;justify-content:space-between;gap:10px;align-items:center}.debt-list header{font-weight:900}.debt-list header b{color:#dc2626}.debt-list div span{font-size:11px;color:#9a8b6a}.debt-list mark b{background:#dc2626}.tx-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.tx-grid h3{font-size:13px;color:#16a34a}.tx-grid h3.red{color:#dc2626}.tx-grid article,.modal-tx{display:flex;align-items:center;gap:9px;background:#fff;border:2px solid #3a2e1f;border-radius:11px;padding:9px 12px;box-shadow:2px 2px 0 #3a2e1f;margin-bottom:7px}.tx-grid i,.modal-tx i{width:8px;height:8px;border-radius:50%;background:#16a34a}.tx-grid .out i,.modal-tx.expense i{background:#dc2626}.tx-grid span,.modal-tx span{flex:1;font-size:12px;font-weight:800}.tx-grid small,.modal-tx small{display:block;color:#9a8b6a;font-size:10px}.tx-grid b{color:#16a34a}.tx-grid .out b,.modal-tx.expense b{color:#dc2626}.finance-calendar{margin-top:18px;background:#fff;border:2px solid #3a2e1f;border-radius:16px;box-shadow:4px 4px 0 #3a2e1f;padding:16px}.calendar-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}.calendar-stats b{padding:10px;border-radius:10px;background:#dcfce7;color:#16a34a;text-align:center}.calendar-stats .out{background:#fee2e2;color:#dc2626}.weekdays,.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.weekdays span{text-align:center;color:#9a8b6a;font-size:11px;font-weight:900}.calendar-grid button{min-height:74px;border:2px solid #eadfbe;border-radius:11px;background:#fff;display:flex;flex-direction:column;align-items:flex-start;gap:3px;padding:7px;cursor:pointer}.calendar-grid button.today{border-color:#ff6fa5}.calendar-grid button.empty{visibility:hidden}.calendar-grid span{color:#16a34a;font-size:10px;font-weight:900}.calendar-grid em{color:#dc2626;font-size:10px;font-style:normal;font-weight:900}.finance-modal-backdrop{position:fixed;inset:0;background:rgba(20,14,10,.55);z-index:1000;display:grid;place-items:center;padding:20px}.finance-modal{width:430px;max-width:100%;max-height:88vh;overflow:auto;background:#fffbf0;border:2px solid #3a2e1f;border-radius:18px;box-shadow:6px 6px 0 #3a2e1f;padding:22px;display:grid;gap:10px}.finance-modal header{display:flex;justify-content:space-between;align-items:center}.finance-modal h2{font-family:Lalezar,Vazirmatn,sans-serif;margin:0}.finance-modal header button{width:30px;height:30px;border:2px solid #3a2e1f;border-radius:9px;background:#fff}.finance-modal input,.finance-modal select{height:42px;border:2px solid #3a2e1f;border-radius:11px;background:#fff;padding:0 12px;font-weight:800}.finance-modal .submit{height:44px;border:2px solid #3a2e1f;border-radius:11px;background:#34d399;color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-weight:900}.seg{display:flex;gap:8px}.seg button{flex:1;height:38px;border:2px solid #3a2e1f;border-radius:10px;background:#fff;font-weight:900}.seg button.active{background:#ffd93d}.day-totals{display:grid;grid-template-columns:1fr 1fr;gap:8px}.day-totals span{background:#dcfce7;border:2px solid #16a34a;border-radius:10px;padding:8px;text-align:center;font-size:12px;font-weight:900}.day-totals span+span{background:#fee2e2;border-color:#dc2626;color:#dc2626}.empty-line,.finance-loading{text-align:center;color:#9a8b6a;font-weight:900;padding:18px}
.finance-modal{width:560px;border-radius:26px;padding:30px 30px 34px;gap:14px;background:#fffbf0;box-shadow:10px 10px 0 #3a2e1f}.finance-modal header{margin-bottom:4px}.finance-modal h2{font-size:27px}.finance-modal header button{width:42px;height:42px;border-radius:14px;font-size:24px;font-weight:900}.finance-modal input,.finance-modal select,.finance-date-field{height:58px;border:3px solid #3a2e1f;border-radius:15px;background:#fff;padding:0 18px;font-size:20px;font-weight:900;color:#1f2937}.finance-modal input::placeholder{color:#9ca3af}.seg{gap:12px}.seg button{height:58px;border:3px solid #3a2e1f;border-radius:15px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;box-shadow:none}.seg button.active{background:#ffd93d}.finance-date-field{position:relative;display:flex;align-items:center;justify-content:space-between;direction:ltr;cursor:pointer}.finance-date-field span{font-family:Vazirmatn,sans-serif}.finance-date-field i{font-style:normal;font-size:21px}.finance-date-field input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;padding:0}.finance-modal .submit{height:62px;border:3px solid #3a2e1f;border-radius:15px;background:#34d399;font-family:Lalezar,Vazirmatn,sans-serif;font-size:27px;box-shadow:4px 4px 0 #3a2e1f}.finance-empty-table{display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden;border:2px solid #eadfbe;border-radius:14px;background:#fff;box-shadow:2px 2px 0 rgba(58,46,31,.18);margin-bottom:8px}.finance-empty-table span{padding:10px 12px;border-bottom:1px solid #eadfbe;color:#7a6a4f;font-size:11px;font-weight:900;background:#fffaf0}.finance-empty-table b{grid-column:1/-1;min-height:74px;display:grid;place-items:center;color:#9a8b6a;font-size:13px;background:rgba(255,255,255,.74)}.tx-empty{margin-top:0}.installment-empty,.debt-empty{margin-bottom:28px}.account-cards{margin-bottom:36px}.finance-block-head{margin-top:26px}.tx-grid>div{min-width:0}
.finance-actions .dashboard-link{background:#ffd93d;color:#3a2e1f}
.tx-grid>div{border:2px solid #eadfbe;border-radius:16px;background:rgba(255,255,255,.46);padding:12px;box-shadow:2px 2px 0 rgba(58,46,31,.12)}.tx-grid h3{height:38px;display:flex;align-items:center;gap:8px;margin:0 0 10px;padding:0 12px;border:2px solid #16a34a;border-radius:11px;background:#ecfdf5;color:#15803d;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;font-weight:400}.tx-grid h3 i{width:11px;height:11px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.14);flex-shrink:0}.tx-grid h3 span{font-size:inherit;font-weight:400}.tx-grid h3.red{border-color:#dc2626;background:#fef2f2;color:#b91c1c}.tx-grid h3.red i{background:#dc2626;box-shadow:0 0 0 4px rgba(220,38,38,.14)}.tx-grid article>i{width:8px;height:8px;flex-shrink:0}.tx-grid article>span{flex:1}.tx-grid article>b{white-space:nowrap}.finance-empty-card{display:flex;align-items:center;gap:12px;min-height:86px;border:2px dashed #d8caa5;border-radius:14px;background:rgba(255,250,240,.82);box-shadow:2px 2px 0 rgba(58,46,31,.12);padding:14px 16px;color:#7a6a4f}.finance-empty-card>i{width:38px;height:38px;display:grid;place-items:center;flex-shrink:0;border:2px solid #3a2e1f;border-radius:12px;background:#fee2e2;color:#dc2626;font-style:normal;font-weight:900;box-shadow:2px 2px 0 #3a2e1f}.finance-empty-card b{display:block;color:#3a2e1f;font-size:14px}.finance-empty-card span{display:block;margin-top:4px;font-size:11px;font-weight:800;line-height:1.8}.finance-empty-card.tx-empty{margin-top:0;min-height:74px;margin-bottom:0}.finance-empty-card.tx-empty>i{background:#dcfce7;color:#16a34a}.finance-empty-card.tx-empty.out>i{background:#fee2e2;color:#dc2626}
.installment-list article.paid,.debt-list article.paid{background:rgba(255,255,255,.38);border-color:#d8caa5;box-shadow:1px 1px 0 rgba(58,46,31,.12);filter:grayscale(.22) saturate(.45);opacity:.58}.installment-list article.paid:hover,.debt-list article.paid:hover{opacity:.72}.installment-list article.paid>i{opacity:.32}.installment-list article.paid strong,.debt-list article.paid header span{color:#7a6a4f}.debt-list header em{font-style:normal;background:#e5e7eb;color:#6b7280;border-radius:999px;padding:2px 8px;font-size:10px}.payment-foot{display:block;margin-top:5px;color:#7a6a4f;font-size:10.5px;font-weight:900}.installment-list aside .ghost,.debt-list .ghost{background:#fff7ed;color:#9a3412;border-color:#9a3412;box-shadow:2px 2px 0 #9a3412}.installment-list aside .icon-only,.debt-list .icon-only{width:38px;height:36px;display:grid;place-items:center;padding:0;border-radius:11px;line-height:1}.installment-list aside .icon-only svg,.debt-list .icon-only svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}.installment-list article.paid .icon-only,.debt-list article.paid .icon-only{opacity:1;filter:none}.finance-date-field{direction:rtl;text-align:right;width:100%;border:3px solid #3a2e1f;box-shadow:none}.finance-date-field span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.payment-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:2px dashed #eadfbe;border-radius:14px;background:#fff}.payment-summary strong{font-family:Lalezar,Vazirmatn,sans-serif;font-size:21px}.payment-summary span{color:#dc2626;font-weight:900}.date-picker-backdrop{z-index:1100}.finance-date-picker{width:420px}.picker-nav{margin:0;justify-content:center}.picker-nav button{width:54px}.picker-grid button{align-items:center;justify-content:center;min-height:48px;font-weight:900}.picker-grid button.selected{border-color:#16a34a;background:#dcfce7;color:#15803d;box-shadow:inset 0 0 0 2px rgba(22,163,74,.22)}.picker-grid button.today:not(.selected){border-color:#ff6fa5;background:#fff7ed}
@media(max-width:900px){.finance-stats,.account-cards,.tx-grid{grid-template-columns:1fr 1fr}.finance-page{padding:24px 16px}.calendar-stats{grid-template-columns:1fr}.calendar-grid button{min-height:64px}}
@media(max-width:560px){.finance-shell{padding:18px 8px}.finance-stats,.account-cards,.tx-grid{grid-template-columns:1fr}.installment-list article{align-items:flex-start;flex-wrap:wrap}.weekdays,.calendar-grid{gap:4px}.calendar-grid button{min-height:58px;padding:5px}.finance-header{align-items:flex-start}.finance-actions{display:grid;grid-template-columns:48px minmax(92px,.72fr) minmax(150px,1fr);width:100%;gap:8px;align-items:center;direction:ltr}.finance-actions :deep(.app-menu-wrap){grid-column:1;grid-row:1;justify-self:start;direction:rtl}.finance-actions .green{grid-column:3;grid-row:1;height:46px;padding:0 9px;white-space:nowrap;font-size:17px;line-height:1;direction:rtl}.finance-actions .dashboard-link{grid-column:2;grid-row:1;height:46px;padding:0 8px;white-space:nowrap;font-size:15px;direction:rtl}.month-nav{display:grid;grid-template-columns:54px minmax(132px,1fr) 54px;gap:8px;justify-content:stretch}.month-nav button,.month-nav strong{width:100%;min-width:0;white-space:nowrap}.month-nav .pink{grid-column:1 / -1;justify-self:end;width:auto;min-width:118px;padding:0 14px}.finance-modal-backdrop{align-items:end;place-items:end center;padding:10px 8px 0;background:rgba(20,14,10,.62)}.finance-modal{width:100%;max-width:100%;max-height:min(92dvh,720px);overflow:auto;border-width:3px;border-radius:24px 24px 0 0;padding:16px 14px calc(14px + env(safe-area-inset-bottom));box-shadow:0 -4px 0 #3a2e1f,0 -18px 44px rgba(20,14,10,.34);gap:11px;overscroll-behavior:contain}.finance-modal header{position:sticky;top:-16px;z-index:4;margin:-16px -14px 2px;padding:14px 14px 10px;background:#fffbf0;border-bottom:2px dashed #eadfbe;border-radius:21px 21px 0 0}.finance-modal h2{font-size:23px;line-height:1.35}.finance-modal header button{width:38px;height:38px;border-radius:13px;font-size:22px}.finance-modal input,.finance-modal select,.finance-date-field{height:50px;min-height:50px;border-width:2px;border-radius:13px;padding:0 13px;font-size:16px}.finance-modal input::placeholder{font-size:15px}.finance-date-field span{font-size:14px}.seg{gap:8px}.seg button{height:48px;border-width:2px;border-radius:13px;font-size:21px}.finance-modal .submit{position:sticky;bottom:0;z-index:5;height:56px;min-height:56px;margin-top:4px;border-width:3px;border-radius:15px;background:#34d399;box-shadow:0 -10px 18px rgba(255,251,240,.96),4px 4px 0 #3a2e1f;font-size:23px}.payment-summary{display:grid;gap:4px;padding:10px 12px}.payment-summary strong{font-size:19px}.payment-summary span{font-size:13px}.finance-date-picker{max-height:82dvh;border-radius:22px 22px 0 0}.finance-date-picker .picker-grid button{min-height:42px;padding:4px}.date-picker-backdrop{z-index:1200}}
@media(max-width:560px){.finance-actions{grid-template-columns:48px minmax(0,1fr) 92px 132px;gap:7px;overflow:visible}.finance-actions :deep(.app-menu-wrap){grid-column:1}.finance-actions :deep(.shared-menu-button){width:44px;height:44px}.finance-actions .dashboard-link{grid-column:3;height:44px;min-width:0;max-width:100%;padding:0 7px;font-size:14px;overflow:hidden;text-overflow:ellipsis}.finance-actions .green{grid-column:4;height:44px;min-width:0;max-width:100%;padding:0 7px;font-size:15px;overflow:hidden;text-overflow:ellipsis}.month-nav{grid-template-columns:44px minmax(112px,1fr) 44px 96px;gap:6px;direction:ltr;align-items:center}.month-nav button,.month-nav strong{height:40px;min-width:0;width:100%;padding:0 6px;font-size:16px;direction:rtl}.month-nav strong{font-size:18px}.month-nav .pink{grid-column:4;grid-row:1;justify-self:stretch;min-width:0;width:100%;padding:0 8px;font-size:16px}}
@media(max-width:560px){.finance-actions{display:flex!important;align-items:center!important;width:100%!important;max-width:100%!important;min-height:46px!important;padding:0!important;gap:7px!important;box-sizing:border-box!important;direction:ltr!important;overflow:visible!important}.finance-actions :deep(.app-menu-wrap){order:1!important;flex:0 0 46px!important;width:46px!important;margin:0 auto 0 -8px!important;position:relative!important;left:auto!important;right:auto!important;top:auto!important;direction:rtl!important}.finance-actions :deep(.shared-menu-button){width:44px!important;height:44px!important}.finance-actions .dashboard-link{order:2!important;flex:0 0 90px!important;width:90px!important;min-width:0!important;height:44px!important;padding:0 6px!important;font-size:14px!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;direction:rtl!important}.finance-actions .green{order:3!important;flex:0 0 126px!important;width:126px!important;min-width:0!important;height:44px!important;padding:0 6px!important;font-size:15px!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;direction:rtl!important}}
.finance-empty-card.debt-empty{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:14px;min-height:132px;margin-bottom:28px;padding:20px 22px;border:2px dashed #dccca4;border-radius:20px;background:linear-gradient(135deg,rgba(255,255,255,.88),rgba(255,247,237,.78));box-shadow:4px 4px 0 rgba(58,46,31,.13);text-align:right;color:#7a6a4f}.finance-empty-card.debt-empty>i{width:58px;height:58px;border-width:3px;border-radius:18px;background:#fee2e2;color:#dc2626;box-shadow:3px 3px 0 #3a2e1f}.finance-empty-card.debt-empty>i svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}.finance-empty-card.debt-empty>div{min-width:0;display:grid;gap:4px}.finance-empty-card.debt-empty b{font-family:Lalezar,Vazirmatn,sans-serif;font-size:25px;font-weight:400;line-height:1.35;color:#3a2e1f}.finance-empty-card.debt-empty span{max-width:690px;margin:0;font-size:13px;font-weight:900;line-height:2;color:#8a7a5b}.finance-empty-card.debt-empty button{height:42px;min-width:104px;border:2px solid #3a2e1f;border-radius:13px;background:#ff6fa5;color:#fff;box-shadow:3px 3px 0 #3a2e1f;padding:0 16px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;font-weight:400;white-space:nowrap}.finance-empty-card.debt-empty button:hover{transform:translateY(-1px)}
@media(max-width:700px){.finance-empty-card.debt-empty{grid-template-columns:auto minmax(0,1fr);gap:11px;padding:16px 14px;min-height:0;border-radius:17px}.finance-empty-card.debt-empty>i{width:48px;height:48px;border-radius:15px}.finance-empty-card.debt-empty>i svg{width:25px;height:25px}.finance-empty-card.debt-empty b{font-size:22px}.finance-empty-card.debt-empty span{font-size:12px;line-height:1.9}.finance-empty-card.debt-empty button{grid-column:1/-1;width:100%;height:40px;font-size:19px}}

@media(max-width:560px){
    .finance-shell{padding:10px 6px 34px;background:#241b2f}
    .finance-page{padding:20px 12px 24px;border-radius:8px;background-size:16px 16px;box-shadow:0 18px 36px rgba(0,0,0,.42)}
    .tape{height:22px;width:78px;top:-10px}.tape.yellow{right:34px}.tape.cyan{left:42px}
    .finance-header{display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:8px;text-align:center}
    .finance-header h1{font-size:24px;line-height:1.25}.finance-header p{font-size:10.5px;line-height:1.7}
    .finance-actions{display:grid!important;grid-template-columns:42px minmax(0,1fr) minmax(0,1.08fr)!important;gap:7px!important;min-height:40px!important;direction:ltr!important}
    .finance-actions :deep(.app-menu-wrap){grid-column:1!important;order:unset!important;flex:none!important;width:42px!important;margin:0!important;justify-self:start!important}
    .finance-actions :deep(.shared-menu-button){width:40px!important;height:40px!important;border-width:2px!important;border-radius:12px!important}
    .finance-actions .dashboard-link,.finance-actions .green{height:40px!important;width:100%!important;flex:none!important;padding:0 6px!important;border-width:2px!important;border-radius:10px!important;font-size:12.5px!important;line-height:1!important}
    .finance-actions .dashboard-link{grid-column:2!important}.finance-actions .green{grid-column:3!important}
    .month-nav{display:grid!important;grid-template-columns:38px minmax(0,1fr) 38px 74px!important;gap:6px!important;margin:14px 0!important;direction:ltr!important}
    .month-nav button,.month-nav strong{height:34px!important;min-width:0!important;width:100%!important;padding:0 4px!important;border-width:2px!important;border-radius:9px!important;font-size:13px!important;box-shadow:1.5px 1.5px 0 #3a2e1f!important;direction:rtl!important}
    .month-nav strong{font-size:14px!important}.month-nav .pink{grid-column:4!important;grid-row:1!important;min-width:0!important;font-size:12px!important}
    .finance-stats{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important;margin-bottom:20px!important}
    .finance-stats article{min-height:76px;padding:10px!important;border-width:2px;border-radius:12px;box-shadow:2px 2px 0 #3a2e1f}
    .finance-stats span{font-size:9.5px}.finance-stats b{font-size:13px;line-height:1.65;margin-top:5px;overflow-wrap:anywhere}
    .section-title{gap:6px;margin-bottom:8px}.section-title i{width:4px;height:17px}.section-title b{font-size:18px;line-height:1.35}
    .account-cards{grid-template-columns:1fr!important;gap:9px!important;margin-bottom:22px!important}
    .account-cards article{min-height:88px;padding:12px 13px;border-radius:13px;box-shadow:2px 2px 0 #3a2e1f}
    .account-cards i{width:26px;height:26px;border-radius:8px}.account-cards small{top:11px;left:12px;font-size:9px}
    .account-cards span{font-size:12px}.account-cards b{font-size:13px;line-height:1.7}.account-cards>button{min-height:64px;border-radius:12px;font-size:12px}
    .finance-block-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:8px;margin:18px 0 9px!important}
    .finance-block-head button{height:34px;padding:0 10px;font-size:11px;border-radius:9px;white-space:nowrap}
    .installment-list,.debt-list{gap:9px;margin-bottom:20px}
    .installment-list article{display:grid;grid-template-columns:30px minmax(0,1fr);gap:8px;padding:10px!important;border-radius:12px;box-shadow:2px 2px 0 #3a2e1f}
    .installment-list i{width:30px;height:30px;border-radius:9px}.installment-list div{min-width:0}
    .installment-list strong{font-size:12px;line-height:1.6}.installment-list em{font-size:8.5px;padding:1px 6px}
    .installment-list span,.payment-foot{font-size:9.5px!important;line-height:1.8}.installment-list aside{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;text-align:right;gap:8px}
    .installment-list aside b{font-size:12px}.installment-list aside button{height:30px;margin:0;padding:0 10px;font-size:10.5px}
    .debt-list article{padding:10px;border-radius:12px;box-shadow:2px 2px 0 #3a2e1f}
    .debt-list header,.debt-list div{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center}
    .debt-list header span{font-size:12px;min-width:0}.debt-list header b{font-size:12px;white-space:nowrap}
    .debt-list div span{font-size:9.5px;line-height:1.8}.debt-list button{height:30px;padding:0 10px;font-size:10.5px}
    .tx-summary{font-size:10px;line-height:1.8;margin-bottom:8px}
    .tx-grid{grid-template-columns:1fr!important;gap:10px!important}
    .tx-grid>div{padding:9px;border-radius:13px}.tx-grid h3{height:32px;margin-bottom:8px;padding:0 10px;font-size:17px;border-radius:10px}
    .tx-grid article,.modal-tx{gap:7px;padding:8px 9px;border-radius:10px;box-shadow:1.5px 1.5px 0 #3a2e1f}
    .tx-grid article>span,.modal-tx span{font-size:10.5px;min-width:0}.tx-grid small,.modal-tx small{font-size:8.8px;line-height:1.6}.tx-grid article>b,.modal-tx b{font-size:10.5px;white-space:nowrap}
    .finance-empty-card{min-height:58px;padding:10px;gap:9px;border-radius:12px}.finance-empty-card>i{width:30px;height:30px;border-radius:10px}.finance-empty-card b{font-size:12px}.finance-empty-card span{font-size:9.5px;line-height:1.7}
    .finance-calendar{margin-top:12px;padding:10px;border-radius:13px;box-shadow:2px 2px 0 #3a2e1f;overflow:hidden}
    .calendar-stats{grid-template-columns:1fr!important;gap:6px;margin-bottom:10px}.calendar-stats b{padding:7px;font-size:11px;border-radius:9px}
    .weekdays,.calendar-grid{grid-template-columns:repeat(7,minmax(0,1fr))!important;gap:3px!important}
    .weekdays span{font-size:9px}.calendar-grid button{min-height:38px!important;height:38px;padding:3px 2px!important;border-width:1.5px;border-radius:8px;align-items:center!important;justify-content:center!important;gap:1px!important;overflow:hidden}
    .calendar-grid strong{font-size:11px;line-height:1}.calendar-grid span,.calendar-grid em{font-size:7.5px;line-height:1.15;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .finance-empty-table{grid-template-columns:repeat(2,1fr);border-radius:12px}.finance-empty-table span{padding:8px;font-size:9px}.finance-empty-table b{min-height:54px;font-size:11px}
    .finance-modal-backdrop{z-index:9000;align-items:end;place-items:end center;padding:8px 6px 0}
    .finance-modal{width:100%!important;max-width:100%!important;max-height:min(88dvh,680px)!important;padding:14px 12px calc(12px + env(safe-area-inset-bottom))!important;border-radius:20px 20px 0 0!important;border-width:2px!important;gap:9px!important;box-shadow:0 -3px 0 #3a2e1f,0 -16px 36px rgba(20,14,10,.32)!important}
    .finance-modal header{top:-14px!important;margin:-14px -12px 0!important;padding:12px 12px 8px!important;border-radius:18px 18px 0 0!important}
    .finance-modal h2{font-size:20px!important;line-height:1.35}.finance-modal header button{width:34px!important;height:34px!important;border-radius:11px!important;font-size:19px!important}
    .finance-modal input,.finance-modal select,.finance-date-field{height:44px!important;min-height:44px!important;border-width:2px!important;border-radius:12px!important;padding:0 11px!important;font-size:14px!important}
    .finance-date-field span{font-size:12.5px!important}.finance-date-field i{font-size:17px!important}
    .seg{gap:7px}.seg button{height:42px!important;border-width:2px!important;border-radius:12px!important;font-size:18px!important}
    .finance-modal .submit{height:48px!important;min-height:48px!important;border-width:2px!important;border-radius:13px!important;font-size:19px!important;box-shadow:2px 2px 0 #3a2e1f!important}
    .day-totals{gap:6px}.day-totals span{padding:7px;font-size:10.5px}
    .payment-summary{gap:3px;padding:9px 10px}.payment-summary strong{font-size:17px}.payment-summary span{font-size:11.5px}
    .finance-date-picker{max-height:min(78dvh,560px)!important}.finance-date-picker .picker-nav{display:flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;margin:0 0 8px!important;direction:rtl!important}.finance-date-picker .picker-nav button{width:42px!important;height:36px!important;min-width:42px!important;padding:0!important;border-width:2px!important;border-radius:10px!important;font-size:18px!important;box-shadow:1.5px 1.5px 0 #3a2e1f!important;display:grid!important;place-items:center!important}
    .finance-date-picker .picker-grid button{height:36px!important;min-height:36px!important}.finance-date-picker .picker-grid strong{font-size:12px}
    .finance-empty-card.debt-empty{grid-template-columns:34px minmax(0,1fr);gap:9px;padding:12px 10px;border-radius:14px}.finance-empty-card.debt-empty>i{width:34px;height:34px;border-width:2px;border-radius:11px}.finance-empty-card.debt-empty>i svg{width:19px;height:19px}.finance-empty-card.debt-empty b{font-size:18px}.finance-empty-card.debt-empty span{font-size:10px;line-height:1.75}.finance-empty-card.debt-empty button{grid-column:1/-1;height:36px;font-size:16px}
}
</style>
