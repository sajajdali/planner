<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import AppMenu from '../components/AppMenu.vue';

type GroupTaskItem = { id: number; title: string; is_done: boolean; sort_order: number };
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
type GroupOption = { id: number; category_id: number; name: string; color: string; soft_color: string; already_added: boolean };
type Section = { id: number; name: string; color: string; soft_color: string; groups: GroupOption[]; projects: GroupTaskProject[] };

const loading = ref(true);
const router = useRouter();
const error = ref('');
const sections = ref<Section[]>([]);
const draftTasks = ref<Record<number, string>>({});
const addProjectSection = ref<Section | null>(null);
const deleteProjectTarget = ref<GroupTaskProject | null>(null);
const draggedItem = ref<{ projectId: number; itemId: number } | null>(null);
const tilts = ['rotate(-0.4deg)', 'rotate(0.3deg)', 'rotate(-0.2deg)'];

const hasAnyProject = computed(() => sections.value.some((section) => section.projects.length));

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
            groups: section.groups.map((group) => group.id === project.task_group_id ? { ...group, already_added: false } : group),
            projects: section.projects.filter((item) => item.id !== project.id),
        }
        : section);
    deleteProjectTarget.value = null;
}

async function addTask(project: GroupTaskProject) {
    const title = (draftTasks.value[project.id] || '').trim();
    if (!title) return;
    const { data } = await api.post(`/group-task-projects/${project.id}/items`, { title });
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
    const [moved] = items.splice(from, 1);
    items.splice(to, 0, moved);
    updateProject({ ...project, items });

    const { data } = await api.post(`/group-task-projects/${project.id}/items/reorder`, { item_ids: items.map((item) => item.id) });
    updateProject(data);
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
                <section v-for="section in sections" :key="section.id" class="team-section" :style="{ '--c': section.color }">
                    <header>
                        <span></span>
                        <h2>{{ section.name }}</h2>
                    </header>
                    <button class="add-project-btn" type="button" @click="addProjectSection = section">＋ اضافه کردن پروژه</button>

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

                            <div class="todo-list">
                                <div
                                    v-for="item in project.items"
                                    :key="item.id"
                                    class="todo-row"
                                    draggable="true"
                                    @dragstart="draggedItem = { projectId: project.id, itemId: item.id }"
                                    @dragover.prevent
                                    @drop="dropTask(project, item)"
                                    @dragend="draggedItem = null"
                                >
                                    <svg viewBox="0 0 24 24"><path d="M8 6h8M8 12h8M8 18h8"></path></svg>
                                    <button type="button" :class="{ done: item.is_done }" :style="{ '--g': project.color }" @click="toggleTask(project, item)">
                                        <svg v-if="item.is_done" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                    <span :class="{ done: item.is_done }">{{ item.title }}</span>
                                    <button class="delete-task" type="button" @click="deleteTask(project, item)">×</button>
                                </div>
                                <div v-if="!project.items.length" class="no-tasks">کاری ثبت نشده</div>
                            </div>

                            <div class="new-task-row">
                                <input
                                    v-model="draftTasks[project.id]"
                                    placeholder="یک کار جدید بنویس..."
                                    @keydown.enter.prevent="addTask(project)"
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
                    <div v-else-if="addProjectSection.groups.every((group) => group.already_added)" class="modal-empty">همه پروژه‌های تعریف‌شده برای این گروه اضافه شده‌اند. برای افزودن پروژه جدید به تنظیمات مراجعه کن.</div>
                </div>
                <footer><button type="button" @click="addProjectSection = null">انصراف</button></footer>
            </section>
        </div>

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
@media(max-width:720px){.team-tasks-shell{padding:18px 10px 70px}.team-paper{padding:22px 14px;transform:none}.team-head{align-items:flex-start}.project-card{padding:13px}.project-card>header{display:grid;grid-template-columns:minmax(0,1fr) auto 24px;align-items:start;gap:8px;margin-bottom:12px}.project-card>header strong{grid-column:1;min-width:0;line-height:1.55}.project-card>header div{display:contents}.project-card>header div span{grid-column:2;justify-self:end;align-self:center;white-space:nowrap}.project-card>header div button{grid-column:3;justify-self:start;align-self:start;width:24px;height:24px;border:0;background:transparent;color:#c7b896;box-shadow:none;padding:0}.team-modal-backdrop{padding:12px;align-items:start;overflow:auto}.team-modal{margin:12px 0}.todo-row{gap:6px}.new-task-row input{min-width:0}}
</style>
