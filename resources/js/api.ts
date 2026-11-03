import axios from 'axios';

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function setCsrfToken(token: string) {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    if (meta) meta.content = token;
    api.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

let lastActionElement: HTMLButtonElement | null = null;
let lastActionAt = 0;

const loadingButtons = new WeakMap<HTMLButtonElement, { count: number; disabled: boolean }>();
const deletingTargets = new WeakMap<HTMLElement, number>();

function findActionButton(target: EventTarget | null): HTMLButtonElement | null {
    if (!(target instanceof Element)) return null;

    return target.closest('button');
}

function rememberActionButton(button: HTMLButtonElement | null) {
    if (!button || button.disabled || button.classList.contains('no-request-loading')) return;

    lastActionElement = button;
    lastActionAt = Date.now();
}

function beginButtonLoading(button: HTMLButtonElement | null) {
    if (!button) return null;

    const current = loadingButtons.get(button) ?? { count: 0, disabled: button.disabled };
    current.count += 1;
    loadingButtons.set(button, current);

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.classList.add('request-loading');

    return button;
}

function endButtonLoading(button: HTMLButtonElement | null) {
    if (!button) return;

    const current = loadingButtons.get(button);
    if (!current) return;

    current.count -= 1;

    if (current.count > 0) {
        loadingButtons.set(button, current);
        return;
    }

    button.disabled = current.disabled;
    button.removeAttribute('aria-busy');
    button.classList.remove('request-loading');
    loadingButtons.delete(button);
}

function findDeletingTarget(button: HTMLButtonElement | null): HTMLElement | null {
    if (!button) return null;

    return button.closest([
        '.task-card',
        '.inline-subtask',
        '.meal-item',
        '.follow-item',
        '.expense-list article',
        '.installment-list article',
        '.debt-list article',
        '.settings-card',
        '.settings-row',
        '.ticket-card',
        '.support-card',
        '.routine-item',
        'article',
        'tr',
        'li',
    ].join(','));
}

function beginDeletingTarget(target: HTMLElement | null) {
    if (!target) return null;

    deletingTargets.set(target, (deletingTargets.get(target) ?? 0) + 1);
    target.classList.add('request-deleting');
    target.setAttribute('aria-busy', 'true');

    return target;
}

function endDeletingTarget(target: HTMLElement | null) {
    if (!target) return;

    const count = (deletingTargets.get(target) ?? 1) - 1;
    if (count > 0) {
        deletingTargets.set(target, count);
        return;
    }

    target.classList.remove('request-deleting');
    target.removeAttribute('aria-busy');
    deletingTargets.delete(target);
}

if (typeof window !== 'undefined') {
    window.addEventListener('click', (event) => {
        rememberActionButton(findActionButton(event.target));
    }, true);

    window.addEventListener('submit', (event) => {
        const submitter = event instanceof SubmitEvent ? event.submitter : null;
        rememberActionButton(submitter instanceof HTMLButtonElement ? submitter : null);
    }, true);
}

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    },
});

api.interceptors.request.use((config) => {
    config.headers['X-CSRF-TOKEN'] = csrfToken();
    const actionIsFresh = Date.now() - lastActionAt < 500;
    const button = beginButtonLoading(actionIsFresh ? lastActionElement : null);
    const isDeleteRequest = String(config.method ?? '').toLowerCase() === 'delete';
    const deletingTarget = beginDeletingTarget(isDeleteRequest ? findDeletingTarget(button) : null);

    config.headers['X-UI-Request-Loading'] = button ? 'button' : 'none';
    (config as typeof config & { requestButton?: HTMLButtonElement | null; deletingTarget?: HTMLElement | null }).requestButton = button;
    (config as typeof config & { requestButton?: HTMLButtonElement | null; deletingTarget?: HTMLElement | null }).deletingTarget = deletingTarget;

    lastActionElement = null;
    lastActionAt = 0;

    return config;
});

api.interceptors.response.use(
    (response) => {
        const config = response.config as typeof response.config & { requestButton?: HTMLButtonElement | null; deletingTarget?: HTMLElement | null };
        endButtonLoading(config.requestButton ?? null);
        endDeletingTarget(config.deletingTarget ?? null);

        return response;
    },
    async (error) => {
        const config = error.config as (typeof error.config & {
            requestButton?: HTMLButtonElement | null;
            deletingTarget?: HTMLElement | null;
            csrfRetried?: boolean;
        }) | undefined;
        endButtonLoading(config?.requestButton ?? null);
        endDeletingTarget(config?.deletingTarget ?? null);

        if (error.response?.status === 419 && config && !config.csrfRetried) {
            config.csrfRetried = true;

            const { data } = await axios.get<{ csrf_token: string }>('/api/csrf-token', {
                withCredentials: true,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            setCsrfToken(data.csrf_token);
            config.headers['X-CSRF-TOKEN'] = data.csrf_token;

            return api.request(config);
        }

        return Promise.reject(error);
    },
);

export default api;
