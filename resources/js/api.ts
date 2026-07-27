import axios from 'axios';

let lastActionElement: HTMLButtonElement | null = null;
let lastActionAt = 0;

const loadingButtons = new WeakMap<HTMLButtonElement, { count: number; disabled: boolean }>();

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
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
    },
});

api.interceptors.request.use((config) => {
    const actionIsFresh = Date.now() - lastActionAt < 500;
    const button = beginButtonLoading(actionIsFresh ? lastActionElement : null);

    config.headers['X-UI-Request-Loading'] = button ? 'button' : 'none';
    (config as typeof config & { requestButton?: HTMLButtonElement | null }).requestButton = button;

    lastActionElement = null;
    lastActionAt = 0;

    return config;
});

api.interceptors.response.use(
    (response) => {
        endButtonLoading((response.config as typeof response.config & { requestButton?: HTMLButtonElement | null }).requestButton ?? null);

        return response;
    },
    (error) => {
        endButtonLoading((error.config as typeof error.config & { requestButton?: HTMLButtonElement | null }).requestButton ?? null);

        return Promise.reject(error);
    },
);

export default api;
