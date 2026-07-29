<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type NoteType = 'text' | 'code';
type NoteGroup = { id: number; name: string; color: string; icon: 'text' | 'code' | 'terminal'; notes: NoteItem[] };
type NoteItem = { id: number; notebook_note_group_id: number; title: string; content: string; content_type: NoteType; language: string | null; is_important: boolean; updated_at?: string };
type NoteForm = { id: number | null; groupId: number | ''; title: string; content: string; content_type: NoteType; language: string; is_important: boolean };
type GroupForm = { id: number | null; name: string; color: string; icon: 'text' | 'code' | 'terminal' };
type CodeToken = { text: string; className?: string };

const loading = ref(true);
const search = ref('');
const toast = ref('');
const groups = ref<NoteGroup[]>([]);
const collapsed = ref<Record<number, boolean>>({});
const groupModal = ref(false);
const noteModal = ref(false);
const viewNote = ref<NoteItem | null>(null);
const fullScreenCode = ref(false);
const deleteConfirm = ref<{ title: string; message: string; run: () => Promise<void> } | null>(null);
const groupForm = ref<GroupForm>({ id: null, name: '', color: '#FF6FA5', icon: 'text' });
const noteForm = ref<NoteForm>({ id: null, groupId: '', title: '', content: '', content_type: 'text', language: 'javascript', is_important: false });

const colors = ['#FF6FA5', '#22D3D0', '#9B5DE5', '#FFD93D', '#FF8A3D', '#2563EB', '#16A34A', '#EF4444', '#0EA5E9', '#F97316', '#A855F7', '#14B8A6'];
const languages = [
    ['javascript', 'JavaScript'],
    ['python', 'Python'],
    ['bash', 'Bash / ترمینال'],
    ['sql', 'SQL'],
    ['html', 'HTML'],
    ['css', 'CSS'],
    ['json', 'JSON'],
    ['php', 'PHP'],
    ['other', 'سایر'],
];
const iconPaths = {
    text: 'M4 6h16M4 12h16M4 18h10',
    code: 'M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-2 12',
    terminal: 'M4 5h16v14H4z M8 9l4 3-4 3M13 15h4',
};

const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();
    return groups.value
        .map((group) => ({
            ...group,
            notes: group.notes.filter((note) => !q || note.title.toLowerCase().includes(q) || note.content.toLowerCase().includes(q)),
        }))
        .filter((group) => !q || group.notes.length);
});

function languageLabel(value?: string | null) {
    return languages.find(([key]) => key === value)?.[1] ?? 'کد';
}

function preview(content: string) {
    return content.length > 140 ? `${content.slice(0, 140)}...` : content;
}

function codePreview(content: string) {
    return content.split('\n').slice(0, 6).join('\n');
}

function highlightedCode(content: string): CodeToken[] {
    const matcher = new RegExp(
        '(\\/\\/[^\\n]*|#[^\\n]*|\\/\\*[\\s\\S]*?\\*\\/|<!--[\\s\\S]*?-->)'
        + '|("(?:[^"\\\\]|\\\\.)*"|\\\'(?:[^\\\'\\\\]|\\\\.)*\\\'|`(?:[^`\\\\]|\\\\.)*`)'
        + '|(\\b\\d+(?:\\.\\d+)?\\b)'
        + '|(<\\/?[\\w:-]+|\\/?>)'
        + '|(\\b(?:const|let|var|function|return|if|else|import|from|export|default|async|await|for|while|class|new|try|catch|throw|typeof|extends|this|null|true|false|void|switch|case|break|interface|type|public|private|protected|static|echo|SELECT|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|JOIN|LEFT|RIGHT|INNER|AND|OR|NOT|NULL|LIKE|ORDER|BY|GROUP|LIMIT|sudo|git|docker|npm|npx|cd|mkdir|rm|cp|mv|curl|chmod)\\b)'
        + '|([{}()[\\];,.])',
        'g',
    );
    const tokens: CodeToken[] = [];
    let last = 0;
    let match: RegExpExecArray | null;

    while ((match = matcher.exec(content)) !== null) {
        if (match.index > last) tokens.push({ text: content.slice(last, match.index) });
        const className = match[1] ? 'comment' : match[2] ? 'string' : match[3] ? 'number' : match[4] ? 'tag' : match[5] ? 'keyword' : 'punctuation';
        tokens.push({ text: match[0], className });
        last = match.index + match[0].length;
    }

    if (last < content.length) tokens.push({ text: content.slice(last) });
    return tokens;
}

function showToast(message: string) {
    toast.value = message;
    window.setTimeout(() => {
        if (toast.value === message) toast.value = '';
    }, 1800);
}

async function load() {
    loading.value = true;
    const { data } = await api.get('/notebook-notes');
    groups.value = data.groups ?? [];
    loading.value = false;
}

function openGroupModal(group?: NoteGroup) {
    groupForm.value = group
        ? { id: group.id, name: group.name, color: group.color, icon: group.icon }
        : { id: null, name: '', color: colors[0], icon: 'text' };
    groupModal.value = true;
}

async function saveGroup() {
    if (!groupForm.value.name.trim()) return;
    const payload = { name: groupForm.value.name.trim(), color: groupForm.value.color, icon: groupForm.value.icon };
    const { data } = groupForm.value.id
        ? await api.put(`/notebook-note-groups/${groupForm.value.id}`, payload)
        : await api.post('/notebook-note-groups', payload);
    const index = groups.value.findIndex((group) => group.id === data.id);
    if (index >= 0) groups.value[index] = data;
    else groups.value.push(data);
    groupModal.value = false;
    showToast(groupForm.value.id ? 'گروه ویرایش شد' : 'گروه ساخته شد');
}

function openNoteModal(groupId?: number, note?: NoteItem) {
    noteForm.value = note
        ? {
            id: note.id,
            groupId: note.notebook_note_group_id,
            title: note.title,
            content: note.content,
            content_type: note.content_type,
            language: note.language || 'javascript',
            is_important: note.is_important,
        }
        : { id: null, groupId: groupId ?? groups.value[0]?.id ?? '', title: '', content: '', content_type: 'text', language: 'javascript', is_important: false };
    noteModal.value = true;
}

async function saveNote() {
    if (!noteForm.value.title.trim() || !noteForm.value.groupId) return;
    const payload = {
        notebook_note_group_id: noteForm.value.groupId,
        title: noteForm.value.title.trim(),
        content: noteForm.value.content,
        content_type: noteForm.value.content_type,
        language: noteForm.value.content_type === 'code' ? noteForm.value.language : null,
        is_important: noteForm.value.is_important,
    };
    const { data } = noteForm.value.id
        ? await api.put(`/notebook-notes/${noteForm.value.id}`, payload)
        : await api.post('/notebook-notes', payload);

    groups.value = groups.value.map((group) => ({
        ...group,
        notes: group.notes.filter((note) => note.id !== data.id),
    }));
    const target = groups.value.find((group) => group.id === data.notebook_note_group_id);
    target?.notes.unshift(data);
    noteModal.value = false;
    showToast(noteForm.value.id ? 'یادداشت ویرایش شد' : 'یادداشت اضافه شد');
}

async function copyText(text: string) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    showToast('کپی شد');
}

function askDeleteNote(note: NoteItem) {
    deleteConfirm.value = {
        title: 'حذف یادداشت',
        message: `«${note.title}» حذف شود؟`,
        run: async () => {
            await api.delete(`/notebook-notes/${note.id}`);
            groups.value = groups.value.map((group) => ({ ...group, notes: group.notes.filter((item) => item.id !== note.id) }));
            deleteConfirm.value = null;
            showToast('یادداشت حذف شد');
        },
    };
}

function askDeleteGroup(group: NoteGroup) {
    deleteConfirm.value = {
        title: 'حذف گروه',
        message: `گروه «${group.name}» و همه یادداشت‌های آن حذف شود؟`,
        run: async () => {
            await api.delete(`/notebook-note-groups/${group.id}`);
            groups.value = groups.value.filter((item) => item.id !== group.id);
            deleteConfirm.value = null;
            showToast('گروه حذف شد');
        },
    };
}

onMounted(load);
</script>

<template>
    <main class="notes-page" dir="rtl">
        <section class="notes-sheet">
            <span class="tape tape-a"></span>
            <span class="tape tape-b"></span>
            <span class="tape tape-c"></span>

            <header class="notes-header">
                <div class="notes-heading">
                    <i class="note-logo">ن</i>
                    <div>
                        <h1>یادداشت‌های ثابت</h1>
                        <p>متن‌ها و کدهای آماده، مرتب و همیشه قابل کپی</p>
                    </div>
                </div>
                <div class="notes-top-tools">
                    <AppMenu />
                </div>
            </header>
            <div class="notes-actions-row">
                <button class="add-group-btn" type="button" @click="openGroupModal()"><span>＋</span> گروه جدید</button>
            </div>

            <label class="notes-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                <input v-model="search" placeholder="جستجو در عنوان یا محتوا..." />
                <button v-if="search" type="button" @click="search = ''">×</button>
            </label>

            <div v-if="loading" class="empty-note">در حال آماده‌سازی یادداشت‌ها...</div>

            <template v-else>
                <section v-for="(group, index) in filteredGroups" :key="group.id" class="note-group" :style="{ '--group-color': group.color, transform: index % 2 ? 'rotate(.22deg)' : 'rotate(-.28deg)' }">
                    <header class="note-group-head" @click="collapsed[group.id] = !collapsed[group.id]">
                        <i><svg viewBox="0 0 24 24"><path :d="iconPaths[group.icon]"></path></svg></i>
                        <div>
                            <strong>{{ group.name }}</strong>
                            <small>{{ group.notes.length }} مورد</small>
                        </div>
                        <button class="group-action add" type="button" aria-label="افزودن یادداشت" @click.stop="openNoteModal(group.id)">＋</button>
                        <button class="group-action edit" type="button" aria-label="ویرایش گروه" @click.stop="openGroupModal(group)">
                            <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        </button>
                        <button class="group-action delete" type="button" aria-label="حذف گروه" @click.stop="askDeleteGroup(group)">
                            <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z"></path></svg>
                        </button>
                        <svg class="chevron" :class="{ closed: collapsed[group.id] }" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                    </header>

                    <div v-if="!collapsed[group.id]" class="note-group-body">
                        <div v-if="group.notes.length" class="note-grid">
                            <article v-for="note in group.notes" :key="note.id" class="note-card" :class="{ important: note.is_important }">
                                <header>
                                    <strong>{{ note.title }}</strong>
                                    <div class="note-card-badges">
                                        <em v-if="note.is_important">مهم</em>
                                        <span>{{ note.content_type === 'code' ? languageLabel(note.language) : 'متن' }}</span>
                                    </div>
                                </header>
                                <pre v-if="note.content_type === 'code'" class="code-preview"><code><span v-for="(token, tokenIndex) in highlightedCode(codePreview(note.content))" :key="tokenIndex" :class="token.className">{{ token.text }}</span></code></pre>
                                <p v-else>{{ preview(note.content) || 'بدون محتوا' }}</p>
                                <footer>
                                    <button class="copy" type="button" @click="copyText(note.content)">
                                        <svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"></rect><path d="M5 15V5a2 2 0 012-2h10"></path></svg>
                                        کپی
                                    </button>
                                    <button type="button" aria-label="مشاهده" @click="viewNote = note"><svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                                    <button type="button" aria-label="ویرایش" @click="openNoteModal(group.id, note)"><svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></button>
                                    <button class="soft-delete" type="button" aria-label="حذف" @click="askDeleteNote(note)"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z M10 11v6M14 11v6"></path></svg></button>
                                </footer>
                            </article>
                        </div>
                        <div v-else class="empty-note">چیزی در این گروه ثبت نشده</div>
                    </div>
                </section>

                <div v-if="!filteredGroups.length" class="empty-note">چیزی پیدا نشد</div>
                <button v-if="!groups.length" class="first-note-btn" type="button" @click="openGroupModal()">＋ ساخت اولین گروه</button>
            </template>
        </section>

        <div v-if="toast" class="toast">{{ toast }}</div>

        <div v-if="groupModal" class="modal-backdrop">
            <form class="notes-modal group-modal" @submit.prevent="saveGroup">
                <h2>{{ groupForm.id ? 'ویرایش گروه' : 'گروه جدید' }}</h2>
                <label>نام گروه<input v-model="groupForm.name" placeholder="مثلاً: قالب‌های پیام" required /></label>
                <div class="color-picker">
                    <button v-for="color in colors" :key="color" type="button" :class="{ active: groupForm.color === color }" :style="{ background: color }" @click="groupForm.color = color"></button>
                </div>
                <footer><button type="button" @click="groupModal = false">انصراف</button><button class="primary" type="submit">{{ groupForm.id ? 'ذخیره' : 'ساخت گروه' }}</button></footer>
            </form>
        </div>

        <div v-if="noteModal" class="modal-backdrop">
            <form class="notes-modal item-modal" @submit.prevent="saveNote">
                <h2>{{ noteForm.id ? 'ویرایش یادداشت' : 'مورد جدید' }}</h2>
                <label>عنوان<input v-model="noteForm.title" placeholder="مثلاً: پیام خوش‌آمدگویی" required /></label>
                <label>گروه<select v-model="noteForm.groupId" required><option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option></select></label>
                <div class="type-switch">
                    <button type="button" :class="{ active: noteForm.content_type === 'text' }" @click="noteForm.content_type = 'text'">متن ساده</button>
                    <button type="button" :class="{ active: noteForm.content_type === 'code' }" @click="noteForm.content_type = 'code'">کد برنامه‌نویسی</button>
                </div>
                <label v-if="noteForm.content_type === 'code'">زبان<select v-model="noteForm.language"><option v-for="[value, label] in languages" :key="value" :value="value">{{ label }}</option></select></label>
                <label class="check-line"><input v-model="noteForm.is_important" type="checkbox" /> مهم</label>
                <textarea v-model="noteForm.content" :class="{ code: noteForm.content_type === 'code' }" :placeholder="noteForm.content_type === 'code' ? '// کد خودت را اینجا بنویس' : 'متن خودت را اینجا بنویس...'" />
                <footer><button type="button" @click="noteModal = false">انصراف</button><button class="primary pink" type="submit">ذخیره</button></footer>
            </form>
        </div>

        <div v-if="viewNote" class="modal-backdrop">
            <section class="notes-modal view-modal">
                <header>
                    <h2>{{ viewNote.title }}</h2>
                    <span>{{ viewNote.content_type === 'code' ? languageLabel(viewNote.language) : 'متن' }}</span>
                    <button class="fullscreen-btn" type="button" @click="fullScreenCode = true">تمام صفحه</button>
                    <button type="button" @click="viewNote = null; fullScreenCode = false">×</button>
                </header>
                <pre v-if="viewNote.content_type === 'code'" class="full-code"><code><span v-for="(token, tokenIndex) in highlightedCode(viewNote.content)" :key="tokenIndex" :class="token.className">{{ token.text }}</span></code></pre>
                <div v-else class="full-text">{{ viewNote.content }}</div>
                <footer>
                    <button type="button" @click="openNoteModal(viewNote.notebook_note_group_id, viewNote); viewNote = null">ویرایش</button>
                    <button class="copy-modal" type="button" @click="copyText(viewNote.content)">کپی همه</button>
                </footer>
            </section>
        </div>

        <div v-if="viewNote && fullScreenCode" class="code-fullscreen" :class="{ text: viewNote.content_type === 'text' }">
            <header>
                <div>
                    <strong>{{ viewNote.title }}</strong>
                    <span>{{ viewNote.content_type === 'code' ? languageLabel(viewNote.language) : 'متن' }}</span>
                </div>
                <button class="copy-modal" type="button" @click="copyText(viewNote.content)">کپی همه</button>
                <button type="button" @click="fullScreenCode = false">بستن</button>
            </header>
            <pre v-if="viewNote.content_type === 'code'" class="fullscreen-code"><code><span v-for="(token, tokenIndex) in highlightedCode(viewNote.content)" :key="tokenIndex" :class="token.className">{{ token.text }}</span></code></pre>
            <article v-else class="fullscreen-text">{{ viewNote.content || 'بدون محتوا' }}</article>
        </div>

        <div v-if="deleteConfirm" class="modal-backdrop">
            <section class="notes-modal confirm-modal">
                <h2>{{ deleteConfirm.title }}</h2>
                <p>{{ deleteConfirm.message }}</p>
                <footer><button type="button" @click="deleteConfirm = null">انصراف</button><button class="danger" type="button" @click="deleteConfirm.run">حذف شود</button></footer>
            </section>
        </div>
    </main>
</template>

<style scoped>
.notes-page{min-height:100vh;display:flex;justify-content:center;align-items:flex-start;padding:44px 20px 90px;color:#3a2e1f;background:#241b2f;background-image:radial-gradient(circle at 20% 20%,#2e2140 0%,#1a1424 70%)}.notes-sheet{width:940px;max-width:100%;position:relative;padding:34px;border-radius:6px;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;box-shadow:0 30px 60px rgba(0,0,0,.5),0 0 0 1px rgba(0,0,0,.05);transform:rotate(-.3deg)}.tape{position:absolute;height:34px;opacity:.85;box-shadow:0 3px 6px rgba(0,0,0,.2)}.tape-a{top:-16px;right:60px;width:110px;background:#ffd93d;transform:rotate(-6deg)}.tape-b{top:-14px;left:80px;width:90px;background:#22d3d0;transform:rotate(5deg)}.tape-c{top:20px;left:-14px;width:32px;height:90px;background:#ff6fa5;transform:rotate(3deg)}.notes-header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}.notes-heading{display:flex;align-items:center;gap:10px;min-width:0}.back-btn{width:38px;height:38px;border-radius:10px;border:2px solid #3a2e1f;background:#fff;display:grid;place-items:center;box-shadow:2px 2px 0 #3a2e1f;cursor:pointer}.back-btn svg,.note-card svg,.note-group svg,.notes-search svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round}.note-logo{width:38px;height:38px;border-radius:50%;background:#9b5de5;display:grid;place-items:center;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#fff;font-style:normal;box-shadow:2px 2px 0 #3a2e1f;transform:rotate(-4deg)}h1{font-family:Lalezar,Vazirmatn,sans-serif;font-size:28px;line-height:1;margin:0;color:#3a2e1f}p{margin:0}.notes-heading p{font-size:11.5px;color:#9a8b6a;margin-top:5px}.add-group-btn,.first-note-btn{height:38px;padding:0 16px;border-radius:10px;border:2px solid #3a2e1f;background:#ffd93d;color:#3a2e1f;cursor:pointer;font-size:13px;font-weight:900;box-shadow:2px 2px 0 #3a2e1f}.notes-search{height:44px;display:flex;align-items:center;gap:10px;margin-bottom:24px;padding:0 14px;border:2px solid #3a2e1f;border-radius:14px;background:#fff;box-shadow:2px 2px 0 #3a2e1f;color:#9a8b6a}.notes-search input{flex:1;height:100%;border:0;outline:0;background:transparent;font-size:13.5px}.notes-search button{border:0;background:transparent;color:#b9ac8c;font-size:18px;cursor:pointer}.note-group{overflow:hidden;margin-bottom:20px;border:2px solid #3a2e1f;border-radius:16px;background:var(--group-color);box-shadow:4px 4px 0 #3a2e1f}.note-group-head{display:flex;align-items:center;gap:12px;padding:14px 18px;background:rgba(255,255,255,.14);cursor:pointer;color:#fff}.note-group-head>i{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;border:2px solid #3a2e1f;background:#fff;color:var(--group-color)}.note-group-head>div{flex:1;min-width:0}.note-group-head strong{display:block;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px}.note-group-head small{font-size:11px;opacity:.85}.group-action{width:30px;height:30px;border-radius:9px;border:2px solid #3a2e1f;background:#fff;color:var(--group-color);display:grid;place-items:center;cursor:pointer;flex-shrink:0}.group-action svg{width:14px;height:14px}.group-action.delete{border-color:rgba(255,255,255,.5);background:transparent;color:#fff}.chevron{color:#fff;transition:.2s}.chevron.closed{transform:rotate(-90deg)}.note-group-body{padding:16px 18px 20px;background:#fffbf0}.note-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.note-card{display:flex;flex-direction:column;gap:9px;min-width:0;padding:13px 14px;border:2px solid #3a2e1f;border-radius:13px;background:#fff;box-shadow:2px 2px 0 #3a2e1f}.note-card.important{background:linear-gradient(180deg,#fff,#fff8dc)}.note-card header{display:flex;align-items:center;justify-content:space-between;gap:8px}.note-card header strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13.5px;font-weight:900}.note-card header span,.view-modal header span{padding:3px 9px;border:1px solid #3a2e1f;border-radius:20px;background:#fff3e0;color:#8a4b1e;font-size:10px;font-weight:900;white-space:nowrap}.note-card p{max-height:52px;overflow:hidden;color:#6b5d45;font-size:12px;line-height:1.7}.code-preview,.full-code{direction:ltr;text-align:left;white-space:pre;overflow:auto;border-radius:9px;background:#171321;color:#efe3c4;font-family:"JetBrains Mono",monospace}.code-preview{max-height:72px;margin:0;padding:9px 11px;font-size:11px;line-height:1.6}.code-preview code,.full-code code{font-family:inherit}.code-preview .comment,.full-code .comment{color:#837858;font-style:italic}.code-preview .string,.full-code .string{color:#ffd93d}.code-preview .number,.full-code .number{color:#ff8a80}.code-preview .keyword,.full-code .keyword{color:#9b8cff;font-weight:700}.code-preview .tag,.full-code .tag{color:#22d3d0}.code-preview .punctuation,.full-code .punctuation{color:#ff6fa5}.note-card footer,.notes-modal footer{display:flex;align-items:center;gap:6px;justify-content:flex-end}.note-card footer button{width:30px;height:30px;border-radius:8px;border:2px solid #3a2e1f;background:#fff;color:#3a2e1f;display:grid;place-items:center;cursor:pointer}.note-card footer .copy{width:auto;flex:1;display:flex;align-items:center;justify-content:center;gap:5px;background:#22d3d0;color:#0b4a48;font-size:11.5px;font-weight:900}.note-card footer .soft-delete{border:0;background:transparent;color:#c7b896}.empty-note{text-align:center;padding:22px;border:2px dashed #b9ac8c;border-radius:14px;background:#fff;color:#9a8b6a;font-size:12.5px}.first-note-btn{display:block;margin:18px auto 0}.toast{position:fixed;bottom:32px;left:50%;z-index:90;transform:translateX(-50%);padding:11px 22px;border-radius:30px;background:#3a2e1f;color:#fff;font-size:13px;font-weight:800;box-shadow:0 8px 20px rgba(0,0,0,.4)}.modal-backdrop{position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(20,15,10,.6)}.notes-modal{width:480px;max-width:94vw;max-height:88vh;overflow:auto;padding:22px;border:3px solid #3a2e1f;border-radius:18px;background:#fffbf0;box-shadow:6px 6px 0 rgba(0,0,0,.3)}.notes-modal h2{margin:0 0 16px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:22px;color:#d63384}.notes-modal label{display:grid;gap:6px;margin-bottom:14px;color:#9a8b6a;font-size:11.5px;font-weight:800}.notes-modal input,.notes-modal select,.notes-modal textarea{width:100%;min-width:0;border:1.5px solid #efe3c4;border-radius:10px;background:#fff;padding:0 11px;outline:0;color:#3a2e1f;font-size:13px}.notes-modal input,.notes-modal select{height:38px}.notes-modal textarea{min-height:150px;padding:10px 11px;resize:vertical;line-height:1.8}.notes-modal textarea.code{direction:ltr;text-align:left;background:#171321;color:#efe3c4;font-family:"JetBrains Mono",monospace;font-size:12.5px}.color-picker{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.color-picker button{width:32px;height:32px;border-radius:9px;border:2px solid rgba(0,0,0,.15);cursor:pointer}.color-picker .active{border:3px solid #3a2e1f}.type-switch{display:flex;gap:8px;margin-bottom:14px}.type-switch button{flex:1;height:36px;border-radius:9px;border:2px solid #3a2e1f;background:#fff;font-weight:800;cursor:pointer}.type-switch .active:first-child{background:#ff6fa5;color:#fff}.type-switch .active:last-child{background:#9b5de5;color:#fff}.check-line{display:flex!important;grid-template-columns:auto 1fr;align-items:center;justify-content:start;gap:8px;color:#3a2e1f}.check-line input{width:18px;height:18px}.notes-modal footer button{min-width:92px;height:38px;border-radius:10px;border:1.5px solid #3a2e1f;background:#fff;color:#3a2e1f;font-size:13px;font-weight:800;cursor:pointer}.notes-modal footer .primary{background:#9b5de5;color:#fff}.notes-modal footer .pink{background:#d63384}.notes-modal footer .danger{background:#b91c1c;color:#fff}.view-modal{width:560px}.view-modal header{display:flex;align-items:center;gap:10px;margin-bottom:14px}.view-modal header h2{flex:1;min-width:0;margin:0;color:#3a2e1f}.view-modal header button{width:30px;height:30px;border:2px solid #3a2e1f;border-radius:9px;background:#fff;font-size:16px;cursor:pointer}.full-code{max-height:52vh;margin:0;padding:14px;font-size:12.5px;line-height:1.7;border:2px solid #3a2e1f}.full-text{white-space:pre-wrap;padding:16px;border:2px solid #efe3c4;border-radius:12px;background:#fff;color:#3a2e1f;font-size:13.5px;line-height:1.9}.copy-modal{background:#22d3d0!important;color:#0b4a48!important}.confirm-modal{width:360px}.confirm-modal h2{color:#b91c1c}.confirm-modal p{margin-bottom:18px;color:#4b3b22;font-size:13px}
.fullscreen-btn{width:auto!important;min-width:86px!important;padding:0 12px;border-width:1.5px!important;background:#ffd93d!important;font-size:12px!important;font-weight:900}.code-fullscreen{position:fixed;inset:0;z-index:120;display:grid;grid-template-rows:auto minmax(0,1fr);gap:0;background:#171321;color:#efe3c4;direction:rtl}.code-fullscreen.text{background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;color:#3a2e1f}.code-fullscreen header{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:2px solid rgba(255,255,255,.08);background:#211b2e;box-shadow:0 8px 22px rgba(0,0,0,.22)}.code-fullscreen.text header{border-bottom:2px solid #efe3c4;background:#fff8e8}.code-fullscreen header>div{flex:1;min-width:0;display:flex;align-items:center;gap:10px}.code-fullscreen strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff;font-size:15px}.code-fullscreen.text strong{color:#3a2e1f}.code-fullscreen header span{padding:4px 10px;border-radius:999px;background:#2d2540;color:#ffd93d;font-family:"JetBrains Mono",monospace;font-size:11px}.code-fullscreen.text header span{background:#fff3e0;color:#8a4b1e;font-family:Vazirmatn,sans-serif}.code-fullscreen button{height:34px;border:2px solid #3a2e1f;border-radius:10px;background:#fffbf0;color:#3a2e1f;padding:0 14px;font-size:12px;font-weight:900;cursor:pointer;box-shadow:2px 2px 0 #3a2e1f}.fullscreen-code{min-width:0;min-height:0;margin:0;padding:22px 26px;overflow:auto;direction:ltr;text-align:left;white-space:pre;color:#f5ead3;background:#171321;font-family:"JetBrains Mono",monospace;font-size:14px;line-height:1.85}.fullscreen-code code{font-family:inherit}.fullscreen-code span{display:inline!important;margin:0!important;padding:0!important;border-radius:0!important;background:transparent!important}.fullscreen-code .comment{color:#8f8465;font-style:italic}.fullscreen-code .string{color:#ffe26a}.fullscreen-code .number{color:#ff9a92}.fullscreen-code .keyword{color:#aea4ff;font-weight:700}.fullscreen-code .tag{color:#4ce3e0}.fullscreen-code .punctuation{color:#ff7eb6}.fullscreen-text{min-width:0;min-height:0;overflow:auto;margin:24px auto;padding:28px 30px;width:min(960px,calc(100vw - 32px));border:2px solid #3a2e1f;border-radius:18px;background:#fff;color:#3a2e1f;box-shadow:5px 5px 0 #3a2e1f;white-space:pre-wrap;font-size:17px;line-height:2.15;text-align:right}
.notes-header{align-items:center!important;justify-content:space-between!important;margin-bottom:14px!important}.notes-heading{order:1;flex:1;justify-content:flex-start}.notes-top-tools{order:2;display:flex;align-items:center;justify-content:flex-end;margin-right:auto}.notes-actions-row{display:flex;justify-content:flex-end;margin:0 0 18px}.back-btn{display:none!important}.notes-search{margin-top:0}
.notes-page .modal-backdrop{z-index:9000}.notes-page .notes-modal{position:relative;z-index:9001}
.view-modal .full-text,.view-modal .full-code{margin-bottom:18px}.view-modal footer{padding-top:4px}
.note-card.important{position:relative;border-color:#b45309!important;background:linear-gradient(180deg,#fffdf4,#fff7d6)!important;box-shadow:2px 2px 0 #3a2e1f,0 0 0 4px rgba(255,217,61,.22)!important}.note-card.important::before{content:"";position:absolute;top:12px;bottom:12px;right:-2px;width:6px;border-radius:999px;background:#ffd93d;border:2px solid #3a2e1f}.note-card-badges{display:flex;align-items:center;gap:6px;flex-shrink:0}.note-card-badges em{padding:3px 9px;border:1.5px solid #3a2e1f;border-radius:999px;background:#ffd93d;color:#3a2e1f;font-size:10px;font-style:normal;font-weight:900;white-space:nowrap;box-shadow:1px 1px 0 #3a2e1f}.note-card-badges span{padding:3px 9px;border:1px solid #3a2e1f;border-radius:20px;background:#fff3e0;color:#8a4b1e;font-size:10px;font-weight:900;white-space:nowrap}
.note-card p{white-space:pre-wrap;max-height:88px!important}
@media(max-width:700px){.notes-page{padding:24px 10px 80px}.notes-sheet{padding:24px 14px 28px;transform:none}.notes-header{align-items:flex-start}.notes-heading{width:100%;flex-wrap:wrap}.notes-heading>div:last-child{width:100%}h1{font-size:25px}.add-group-btn{width:100%;justify-content:center}.note-grid{grid-template-columns:1fr}.note-group-head{gap:8px;padding:12px}.note-group-head strong{font-size:18px}.group-action{width:28px;height:28px}.notes-modal{padding:18px}.modal-backdrop{align-items:flex-start;overflow:auto}.view-modal header{align-items:flex-start;flex-wrap:wrap}.view-modal header h2{width:100%;font-size:20px}.code-fullscreen header{align-items:flex-start;flex-wrap:wrap;padding:10px}.code-fullscreen header>div{width:100%;flex:1 0 100%}.code-fullscreen button{flex:1}.fullscreen-code{padding:16px 12px;font-size:12.5px;line-height:1.8}.fullscreen-text{width:calc(100vw - 20px);margin:12px auto;padding:18px 16px;font-size:15px;line-height:2}}
</style>
