<script setup lang="ts">
import { onMounted, ref } from 'vue';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type SupportTicket = {
    id: number;
    subject: string;
    body: string;
    status: 'open' | 'answered';
    admin_reply?: string | null;
    created_at: string;
    replied_at?: string | null;
    user?: { name: string; email?: string | null; phone?: string | null } | null;
};

const loading = ref(true);
const tickets = ref<SupportTicket[]>([]);
const replies = ref<Record<number, string>>({});
const savingId = ref<number | null>(null);

onMounted(loadTickets);

async function loadTickets() {
    loading.value = true;
    const { data } = await api.get('/admin/support-tickets');
    tickets.value = data;
    replies.value = Object.fromEntries(tickets.value.map((ticket) => [ticket.id, ticket.admin_reply ?? '']));
    loading.value = false;
}

function ticketDate(value?: string | null) {
    if (!value) return '';
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

async function reply(ticket: SupportTicket) {
    const adminReply = replies.value[ticket.id]?.trim();
    if (!adminReply) return;

    savingId.value = ticket.id;
    const { data } = await api.put(`/admin/support-tickets/${ticket.id}/reply`, { admin_reply: adminReply });
    tickets.value = tickets.value.map((item) => item.id === ticket.id ? data : item);
    replies.value[data.id] = data.admin_reply ?? '';
    savingId.value = null;
}
</script>

<template>
    <main class="support-admin-shell" dir="rtl">
        <section class="support-admin-page">
            <i class="tape tape-yellow"></i>
            <i class="tape tape-cyan"></i>

            <header>
                <div>
                    <span>مدیریت پشتیبانی</span>
                    <h1>تیکت‌های کاربران</h1>
                    <p>درخواست‌ها را بخوان و پاسخ مدیریت را ثبت کن.</p>
                </div>
                <AppMenu />
            </header>

            <div v-if="loading" class="support-admin-loading">در حال بارگذاری تیکت‌ها...</div>
            <section v-else class="support-admin-list">
                <article v-for="ticket in tickets" :key="ticket.id" :class="ticket.status">
                    <div class="ticket-main">
                        <header>
                            <div>
                                <strong>{{ ticket.subject }}</strong>
                                <small>{{ ticket.user?.name || 'کاربر' }} · {{ ticket.user?.phone || ticket.user?.email || 'بدون تماس' }}</small>
                            </div>
                            <span>{{ ticket.status === 'answered' ? 'پاسخ داده شده' : 'باز' }}</span>
                        </header>
                        <p>{{ ticket.body }}</p>
                        <small>{{ ticketDate(ticket.created_at) }}</small>
                    </div>

                    <div class="ticket-reply-box">
                        <textarea v-model="replies[ticket.id]" placeholder="پاسخ مدیریت را بنویس..."></textarea>
                        <button :disabled="savingId === ticket.id || !replies[ticket.id]?.trim()" @click="reply(ticket)">
                            {{ savingId === ticket.id ? 'در حال ثبت...' : 'ثبت پاسخ' }}
                        </button>
                    </div>
                </article>
                <div v-if="!tickets.length" class="support-admin-empty">هنوز تیکتی ثبت نشده.</div>
            </section>
        </section>
    </main>
</template>

<style scoped>
.support-admin-shell{min-height:100vh;background:#241b2f;background-image:radial-gradient(circle at 20% 10%,#2e2140 0%,#1a1424 65%);padding:36px 20px 60px;color:#3a2e1f;font-family:Vazirmatn,sans-serif}
.support-admin-page{width:980px;max-width:100%;margin:auto;position:relative;padding:34px;border:3px solid #3a2e1f;border-radius:10px;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;box-shadow:8px 8px 0 #3a2e1f,0 30px 60px rgba(0,0,0,.45)}
.tape{position:absolute;width:74px;height:24px;opacity:.9;border:2px solid rgba(58,46,31,.28)}.tape-yellow{right:48px;top:-12px;background:#ffd93d;transform:rotate(-4deg)}.tape-cyan{left:64px;top:-10px;background:#22d3d0;transform:rotate(5deg)}
.support-admin-page>header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:24px}.support-admin-page>header span{color:#d63384;font-size:12px;font-weight:900}.support-admin-page h1{margin:3px 0 0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:34px}.support-admin-page p{margin:4px 0 0;color:#7a6a4f;font-weight:900;line-height:1.9}
.support-admin-list{display:grid;gap:14px}.support-admin-list article{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;padding:14px;border:2px solid #3a2e1f;border-radius:16px;background:#fff;box-shadow:4px 4px 0 #3a2e1f}.support-admin-list article.answered{opacity:.72;background:#f8fafc}.ticket-main header{display:flex;justify-content:space-between;gap:10px}.ticket-main strong{font-family:Lalezar,Vazirmatn,sans-serif;font-size:23px}.ticket-main small{display:block;color:#8a7a5b;font-size:11px;font-weight:900}.ticket-main header span{height:30px;display:inline-flex;align-items:center;padding:0 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:900}.answered .ticket-main header span{background:#dcfce7;color:#15803d}.ticket-main p{white-space:pre-wrap}
.ticket-reply-box{display:grid;gap:10px}.ticket-reply-box textarea{min-height:132px;border:2px solid #3a2e1f;border-radius:13px;background:#fffbf0;font-weight:800}.ticket-reply-box button{height:42px;border:2px solid #3a2e1f;border-radius:12px;background:#22d3d0;color:#fff;box-shadow:3px 3px 0 #3a2e1f;font-weight:900}.ticket-reply-box button:disabled{opacity:.5}.support-admin-loading,.support-admin-empty{min-height:180px;display:grid;place-items:center;color:#7a6a4f;font-weight:900}
@media(max-width:780px){.support-admin-list article{grid-template-columns:1fr}.support-admin-page{padding:24px 14px}.support-admin-page>header{flex-wrap:wrap}}
</style>
