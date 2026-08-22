<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type GroupTaskItem = { id: number; title: string; period_type: PeriodType; is_done: boolean; sort_order: number };
type GroupTaskProject = {
    id: number;
    category_id: number;
    task_group_id: number;
    name: string;
    color: string;
    soft_color: string;
    done_count: number;
    total_count: number;
    items: GroupTaskItem[];
};
type PeriodType = 'weekly' | 'monthly' | 'general';
type GroupOption = { id: number; category_id: number; name: string; color: string; soft_color: string; already_added: boolean };
type Section = { id: number; name: string; color: string; soft_color: string; groups: GroupOption[]; projects: GroupTaskProject[] };

const loading = ref(true);
const router = useRouter();
const error = ref('');
const sections = ref<Section[]>([]);
const draftTasks = ref<Record<number, string>>({});
const draftPeriods = ref<Record<number, PeriodType>>({});
const addProjectSection = ref<Section | null>(null);
const deleteProjectTarget = ref<GroupTaskProject | null>(null);
const draggedItem = ref<{ projectId: number; itemId: number } | null>(null);
const editingTask = ref<{ projectId: number; itemId: number } | null>(null);
const editTaskDraft = ref('');
const toast = ref('');
const tableFilter = ref<'all' | 'weekly' | 'monthly'>('all');
const overviewOpen = ref(false);
const tilts = ['rotate(-0.4deg)', 'rotate(0.3deg)', 'rotate(-0.2deg)'];
const periods: { key: PeriodType; label: string; hint: string }[] = [
    { key: 'general', label: 'کلی', hint: 'بک‌لاگ و ایده‌های آزاد' },
    { key: 'monthly', label: 'ماهانه', hint: 'هدف‌ها و پیگیری‌های این ماه' },
    { key: 'weekly', label: 'هفتگی', hint: 'کارهای قابل انجام در این هفته' },
];

const hasAnyProject = computed(() => sections.value.some((section) => section.projects.length));
const tableTasks = computed(() => sections.value.flatMap((section) => section.projects.flatMap((project) => project.items.map((item) => ({
    item,
    project,
    section,
})))).filter(({ item }) => tableFilter.value === 'all' || item.period_type === tableFilter.value));
const filterCount = (filter: 'all' | 'weekly' | 'monthly') => sections.value.reduce((count, section) => count + section.projects.reduce((projectCount, project) => projectCount + project.items.filter((item) => filter === 'all' || item.period_type === filter).length, 0), 0);
const tableTitle = computed(() => ({ all: 'همه کارهای گروهی', weekly: 'کارهای این هفته', monthly: 'کارهای این ماه' })[tableFilter.value]);

onMounted(load);

function fa(value: number | string) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/group-tasks');
        sections.value = data.sections ?? [];
    } catch {
        error.value = 'کارهای گروهی فعلاً بارگذاری نشدند.';
    } finally {
        loading.value = false;
    }
}

function updateProject(project: GroupTaskProject) {
    sections.value = sections.value.map((section) => ({
        ...section,
        projects: section.projects.map((item) => item.id === project.id ? project : item),
    }));
}

async function addProject(group: GroupOption) {
    if (group.already_added) return;
    const { data } = await api.post('/group-task-projects', { task_group_id: group.id });
    sections.value = sections.value.map((section) => section.id === data.category_id
        ? {
            ...section,
            groups: section.groups.map((item) => item.id === group.id ? { ...item, already_added: true } : item),
            projects: [...section.projects, data],
        }
        : section);
    addProjectSection.value = null;
}

async function goDefineGroups(section: Section) {
    await router.push({ path: '/settings', query: { taskGroupCategory: String(section.id), createTaskGroup: '1' } });
}

async function deleteProject() {
    if (!deleteProjectTarget.value) return;
    const project = deleteProjectTarget.value;
    await api.delete(`/group-task-projects/${project.id}`);
    sections.value = sections.value.map((section) => section.id === project.category_id
        ? {
            ...section,
            groups: section.groups.map((group) => group.id === project.task_group_id
                ? { ...group, already_added: false }
                : group),
            projects: section.projects.filter((item) => item.id !== project.id),
        }
        : section);
    deleteProjectTarget.value = null;
}

function tasksFor(project: GroupTaskProject, period: PeriodType) {
    return project.items.filter((item) => item.period_type === period);
}

function openAddProject(section: Section) {
    addProjectSection.value = section;
}

function draftPeriod(project: GroupTaskProject) {
    return draftPeriods.value[project.id] ?? 'general';
}

function setDraftPeriod(project: GroupTaskProject, period: PeriodType) {
    draftPeriods.value[project.id] = period;
}

function updateDraftPeriod(project: GroupTaskProject, event: Event) {
    setDraftPeriod(project, (event.target as HTMLSelectElement).value as PeriodType);
}

async function addTask(project: GroupTaskProject) {
    const title = (draftTasks.value[project.id] || '').trim();
    if (!title) return;
    const period = draftPeriod(project);
    const { data } = await api.post(`/group-task-projects/${project.id}/items`, { title, period_type: period });
    updateProject({
        ...project,
        total_count: project.total_count + 1,
        items: [...project.items, data],
    });
    draftTasks.value[project.id] = '';
}

async function toggleTask(project: GroupTaskProject, item: GroupTaskItem) {
    const { data } = await api.put(`/group-task-items/${item.id}`, { is_done: !item.is_done });
    const items = project.items.map((task) => task.id === item.id ? data : task);
    updateProject({
        ...project,
        done_count: items.filter((task) => task.is_done).length,
        total_count: items.length,
        items,
    });
}

function isEditingTask(project: GroupTaskProject, item: GroupTaskItem) {
    return editingTask.value?.projectId === project.id && editingTask.value?.itemId === item.id;
}

function startEditTask(project: GroupTaskProject, item: GroupTaskItem) {
    editingTask.value = { projectId: project.id, itemId: item.id };
    editTaskDraft.value = item.title;
}

function cancelEditTask() {
    editingTask.value = null;
    editTaskDraft.value = '';
}

async function saveEditTask(project: GroupTaskProject, item: GroupTaskItem) {
    const title = editTaskDraft.value.trim();
    if (!title) return;
    const { data } = await api.put(`/group-task-items/${item.id}`, { title });
    const items = project.items.map((task) => task.id === item.id ? data : task);
    updateProject({
        ...project,
        done_count: items.filter((task) => task.is_done).length,
        total_count: items.length,
        items,
    });
    cancelEditTask();
}

async function deleteTask(project: GroupTaskProject, item: GroupTaskItem) {
    await api.delete(`/group-task-items/${item.id}`);
    const items = project.items.filter((task) => task.id !== item.id);
    updateProject({
        ...project,
        done_count: items.filter((task) => task.is_done).length,
        total_count: items.length,
        items,
    });
}

async function dropTask(project: GroupTaskProject, target: GroupTaskItem) {
    const source = draggedItem.value;
    draggedItem.value = null;
    if (!source || source.projectId !== project.id || source.itemId === target.id) return;

    const items = [...project.items];
    const from = items.findIndex((item) => item.id === source.itemId);
    const to = items.findIndex((item) => item.id === target.id);
    if (from < 0 || to < 0) return;

    if (items[from].period_type !== target.period_type) {
        await moveTaskToPeriod(project, items[from], target.period_type);
        return;
    }

    const [moved] = items.splice(from, 1);
    items.splice(to, 0, moved);
    updateProject({ ...project, items });

    const { data } = await api.post(`/group-task-projects/${project.id}/items/reorder`, { item_ids: items.map((item) => item.id) });
    updateProject(data);
}

async function moveTaskToPeriod(project: GroupTaskProject, item: GroupTaskItem, period: PeriodType) {
    if (item.period_type === period) return;
    const { data } = await api.post(`/group-task-items/${item.id}/move-period`, { period_type: period });
    updateProject(data);
    toast.value = `«${item.title}» به ${periods.find((periodItem) => periodItem.key === period)?.label} منتقل شد`;
    window.setTimeout(() => toast.value = '', 2500);
}

async function dropTaskToPeriod(project: GroupTaskProject, period: PeriodType) {
    const source = draggedItem.value;
    if (!source) return;
    draggedItem.value = null;
    if (source.projectId !== project.id) return;
    const item = project?.items.find((candidate) => candidate.id === source.itemId);
    if (!project || !item) return;
    await moveTaskToPeriod(project, item, period);
}

</script>

<template>
    <div class="team-tasks-shell" dir="rtl">
        <main class="team-paper">
            <i class="team-tape yellow"></i>
            <i class="team-tape cyan"></i>

            <header class="team-head">
                <div class="team-brand">
                    <i>گ</i>
                    <h1>کارهای گروهی</h1>
                </div>
                <AppMenu />
            </header>

            <div v-if="loading" class="team-state">در حال بارگذاری کارهای گروهی...</div>
            <div v-else-if="error" class="team-state error">{{ error }}</div>
            <template v-else>
                <section class="group-task-overview" aria-label="فیلتر و جدول کارهای گروهی">
                    <button type="button" class="overview-toggle" :aria-expanded="overviewOpen" @click="overviewOpen = !overviewOpen">
                        <div>
                            <span class="overview-kicker">نمای یک‌نگاه</span>
                            <h2>نمای کارهای گروهی</h2>
                            <p>{{ overviewOpen ? 'بازه زمانی دلخواهت را انتخاب کن.' : 'برای دیدن جدول و فیلتر کارها کلیک کن.' }}</p>
                        </div>
                        <span class="overview-toggle-end"><strong><b>{{ fa(tableTasks.length) }}</b> کار</strong><i :class="{ open: overviewOpen }">⌄</i></span>
                    </button>
                    <div v-if="overviewOpen" class="overview-content">
                        <h3>{{ tableTitle }}</h3>
                        <div class="overview-filters" role="tablist" aria-label="بازه زمانی کارها">
                        <button type="button" :class="{ active: tableFilter === 'weekly' }" @click="tableFilter = 'weekly'">
                            این هفته <b>{{ fa(filterCount('weekly')) }}</b>
                        </button>
                        <button type="button" :class="{ active: tableFilter === 'monthly' }" @click="tableFilter = 'monthly'">
                            این ماه <b>{{ fa(filterCount('monthly')) }}</b>
                        </button>
                        <button type="button" :class="{ active: tableFilter === 'all' }" @click="tableFilter = 'all'">
                            همه کارها <b>{{ fa(filterCount('all')) }}</b>
                        </button>
                        </div>
                        <div class="overview-table-wrap">
                        <table class="overview-table">
                            <thead><tr><th>کار</th><th>پروژه</th><th>دسته‌بندی</th><th>وضعیت</th></tr></thead>
                            <tbody>
                                <tr v-for="({ item, project, section }) in tableTasks" :key="`overview-${item.id}`">
                                    <td class="overview-task"><button type="button" :class="{ done: item.is_done }" :style="{ '--g': project.color, '--soft': project.soft_color }" :aria-label="item.is_done ? 'علامت‌گذاری به‌عنوان انجام‌نشده' : 'علامت‌گذاری به‌عنوان انجام‌شده'" @click="toggleTask(project, item)"></button><span :class="{ done: item.is_done }">{{ item.title }}</span></td>
                                    <td><i class="project-dot" :style="{ background: project.color }"></i>{{ project.name }}</td>
                                    <td>{{ section.name }}</td>
                                    <td><em :class="item.is_done ? 'is-done' : 'is-pending'">{{ item.is_done ? 'انجام شده' : 'در انتظار' }}</em></td>
                                </tr>
                                <tr v-if="!tableTasks.length"><td colspan="4" class="overview-empty">در این بازه کاری برای نمایش وجود ندارد.</td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
                <section v-for="section in sections" :key="section.id" class="team-section" :style="{ '--c': section.color }">
                    <header>
                        <span></span>
                        <h2>{{ section.name }}</h2>
                    </header>
                    <button class="add-project-btn section-add" type="button" @click="openAddProject(section)">＋ اضافه کردن پروژه</button>
                    <div class="project-list">
                        <article
                            v-for="(project, index) in section.projects"
                            :key="project.id"
                            class="project-card"
                            :style="{ '--g': project.color, '--soft': project.soft_color, transform: tilts[index % tilts.length] }"
                        >
                            <header>
                                <strong>{{ project.name }}</strong>
                                <div>
                                    <span v-if="project.total_count">{{ fa(project.done_count) }} از {{ fa(project.total_count) }} انجام شد</span>
                                    <button type="button" @click="deleteProjectTarget = project">
                                        <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v13a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z M10 11v6M14 11v6"></path></svg>
                                    </button>
                                </div>
                            </header>

                            <div class="task-period-board">
                                <section
                                    v-for="period in periods"
                                    :key="period.key"
                                    class="task-period-lane"
                                    :class="[`task-period-${period.key}`, { over: draggedItem?.projectId === project.id }]"
                                    @dragover.prevent
                                    @drop="dropTaskToPeriod(project, period.key)"
                                >
                                    <header>
                                        <strong>{{ period.label }}</strong>
                                        <small>{{ fa(tasksFor(project, period.key).length) }} کار</small>
                                    </header>
                                    <div class="todo-list">
                                        <div
                                            v-for="item in tasksFor(project, period.key)"
                                            :key="item.id"
                                            class="todo-row"
                                            :style="{ '--soft': project.soft_color }"
                                            :draggable="!isEditingTask(project, item)"
                                            @dragstart.stop="draggedItem = { projectId: project.id, itemId: item.id }"
                                            @dragover.prevent
                                            @drop.stop="dropTask(project, item)"
                                            @dragend="draggedItem = null"
                                        >
                                            <svg viewBox="0 0 24 24"><path d="M8 6h8M8 12h8M8 18h8"></path></svg>
                                            <button type="button" :class="{ done: item.is_done }" :style="{ '--g': project.color }" @click="toggleTask(project, item)"></button>
                                            <input
                                                v-if="isEditingTask(project, item)"
                                                v-model="editTaskDraft"
                                                class="edit-task-input"
                                                autofocus
                                                @keydown.enter.prevent="saveEditTask(project, item)"
                                                @keydown.esc.prevent="cancelEditTask"
                                            />
                                            <span v-else :class="{ done: item.is_done }">{{ item.title }}</span>
                                            <button
                                                v-if="!isEditingTask(project, item)"
                                                class="edit-task-btn"
                                                type="button"
                                                title="ویرایش"
                                                @click="startEditTask(project, item)"
                                            >
                                                <svg viewBox="0 0 24 24"><path d="M5 19l4-.8L18.5 8.7a2 2 0 00-2.8-2.8L6.2 15.4 5 19zM14.5 7.1l2.4 2.4"></path></svg>
                                            </button>
                                            <button
                                                v-if="isEditingTask(project, item)"
                                                class="edit-save"
                                                type="button"
                                                title="ثبت"
                                                @click="saveEditTask(project, item)"
                                            >✓</button>
                                            <button
                                                v-if="isEditingTask(project, item)"
                                                class="edit-cancel"
                                                type="button"
                                                title="لغو"
                                                @click="cancelEditTask"
                                            >×</button>
                                            <button v-else class="delete-task" type="button" @click="deleteTask(project, item)">×</button>
                                        </div>
                                        <div v-if="!tasksFor(project, period.key).length" class="no-tasks">کاری ثبت نشده</div>
                                    </div>
                                </section>
                            </div>

                            <div class="new-task-row">
                                <div class="new-task-period">
                                    <select :value="draftPeriod(project)" title="جای افزودن کار" @change="updateDraftPeriod(project, $event)">
                                        <option v-for="period in periods" :key="period.key" :value="period.key">{{ period.label }}</option>
                                    </select>
                                </div>
                                <input
                                    v-model="draftTasks[project.id]"
                                    placeholder="یک کار جدید بنویس..."
                                    @keydown.enter.exact.prevent="addTask(project)"
                                />
                                <button type="button" @click="addTask(project)">＋</button>
                            </div>
                        </article>
                        <div v-if="!section.projects.length" class="section-empty">هنوز پروژه‌ای در این گروه نیست</div>
                    </div>
                </section>

                <div v-if="!hasAnyProject" class="team-state empty">برای شروع، از بخش‌های بالا یک پروژه اضافه کن.</div>
            </template>
        </main>

        <div v-if="addProjectSection" class="team-modal-backdrop">
            <section class="team-modal">
                <h2>افزودن پروژه به «{{ addProjectSection.name }}»</h2>
                <p>از لیست پروژه‌های تعریف‌شده در تنظیمات انتخاب کن</p>
                <div class="catalog-list">
                    <button
                        v-for="group in addProjectSection.groups"
                        :key="group.id"
                        type="button"
                        :disabled="group.already_added"
                        :style="{ '--g': group.color }"
                        @click="addProject(group)"
                    >
                        <i :class="{ added: group.already_added }"></i>
                        <span>{{ group.name }}</span>
                        <small v-if="group.already_added">قبلاً اضافه شده</small>
                    </button>
                    <div v-if="!addProjectSection.groups.length" class="modal-empty rich">
                        <i>＋</i>
                        <strong>برای این بخش هنوز گروه‌بندی فعالی تعریف نشده است.</strong>
                        <span>اول یک گروه‌بندی بساز، بعد همینجا آن را به‌عنوان پروژه اضافه کن.</span>
                        <button type="button" @click="goDefineGroups(addProjectSection)">تعریف گروه‌بندی</button>
                    </div>
                    <div v-else-if="addProjectSection.groups.every((group) => group.already_added)" class="modal-empty">همه پروژه‌های تعریف‌شده اضافه شده‌اند. برای افزودن پروژه جدید به تنظیمات مراجعه کن.</div>
                </div>
                <footer><button type="button" @click="addProjectSection = null">انصراف</button></footer>
            </section>
        </div>

        <div v-if="toast" class="team-toast">{{ toast }}</div>

        <div v-if="deleteProjectTarget" class="team-modal-backdrop">
            <section class="team-modal confirm">
                <h2>حذف پروژه</h2>
                <p>پروژه «{{ deleteProjectTarget.name }}» و همه کارهایش حذف شود؟</p>
                <footer>
                    <button type="button" @click="deleteProjectTarget = null">انصراف</button>
                    <button type="button" class="danger" @click="deleteProject">حذف شود</button>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.team-tasks-shell{min-height:100vh;display:flex;justify-content:center;align-items:flex-start;padding:44px 20px 90px;background:#241b2f;background-image:radial-gradient(circle at 20% 20%,#2e2140 0%,#1a1424 70%);color:#3a2e1f;font-family:Vazirmatn,sans-serif}.team-paper{width:900px;max-width:100%;position:relative;padding:34px 34px 40px;background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;border-radius:6px;box-shadow:0 30px 60px rgba(0,0,0,.5),0 0 0 1px rgba(0,0,0,.05);transform:rotate(-.3deg)}.team-tape{position:absolute;height:34px;box-shadow:0 3px 6px rgba(0,0,0,.2);opacity:.85}.team-tape.yellow{top:-16px;right:60px;width:110px;background:#ffd93d;transform:rotate(-6deg)}.team-tape.cyan{top:-14px;left:80px;width:90px;background:#22d3d0;transform:rotate(5deg)}.team-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}.team-brand{display:flex;align-items:center;gap:10px}.team-brand i{width:38px;height:38px;border-radius:50%;background:#2563eb;display:grid;place-items:center;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#fff;transform:rotate(-4deg);box-shadow:2px 2px 0 #3a2e1f;font-style:normal}.team-brand h1{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:26px}.team-section{margin-bottom:26px}.team-section>header{display:flex;align-items:center;gap:10px;margin-bottom:6px}.team-section>header span{width:11px;height:11px;border-radius:50%;background:var(--c)}.team-section h2{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:21px;color:var(--c)}.add-project-btn{height:32px;padding:0 13px;border-radius:9px;border:2px dashed #b9ac8c;background:transparent;color:#9a8b6a;font-size:12px;font-weight:900;margin-bottom:14px;cursor:pointer}.project-list{display:flex;flex-direction:column;gap:14px}.project-card{background:#fff;border:2px solid #3a2e1f;border-radius:14px;box-shadow:3px 3px 0 #3a2e1f;padding:15px 17px}.project-card>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px}.project-card strong{font-size:14.5px}.project-card>header div{display:flex;align-items:center;gap:9px}.project-card>header span{font-size:10.5px;color:#9a8b6a}.project-card>header button{width:24px;height:24px;border-radius:50%;border:0;background:transparent;color:#c7b896;display:grid;place-items:center;cursor:pointer}.project-card>header svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round}.todo-list{display:grid}.todo-row{display:flex;align-items:center;gap:7px;padding:7px 2px;border-bottom:1px dashed #efe3c4;cursor:grab}.todo-row>svg{width:12px;height:12px;fill:none;stroke:#d7c9a6;stroke-width:2;flex:none}.todo-row button:not(.delete-task){width:19px;height:19px;border-radius:6px;border:2px solid #d7c9a6;background:#fff;display:grid;place-items:center;padding:0;cursor:pointer;flex:none}.todo-row button.done{border-color:var(--g);background:var(--g)}.todo-row button svg{width:11px;height:11px;fill:none;stroke:#fff;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.todo-row span{flex:1;min-width:0;font-size:13px;color:#3a2e1f}.todo-row span.done{color:#b7a98c;text-decoration:line-through}.delete-task{width:20px;height:20px;border-radius:6px;border:0;background:transparent;color:#d7c9a6;font-size:14px;cursor:pointer;flex:none}.no-tasks,.section-empty,.team-state{border:2px dashed #b9ac8c;border-radius:14px;background:#fff;text-align:center;color:#9a8b6a;font-weight:900}.no-tasks{border:0;background:transparent;font-size:11.5px;padding:8px 0}.section-empty{padding:22px;font-size:12.5px}.team-state{padding:34px 18px}.team-state.error{color:#b91c1c}.team-state.empty{margin-top:10px}.new-task-row{display:flex;gap:6px;margin-top:10px}.new-task-row input{flex:1;height:32px;border-radius:8px;border:1.5px solid #efe3c4;padding:0 9px;font-size:12px;outline:0;font-family:inherit}.new-task-row button{width:32px;height:32px;border-radius:8px;border:2px solid #3a2e1f;background:#ffd93d;color:#3a2e1f;font-size:16px;cursor:pointer;flex:none}.team-modal-backdrop{position:fixed;inset:0;z-index:7000;display:grid;place-items:center;background:rgba(20,15,10,.6);padding:20px}.team-modal{width:380px;max-width:92vw;background:#fffbf0;border:3px solid #3a2e1f;border-radius:18px;padding:22px;box-shadow:6px 6px 0 rgba(0,0,0,.3)}.team-modal h2{margin:0 0 6px;font-family:Lalezar,Vazirmatn,sans-serif;font-size:20px;color:#2563eb}.team-modal.confirm h2{color:#b91c1c}.team-modal p{margin:0 0 12px;font-size:11.5px;color:#9a8b6a}.catalog-list{display:flex;flex-direction:column;gap:7px;max-height:280px;overflow:auto;margin-bottom:16px}.catalog-list button{display:flex;align-items:center;gap:9px;border:2px solid #3a2e1f;background:#fff;border-radius:11px;padding:10px 13px;cursor:pointer;text-align:right}.catalog-list button:disabled{border-color:#efe3c4;background:#f5eedc;cursor:not-allowed}.catalog-list i{width:16px;height:16px;border-radius:5px;border:2px solid var(--g);background:#fff;flex:none}.catalog-list i.added{background:var(--g)}.catalog-list span{flex:1;font-size:13px;font-weight:900}.catalog-list small,.modal-empty{font-size:10.5px;color:#9a8b6a}.modal-empty{text-align:center;padding:14px 0}.team-modal footer{display:flex;justify-content:flex-end;gap:8px}.team-modal footer button{border:1.5px solid #3a2e1f;background:#fff;padding:9px 16px;border-radius:10px;font-size:13px;cursor:pointer}.team-modal footer .danger{background:#b91c1c;color:#fff}
.modal-empty.rich{display:grid;place-items:center;gap:8px;margin:2px 0 4px;padding:22px 14px;border:1.5px dashed #eadfbe;border-radius:14px;background:#fff}.modal-empty.rich i{width:44px;height:44px;display:grid;place-items:center;border:2px solid #3a2e1f;border-radius:14px;background:#ffd93d;color:#3a2e1f;box-shadow:2px 2px 0 #3a2e1f;font-style:normal;font-size:24px;font-weight:900}.modal-empty.rich strong{font-size:13px;color:#3a2e1f}.modal-empty.rich span{font-size:11.5px;line-height:1.9;color:#9a8b6a}.modal-empty.rich button{height:38px;margin-top:4px;padding:0 16px;border:2px solid #3a2e1f;border-radius:11px;background:#2563eb;color:#fff;box-shadow:2px 2px 0 #3a2e1f;font-family:inherit;font-size:12px;font-weight:900;cursor:pointer}
.team-paper{width:1180px}.team-section>header{margin-bottom:12px}.section-add{width:auto;height:36px;margin:0 0 14px;padding:0 14px;border:2px dashed #b9ac8c;border-radius:10px;background:transparent;box-shadow:none;color:#9a8b6a;font-size:12px}.project-card{cursor:default}.project-card>header{align-items:flex-start}.project-card>header strong{flex:1;min-width:0;font-size:18px;line-height:1.65}.task-period-board{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-template-areas:"general general" "monthly weekly";gap:10px;margin-top:10px}.task-period-general{grid-area:general}.task-period-monthly{grid-area:monthly}.task-period-weekly{grid-area:weekly}.task-period-lane{min-width:0;padding:10px;border:1.5px dashed #d7c9a6;border-radius:12px;background:#fffdf7;transition:.15s ease}.task-period-lane.over{background:#eef9ff;border-color:#2563eb}.task-period-lane>header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px}.task-period-lane>header strong{font-size:12.5px;color:#3a2e1f}.task-period-lane>header small{font-size:10px;color:#9a8b6a;font-weight:900}.todo-row{display:grid;grid-template-columns:14px 24px minmax(0,1fr) 22px 22px;gap:8px;align-items:center;cursor:grab}.todo-list .todo-row:last-child{border-bottom:0}.todo-row button:not(.delete-task){width:24px;height:24px;border-radius:8px;border:2px solid #d7c9a6;background:#fff;display:grid;place-items:center;padding:0;cursor:pointer;flex:none}.todo-row span{line-height:1.8;overflow-wrap:anywhere}.edit-task-input{width:100%;min-width:0;height:28px;border:1.5px solid #eadfbe;border-radius:8px;background:#fffdf7;color:#3a2e1f;font:inherit;font-size:12px;font-weight:900;padding:0 8px;outline:0}.edit-task-input:focus{border-color:#2563eb;background:#fff}.edit-task-btn{width:22px!important;height:22px!important;border:0!important;border-radius:7px!important;background:transparent!important;color:#c7b896!important;box-shadow:none!important}.edit-task-btn:hover{color:#8a7a5b!important;background:#fff8e6!important}.edit-task-btn svg{width:12px!important;height:12px!important;stroke:currentColor!important;stroke-width:1.8!important;fill:none!important}.edit-save,.edit-cancel{width:22px!important;height:22px!important;border:1.5px solid #eadfbe!important;border-radius:7px!important;background:#fffaf0!important;color:#8a7a5b!important;font-size:12px!important;font-weight:900!important;line-height:1!important;box-shadow:none!important}.edit-save{color:#2563eb!important}.edit-cancel{color:#b7a98c!important}.new-task-row{gap:0;align-items:stretch}.new-task-row input{border-right:0;border-radius:8px 0 0 8px;height:34px}.new-task-period{position:relative;align-self:stretch;flex:none}.new-task-period::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:1px;background:#eadfbe;z-index:1}.new-task-period::after{content:"";position:absolute;left:9px;top:50%;width:6px;height:6px;border-left:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:translateY(-65%) rotate(-45deg);pointer-events:none;color:#8a7a5b}.new-task-period select{width:62px;height:34px;appearance:none;-webkit-appearance:none;padding:0 9px 0 20px;border:1.5px solid #efe3c4;border-left:0;border-radius:0 8px 8px 0;background:#fff;color:#6f6046;font-family:inherit;font-size:11px;font-weight:900;line-height:1;outline:0;cursor:pointer}.new-task-period select:focus{border-color:#2563eb;background:#eef2ff;color:#2563eb}.new-task-period:has(select:focus)+input{border-color:#2563eb}.new-task-row>button{margin-right:6px}.team-toast{position:fixed;right:22px;bottom:22px;z-index:7200;padding:12px 16px;border:2px solid #3a2e1f;border-radius:12px;background:#22c55e;color:#fff;font-size:12px;font-weight:900;box-shadow:3px 3px 0 #3a2e1f}
.todo-row button.done{position:relative;border-color:var(--g)!important;background:var(--soft)!important}.todo-row button.done::after{content:"";width:7px;height:3.5px;border-left:1.5px solid var(--g);border-bottom:1.5px solid var(--g);transform:rotate(-45deg) translate(1px,-1px)}
.group-task-overview{margin:0 0 30px;padding:6px;border:2px solid #3a2e1f;border-radius:18px;background:linear-gradient(135deg,#fff 0%,#fffaf0 100%);box-shadow:4px 4px 0 #3a2e1f}.overview-toggle{width:100%;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px;border:0;border-radius:13px;background:transparent;color:inherit;text-align:right;font-family:inherit;cursor:pointer}.overview-toggle:hover{background:#fff8e8}.overview-kicker{display:block;margin-bottom:2px;color:#2563eb;font-size:10px;font-weight:900;letter-spacing:.02em}.overview-toggle h2{margin:0;font-family:Lalezar,Vazirmatn,sans-serif;font-size:23px;line-height:1.35;color:#3a2e1f}.overview-toggle p{margin:2px 0 0;color:#8a7a5b;font-size:11px;font-weight:700}.overview-toggle-end{display:flex;align-items:center;gap:9px}.overview-toggle-end strong{display:flex;align-items:baseline;gap:4px;white-space:nowrap;padding:7px 11px;border:1.5px solid #b9ac8c;border-radius:11px;background:#fffbf0;color:#8a7a5b;font-size:10px}.overview-toggle-end strong b{font-size:18px;color:#2563eb}.overview-toggle-end i{display:grid;width:28px;height:28px;place-items:center;border-radius:9px;background:#2563eb;color:#fff;font-style:normal;font-size:20px;line-height:1;transition:transform .18s ease}.overview-toggle-end i.open{transform:rotate(180deg)}.overview-content{padding:0 12px 12px}.overview-content h3{margin:0 0 9px;color:#8a7a5b;font-size:11px;font-weight:900}.overview-filters{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}.overview-filters button{display:inline-flex;align-items:center;gap:7px;height:35px;padding:0 12px;border:1.5px solid #dfd2b3;border-radius:10px;background:#fff;color:#75664b;font-family:inherit;font-size:11.5px;font-weight:900;cursor:pointer;transition:.16s ease}.overview-filters button:hover{border-color:#2563eb;color:#2563eb}.overview-filters button.active{border-color:#2563eb;background:#2563eb;color:#fff;box-shadow:2px 2px 0 #173f91}.overview-filters b{display:grid;min-width:19px;height:19px;padding:0 4px;place-items:center;border-radius:6px;background:#f0e7d3;font-size:10px;color:#75664b}.overview-filters button.active b{background:rgba(255,255,255,.22);color:#fff}.overview-table-wrap{overflow-x:auto;border:1.5px solid #eadfbe;border-radius:12px;background:#fff}.overview-table{width:100%;min-width:620px;border-collapse:collapse;text-align:right}.overview-table th{padding:10px 12px;background:#fff8e8;color:#8a7a5b;font-size:10px;font-weight:900}.overview-table td{padding:10px 12px;border-top:1px solid #f0e7d3;color:#5d503b;font-size:11.5px;font-weight:700}.overview-table tbody tr:not(:has(.overview-empty)):hover{background:#fffcf4}.overview-task{display:flex;align-items:center;gap:8px;min-width:210px}.overview-task>button{width:21px;height:21px;flex:none;border:2px solid #d7c9a6;border-radius:7px;background:#fff;cursor:pointer}.overview-task>button.done{position:relative;border-color:var(--g);background:var(--soft)}.overview-task>button.done::after{content:"";display:block;width:7px;height:3.5px;margin:-2px auto 0;border-left:1.5px solid var(--g);border-bottom:1.5px solid var(--g);transform:rotate(-45deg)}.overview-task span.done{text-decoration:line-through;color:#b7a98c}.project-dot{display:inline-block;width:8px;height:8px;margin-left:6px;border-radius:50%;vertical-align:middle}.overview-table em{display:inline-block;padding:4px 8px;border-radius:999px;font-size:9.5px;font-style:normal}.overview-table em.is-done{background:#dcfce7;color:#15803d}.overview-table em.is-pending{background:#fff1c9;color:#a16207}.overview-empty{text-align:center!important;padding:23px!important;color:#9a8b6a!important}
@media(max-width:960px){.task-period-board{grid-template-columns:1fr;grid-template-areas:"general" "monthly" "weekly"}}
@media(max-width:720px){.team-tasks-shell{padding:18px 10px 70px}.team-paper{padding:22px 14px;transform:none}.team-head{align-items:flex-start}.group-task-overview{padding:5px;margin-bottom:24px;border-radius:15px}.overview-toggle{gap:8px;padding:10px}.overview-toggle h2{font-size:20px}.overview-toggle p{max-width:200px;line-height:1.7}.overview-toggle-end{gap:5px}.overview-toggle-end strong{padding:6px 7px}.overview-filters{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}.overview-filters button{justify-content:center;padding:0 5px;font-size:10px;gap:3px}.overview-filters b{min-width:17px;height:17px;padding:0 2px}.project-card{padding:13px}.project-card>header{display:grid;grid-template-columns:minmax(0,1fr) auto 24px;align-items:start;gap:8px;margin-bottom:10px}.project-card>header strong{grid-column:1;min-width:0;line-height:1.55;font-size:16px}.project-card>header div{display:contents}.project-card>header div span{grid-column:2;justify-self:start;align-self:center;white-space:normal;font-size:10.5px}.project-card>header div button{grid-column:3;grid-row:1;justify-self:start;align-self:start;width:24px;height:24px;border:0;background:transparent;color:#c7b896;box-shadow:none;padding:0}.team-modal-backdrop{padding:12px;align-items:start;overflow:auto}.team-modal{margin:12px 0}.todo-row{grid-template-columns:14px 26px minmax(0,1fr) 22px 22px;gap:6px;padding:8px 0}.new-task-row{display:flex;align-items:stretch}.new-task-period select{width:58px;height:34px;font-size:10px}.new-task-row input{min-width:0}.team-toast{right:12px;left:12px;text-align:center}}
</style>
