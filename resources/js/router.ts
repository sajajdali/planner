import { createRouter, createWebHistory } from 'vue-router';
import AuthPage from './views/AuthPage.vue';
import DailyPlannerPage from './views/DailyPlannerPage.vue';
import FinanceManagementPage from './views/FinanceManagementPage.vue';
import LandingPage from './views/LandingPage.vue';
import MonthlyReportPage from './views/MonthlyReportPage.vue';
import ProfilePage from './views/ProfilePage.vue';
import SettingsPage from './views/SettingsPage.vue';

export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: LandingPage },
        { path: '/login', component: AuthPage },
        { path: '/register', component: AuthPage },
        { path: '/app', component: DailyPlannerPage },
        { path: '/finance', component: FinanceManagementPage },
        { path: '/reports/monthly', component: MonthlyReportPage },
        { path: '/settings', component: SettingsPage },
        { path: '/profile', component: ProfilePage },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});
