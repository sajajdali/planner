<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api';

type NoteType = 'text' | 'code';
type NoteItem = { id: number; title: string; content: string; content_type: NoteType; language: string | null };
type CodeToken = { text: string; className?: string };
type TextSegment = { type: 'text'; text: string } | { type: 'image'; url: string; alt: string; width: number | null };

const route = useRoute();
const note = ref<NoteItem | null>(null);
const loading = ref(true);
const notFound = ref(false);
const toast = ref('');

const languages = [
    ['javascript', 'JavaScript'],
    ['python', 'Python'],
    ['bash', 'Bash / ترمینال'],
    ['sql', 'SQL'],
    ['html', 'HTML'],
    ['css', 'CSS'],
    ['json', 'JSON'],
    ['php', 'PHP'],
    ['other', 'کد'],
];

function languageLabel(value?: string | null) {
    return languages.find(([key]) => key === value)?.[1] ?? 'کد';
}

function normalizeNoteImageUrl(url: string) {
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.pathname.startsWith('/storage/notebook-images/') ? parsed.pathname : '';
    } catch {
        return url.startsWith('/storage/notebook-images/') ? url : '';
    }
}

function imageMeta(rawAlt: string) {
    const widthMatch = rawAlt.match(/\|w=(\d{1,3})/);
    const width = widthMatch ? Math.min(100, Math.max(15, Number(widthMatch[1]))) : null;
    return {
        alt: rawAlt.replace(/\|[wh]=\d{1,4}/g, '') || 'تصویر یادداشت',
        width,
    };
}

function renderedTextSegments(content: string): TextSegment[] {
    const segments: TextSegment[] = [];
    const matcher = /!\[([^\]]*)]\(((?:https?:\/\/[^)\s]+)?\/storage\/notebook-images\/[^)\s]+)\)/g;
    let last = 0;
    let match: RegExpExecArray | null;

    while ((match = matcher.exec(content)) !== null) {
        if (match.index > last) segments.push({ type: 'text', text: content.slice(last, match.index) });
        const url = normalizeNoteImageUrl(match[2]);
        const meta = imageMeta(match[1] || 'تصویر یادداشت');
        if (url) segments.push({ type: 'image', alt: meta.alt, width: meta.width, url });
        last = matcher.lastIndex;
    }

    if (last < content.length) segments.push({ type: 'text', text: content.slice(last) });
    return segments.length ? segments : [{ type: 'text', text: content }];
}

function highlightedCode(content: string): CodeToken[] {
    const matcher = new RegExp(
        '(\\/\\/[^\\n]*|#[^\\n]*|\\/\\*[\\s\\S]*?\\*\\/|<!--[\\s\\S]*?-->)'
        + '|("(?:[^"\\\\]|\\\\.)*"|\\\'(?:[^\\\'\\\\]|\\\\.)*\\\'|`(?:[^`\\\\]|\\\\.)*`)'
        + '|(\\b\\d+(?:\\.\\d+)?\\b)'
        + '|(\\b(?:const|let|var|function|return|if|else|for|while|import|from|export|class|new|async|await|try|catch|throw|type|interface|public|private|protected|extends|use|namespace|Route|Schema|select|where|insert|update|delete|create|table|true|false|null)\\b)'
        + '|(<\\/?[\\w:-]+|\\b(?:script|template|style)\\b)'
        + '|([{}()[\\];,.=:+\\-*\\/<>!?|&]+)',
        'gi',
    );

    const tokens: CodeToken[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = matcher.exec(content)) !== null) {
        if (match.index > lastIndex) tokens.push({ text: content.slice(lastIndex, match.index) });
        const className = match[1] ? 'comment'
            : match[2] ? 'string'
                : match[3] ? 'number'
                    : match[4] ? 'keyword'
                        : match[5] ? 'tag'
                            : match[6] ? 'punctuation'
                                : undefined;
        tokens.push({ text: match[0], className });
        lastIndex = matcher.lastIndex;
    }

    if (lastIndex < content.length) tokens.push({ text: content.slice(lastIndex) });
    return tokens.length ? tokens : [{ text: content }];
}

async function copyContent() {
    if (!note.value) return;
    await navigator.clipboard.writeText(note.value.content);
    toast.value = 'کپی شد';
    window.setTimeout(() => {
        if (toast.value === 'کپی شد') toast.value = '';
    }, 1600);
}

async function load() {
    try {
        const { data } = await api.get(`/shared-notes/${route.params.token}`);
        note.value = data;
    } catch {
        notFound.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <main class="shared-note-page" :class="{ text: note?.content_type === 'text' }" dir="rtl">
        <header v-if="note" class="shared-note-header">
            <div>
                <strong>{{ note.title }}</strong>
                <span>{{ note.content_type === 'code' ? languageLabel(note.language) : 'متن' }}</span>
            </div>
            <button type="button" @click="copyContent">
                <svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"></rect><path d="M5 15V5a2 2 0 012-2h10"></path></svg>
                کپی
            </button>
        </header>

        <section v-if="loading" class="shared-note-state">در حال باز کردن نوت...</section>
        <section v-else-if="notFound" class="shared-note-state">این لینک پیدا نشد یا دیگر در دسترس نیست.</section>
        <pre v-else-if="note?.content_type === 'code'" class="shared-code"><code><span v-for="(token, tokenIndex) in highlightedCode(note.content)" :key="tokenIndex" :class="token.className">{{ token.text }}</span></code></pre>
        <article v-else-if="note" class="shared-text rich-text">
            <template v-if="note.content">
                <template v-for="(segment, segmentIndex) in renderedTextSegments(note.content)" :key="segmentIndex">
                    <p v-if="segment.type === 'text' && segment.text.trim()">{{ segment.text }}</p>
                    <img
                        v-else-if="segment.type === 'image'"
                        :src="segment.url"
                        :alt="segment.alt"
                        :style="segment.width ? { width: `${segment.width}%` } : undefined"
                        loading="lazy"
                    />
                </template>
            </template>
            <template v-else>بدون محتوا</template>
        </article>

        <div v-if="toast" class="shared-toast">{{ toast }}</div>
    </main>
</template>

<style scoped>
.shared-note-page{height:100vh;min-height:100vh;display:grid;grid-template-rows:auto minmax(0,1fr);overflow:hidden;background:#171321;color:#efe3c4}.shared-note-page.text{background:#fffbf0;background-image:radial-gradient(#efe3c4 1px,transparent 1px);background-size:18px 18px;color:#3a2e1f}.shared-note-header{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:2px solid rgba(255,255,255,.08);background:#211b2e;box-shadow:0 8px 22px rgba(0,0,0,.22)}.shared-note-page.text .shared-note-header{border-bottom-color:#efe3c4;background:#fff8e8}.shared-note-header>div{flex:1;min-width:0;display:flex;align-items:center;gap:10px}.shared-note-header strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff;font-size:15px}.shared-note-page.text .shared-note-header strong{color:#3a2e1f}.shared-note-header span{padding:4px 10px;border-radius:999px;background:#2d2540;color:#ffd93d;font-family:"JetBrains Mono",monospace;font-size:11px}.shared-note-page.text .shared-note-header span{background:#fff3e0;color:#8a4b1e;font-family:Vazirmatn,sans-serif}.shared-note-header button{height:36px;display:inline-flex;align-items:center;gap:7px;border:2px solid #3a2e1f;border-radius:10px;background:#22d3d0;color:#0b4a48;padding:0 14px;font-size:12px;font-weight:900;cursor:pointer;box-shadow:2px 2px 0 #3a2e1f}.shared-note-header svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}.shared-code{min-width:0;min-height:0;margin:0;padding:22px 26px;overflow:auto;direction:ltr;text-align:left;white-space:pre;color:#f5ead3;background:#171321;font-family:"JetBrains Mono",monospace;font-size:14px;line-height:1.85}.shared-code code{font-family:inherit}.shared-code span{display:inline!important;margin:0!important;padding:0!important;border-radius:0!important;background:transparent!important}.shared-code .comment{color:#8f8465;font-style:italic}.shared-code .string{color:#ffe26a}.shared-code .number{color:#ff9a92}.shared-code .keyword{color:#aea4ff;font-weight:700}.shared-code .tag{color:#4ce3e0}.shared-code .punctuation{color:#ff7eb6}.shared-text{min-width:0;min-height:0;overflow:auto;margin:0 auto;padding-top:28px!important;padding-bottom:28px!important;padding-right:30px;padding-left:30px;width:min(960px,calc(100vw - 32px));border:2px solid #3a2e1f;border-radius:18px;background:#fff;color:#3a2e1f;box-shadow:5px 5px 0 #3a2e1f;white-space:pre-wrap;font-size:17px;line-height:2.15;text-align:right}.rich-text{display:block;white-space:normal!important}.rich-text p{margin:0;white-space:pre-wrap}.rich-text img{display:block;max-width:100%;max-height:none;height:auto;object-fit:contain;margin:0;border:0;border-radius:0;background:transparent;box-shadow:none}.shared-note-state{display:grid;place-items:center;padding:32px;color:inherit;font-size:15px;font-weight:900}.shared-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%);padding:10px 18px;border-radius:999px;background:#3a2e1f;color:#fff;font-size:13px;font-weight:900;box-shadow:0 8px 20px rgba(0,0,0,.3)}@media(max-width:700px){.shared-note-header{align-items:flex-start;flex-wrap:wrap;padding:10px}.shared-note-header>div{width:100%;flex:1 0 100%}.shared-note-header button{flex:1;justify-content:center}.shared-code{padding:16px 12px;font-size:12.5px;line-height:1.8}.shared-text{width:calc(100vw - 20px);margin:0 auto;padding:18px 16px!important;font-size:15px;line-height:2}}
</style>
