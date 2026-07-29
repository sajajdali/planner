import { createRouter, createWebHistory } from 'vue-router';
import AuthPage from './views/AuthPage.vue';
import DailyPlannerPage from './views/DailyPlannerPage.vue';
import FinanceManagementPage from './views/FinanceManagementPage.vue';
import GoalsPage from './views/GoalsPage.vue';
import GroupTasksPage from './views/GroupTasksPage.vue';
import LandingPage from './views/LandingPage.vue';
import MonthlyReportPage from './views/MonthlyReportPage.vue';
import NotesLibraryPage from './views/NotesLibraryPage.vue';
import ProfilePage from './views/ProfilePage.vue';
import SettingsPage from './views/SettingsPage.vue';
import SharedNotePage from './views/SharedNotePage.vue';
import SupportAdminPage from './views/SupportAdminPage.vue';

export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: LandingPage },
        { path: '/login', component: AuthPage },
        { path: '/register', component: AuthPage },
        { path: '/app', component: DailyPlannerPage },
        { path: '/goals', component: GoalsPage },
        { path: '/group-tasks', component: GroupTasksPage },
        { path: '/finance', component: FinanceManagementPage },
        { path: '/notes', component: NotesLibraryPage },
        { path: '/s/:token', component: SharedNotePage },
        { path: '/shared-notes/:token', component: SharedNotePage },
        { path: '/reports/monthly', component: MonthlyReportPage },
        { path: '/settings', component: SettingsPage },
        { path: '/profile', component: ProfilePage },
        { path: '/support/admin', component: SupportAdminPage },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});
