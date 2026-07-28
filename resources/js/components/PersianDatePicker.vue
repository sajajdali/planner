<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { isValidJalaaliDate, jalaaliMonthLength, toGregorian, toJalaali } from 'jalaali-js';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const panelStyle = ref<Record<string, string>>({});
const year = ref(0);
const month = ref(0);

const monthName = computed(() => ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'][month.value - 1] ?? '');
const days = computed(() => year.value && month.value ? Array.from({ length: jalaaliMonthLength(year.value, month.value) }, (_, index) => index + 1) : []);
const displayValue = computed(() => props.modelValue ? isoToJalali(props.modelValue) : '');

watch(() => props.modelValue, syncMonth, { immediate: true });
watch(open, async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    positionPanel();
});

function fa(value: string | number) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
}

function en(value: string) {
    return value.replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)));
}

function syncMonth() {
    const target = props.modelValue || new Date().toISOString().slice(0, 10);
    const [gy, gm, gd] = target.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    year.value = j.jy;
    month.value = j.jm;
}

function isoToJalali(value: string) {
    const [gy, gm, gd] = value.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    return fa(`${j.jy}/${String(j.jm).padStart(2, '0')}/${String(j.jd).padStart(2, '0')}`);
}

function selectDay(day: number) {
    if (!isValidJalaaliDate(year.value, month.value, day)) return;
    const g = toGregorian(year.value, month.value, day);
    emit('update:modelValue', `${g.gy}-${String(g.gm).padStart(2, '0')}-${String(g.gd).padStart(2, '0')}`);
    open.value = false;
}

function changeMonth(delta: number) {
    let nextMonth = month.value + delta;
    let nextYear = year.value;
    if (nextMonth < 1) {
        nextMonth = 12;
        nextYear -= 1;
    }
    if (nextMonth > 12) {
        nextMonth = 1;
        nextYear += 1;
    }
    year.value = nextYear;
    month.value = nextMonth;
}

function selected(day: number) {
    if (!props.modelValue) return false;
    const [gy, gm, gd] = props.modelValue.split('-').map(Number);
    const j = toJalaali(gy, gm, gd);
    return j.jy === year.value && j.jm === month.value && j.jd === day;
}

function closeOutside(event: MouseEvent) {
    if (!open.value) return;
    const target = event.target;
    if (target instanceof Node && root.value?.contains(target)) return;
    if (target instanceof Node && panel.value?.contains(target)) return;
    open.value = false;
}

function toggle() {
    open.value = !open.value;
}

function positionPanel() {
    const trigger = root.value?.querySelector('.persian-date-input');
    if (!(trigger instanceof HTMLElement)) return;
    const rect = trigger.getBoundingClientRect();
    const panelWidth = Math.min(284, window.innerWidth - 24);
    const panelHeight = panel.value?.offsetHeight || 320;
    const gap = 8;
    const spaceBelow = window.innerHeight - rect.bottom;
    const top = spaceBelow >= panelHeight + gap
        ? rect.bottom + gap
        : Math.max(12, rect.top - panelHeight - gap);
    const left = Math.min(
        window.innerWidth - panelWidth - 12,
        Math.max(12, rect.right - panelWidth),
    );

    panelStyle.value = {
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        width: `${panelWidth}px`,
        zIndex: '12000',
    };
}

onMounted(() => {
    document.addEventListener('click', closeOutside, true);
    window.addEventListener('resize', positionPanel);
    window.addEventListener('scroll', positionPanel, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeOutside, true);
    window.removeEventListener('resize', positionPanel);
    window.removeEventListener('scroll', positionPanel, true);
});
</script>

<template>
    <div ref="root" class="persian-date-picker">
        <button type="button" class="persian-date-input" @click="toggle">
            <span :class="{ muted: !displayValue }">{{ displayValue || placeholder || 'انتخاب تاریخ' }}</span>
            <svg viewBox="0 0 24 24"><path d="M7 3v4M17 3v4M4 9h16M5 5h14v16H5z"></path></svg>
        </button>
        <Teleport to="body">
            <div v-if="open" ref="panel" class="jalali-calendar jalali-calendar-floating" :style="panelStyle">
                <div class="jalali-calendar-head">
                    <button type="button" title="ماه قبل" @click="changeMonth(-1)">‹</button>
                    <strong>{{ monthName }} {{ fa(year) }}</strong>
                    <button type="button" title="ماه بعد" @click="changeMonth(1)">›</button>
                </div>
                <div class="jalali-weekdays">
                    <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span>
                </div>
                <div class="jalali-days">
                    <button v-for="day in days" :key="day" type="button" :class="{ selected: selected(day) }" @click="selectDay(day)">
                        {{ fa(day) }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
