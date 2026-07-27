import { defineStore } from 'pinia';
import api from '../api';

type User = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    profile_emoji?: string | null;
    avatar_url?: string | null;
};

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as User | null,
        checked: false,
        loading: false,
    }),
    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/user');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.checked = true;
            }
        },
        async login(email: string, password: string) {
            this.loading = true;
            try {
                const { data } = await api.post('/login', { email, password });
                this.user = data.user;
            } finally {
                this.loading = false;
            }
        },
        async register(name: string, email: string, password: string) {
            this.loading = true;
            try {
                const { data } = await api.post('/register', { name, email, password });
                this.user = data.user;
            } finally {
                this.loading = false;
            }
        },
        async sendPhoneCode(phone: string, mode: 'login' | 'register') {
            this.loading = true;
            try {
                const { data } = await api.post('/phone-code', { phone, mode });

                return data as { sent: boolean; expires_in: number; sandbox_code: string | null; sms_error?: boolean };
            } finally {
                this.loading = false;
            }
        },
        async phoneLogin(phone: string, code: string) {
            this.loading = true;
            try {
                const { data } = await api.post('/phone-login', { phone, code });
                this.user = data.user;
            } finally {
                this.loading = false;
            }
        },
        async phoneRegister(payload: { phone: string; code: string; name: string; city?: string; education?: string; job?: string }) {
            this.loading = true;
            try {
                const { data } = await api.post('/phone-register', payload);
                this.user = data.user;
            } finally {
                this.loading = false;
            }
        },
        async updateProfile(payload: { name: string; profile_emoji: string; avatar?: File | null }) {
            this.loading = true;
            try {
                const form = new FormData();
                form.append('name', payload.name);
                form.append('profile_emoji', payload.profile_emoji);
                if (payload.avatar) form.append('avatar', payload.avatar);

                const { data } = await api.post('/profile', form);
                this.user = data.user;
            } finally {
                this.loading = false;
            }
        },
        async logout() {
            await api.post('/logout');
            this.user = null;
        },
    },
});
