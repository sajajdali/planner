import { computed, ref } from 'vue';

export type AlarmSoundKey = 'auto' | 'classic' | 'bell' | 'morning' | 'soft';
export type AlarmSettings = { title: string; time: string; enabled: boolean; lastRangDate: string | null; sound: AlarmSoundKey; armedTime: string | null };

type AlarmSound = {
    key: AlarmSoundKey;
    label: string;
    icon: string;
    notes: Array<{ frequency: number; offset: number; duration: number; volume?: number; type?: OscillatorType }>;
    repeatMs: number;
};

const storageKey = 'planner-dashboard-alarm';
export const alarmSounds: AlarmSound[] = [
    {
        key: 'auto',
        label: 'انتخاب خودکار',
        icon: 'sparkles',
        notes: [],
        repeatMs: 1200,
    },
    {
        key: 'classic',
        label: 'کلاسیک آرام',
        icon: 'clock',
        repeatMs: 1160,
        notes: [
            { frequency: 784, offset: 0, duration: .16, volume: .2 },
            { frequency: 988, offset: .18, duration: .16, volume: .2 },
            { frequency: 1175, offset: .36, duration: .18, volume: .2 },
            { frequency: 988, offset: .58, duration: .18, volume: .16 },
        ],
    },
    {
        key: 'bell',
        label: 'زنگوله شفاف',
        icon: 'bell',
        repeatMs: 1480,
        notes: [
            { frequency: 1319, offset: 0, duration: .22, volume: .16 },
            { frequency: 1760, offset: .22, duration: .28, volume: .14 },
            { frequency: 1568, offset: .54, duration: .22, volume: .13 },
        ],
    },
    {
        key: 'morning',
        label: 'ملودی صبح',
        icon: 'sun',
        repeatMs: 1700,
        notes: [
            { frequency: 523, offset: 0, duration: .18, volume: .16 },
            { frequency: 659, offset: .22, duration: .18, volume: .17 },
            { frequency: 784, offset: .44, duration: .2, volume: .18 },
            { frequency: 1047, offset: .72, duration: .28, volume: .16 },
        ],
    },
    {
        key: 'soft',
        label: 'دیجیتال نرم',
        icon: 'music',
        repeatMs: 1320,
        notes: [
            { frequency: 740, offset: 0, duration: .12, volume: .13, type: 'triangle' },
            { frequency: 932, offset: .16, duration: .12, volume: .13, type: 'triangle' },
            { frequency: 740, offset: .38, duration: .12, volume: .12, type: 'triangle' },
            { frequency: 1109, offset: .54, duration: .18, volume: .12, type: 'triangle' },
        ],
    },
];

const alarm = ref<AlarmSettings>({ title: 'یادآوری مهم', time: '', enabled: false, lastRangDate: null, sound: 'morning', armedTime: null });
const alarmRinging = ref(false);
const nowTick = ref(Date.now());
let loaded = false;
let timer: number | undefined;
let audioContext: AudioContext | undefined;
let soundTimer: number | undefined;
let autoStopTimer: number | undefined;

function tehranDateString(value = new Date()) {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tehran',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(value).reduce<Record<string, string>>((carry, part) => {
        if (part.type !== 'literal') carry[part.type] = part.value;
        return carry;
    }, {});

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function minutesFromTime(time: string) {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
}

function currentMinutes() {
    const now = new Date(nowTick.value);
    return now.getHours() * 60 + now.getMinutes();
}

function loadAlarmSettings() {
    const stored = window.localStorage.getItem(storageKey);
    if (!stored) return;

    try {
        const parsed = JSON.parse(stored) as Partial<AlarmSettings>;
        alarm.value = {
            title: parsed.title?.trim() || 'یادآوری مهم',
            time: typeof parsed.time === 'string' ? parsed.time : '',
            enabled: Boolean(parsed.enabled),
            lastRangDate: typeof parsed.lastRangDate === 'string' ? parsed.lastRangDate : null,
            sound: alarmSounds.some((sound) => sound.key === parsed.sound) ? parsed.sound as AlarmSoundKey : 'morning',
            armedTime: typeof parsed.armedTime === 'string' ? parsed.armedTime : (parsed.enabled && typeof parsed.time === 'string' ? parsed.time : null),
        };
    } catch {
        window.localStorage.removeItem(storageKey);
    }
}

function saveAlarmSettings() {
    window.localStorage.setItem(storageKey, JSON.stringify(alarm.value));
}

async function unlockAlarmAudio() {
    const AudioContextConstructor = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AudioContextConstructor) return;
    audioContext ||= new AudioContextConstructor();
    if (audioContext.state === 'suspended') await audioContext.resume();
}

function selectedAlarmSound() {
    if (alarm.value.sound && alarm.value.sound !== 'auto') {
        return alarmSounds.find((sound) => sound.key === alarm.value.sound) ?? alarmSounds[1];
    }

    const pool = alarmSounds.filter((sound) => sound.key !== 'auto');
    const seed = `${alarm.value.time}-${alarm.value.title}-${tehranDateString(new Date(nowTick.value))}`;
    const index = [...seed].reduce((sum, char) => sum + char.charCodeAt(0), 0) % pool.length;
    return pool[index] ?? alarmSounds[1];
}

function playAlarmChime(sound = selectedAlarmSound()) {
    if (!audioContext) return;
    sound.notes.forEach((note) => {
        const oscillator = audioContext!.createOscillator();
        const gain = audioContext!.createGain();
        const start = audioContext!.currentTime + note.offset;
        oscillator.type = note.type ?? 'sine';
        oscillator.frequency.setValueAtTime(note.frequency, start);
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(note.volume ?? .16, start + .025);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + note.duration);
        oscillator.connect(gain);
        gain.connect(audioContext!.destination);
        oscillator.start(start);
        oscillator.stop(start + note.duration + .03);
    });
}

function stopAlarmSound(closeModal = true) {
    if (soundTimer) window.clearInterval(soundTimer);
    if (autoStopTimer) window.clearTimeout(autoStopTimer);
    soundTimer = undefined;
    autoStopTimer = undefined;
    if (closeModal) {
        alarmRinging.value = false;
        alarm.value.enabled = false;
        saveAlarmSettings();
    }
}

function startAlarmSound() {
    stopAlarmSound(false);
    const sound = selectedAlarmSound();
    playAlarmChime(sound);
    soundTimer = window.setInterval(() => playAlarmChime(sound), sound.repeatMs);
    autoStopTimer = window.setTimeout(() => stopAlarmSound(false), 20000);
}

function resetAlarmRingDateForSelectedTime() {
    if (!alarm.value.time) {
        alarm.value.lastRangDate = null;
        alarm.value.armedTime = null;
        return;
    }
    const today = tehranDateString(new Date(nowTick.value));
    alarm.value.lastRangDate = minutesFromTime(alarm.value.time) <= currentMinutes() ? today : null;
}

function checkDashboardAlarm() {
    if (!alarm.value.enabled || !alarm.value.time) return;
    if (alarm.value.armedTime !== alarm.value.time) return;
    const today = tehranDateString(new Date(nowTick.value));
    if (alarm.value.lastRangDate === today) return;
    if (minutesFromTime(alarm.value.time) > currentMinutes()) return;

    alarm.value.lastRangDate = today;
    alarmRinging.value = true;
    saveAlarmSettings();
    void unlockAlarmAudio().then(startAlarmSound);
}

function startGlobalAlarmWatcher() {
    if (loaded) return;
    loaded = true;
    loadAlarmSettings();
    timer = window.setInterval(() => {
        nowTick.value = Date.now();
        checkDashboardAlarm();
    }, 1000);
    window.addEventListener('storage', loadAlarmSettings);
    checkDashboardAlarm();
}

function stopGlobalAlarmWatcher() {
    if (timer) window.clearInterval(timer);
    window.removeEventListener('storage', loadAlarmSettings);
    timer = undefined;
    loaded = false;
    stopAlarmSound();
}

function durationCountdown(totalSeconds: number) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    if (hours > 0) return `${hours} ساعت و ${minutes} دقیقه`;
    if (minutes > 0) return `${minutes} دقیقه و ${seconds} ثانیه`;
    return `${seconds} ثانیه`;
}

const alarmTarget = computed(() => {
    if (!alarm.value.time) return null;
    if (alarm.value.armedTime !== alarm.value.time) return null;
    const [hour, minute] = alarm.value.time.split(':').map(Number);
    if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null;

    const target = new Date(nowTick.value);
    target.setHours(hour, minute, 0, 0);
    if (target.getTime() <= nowTick.value && (!alarm.value.enabled || alarm.value.lastRangDate === tehranDateString(target))) {
        target.setDate(target.getDate() + 1);
    }
    return target;
});

const alarmRemainingSeconds = computed(() => {
    if (!alarm.value.enabled || !alarmTarget.value) return 0;
    return Math.max(0, Math.ceil((alarmTarget.value.getTime() - nowTick.value) / 1000));
});

export function useDashboardAlarm() {
    return {
        alarm,
        alarmRinging,
        alarmRemainingSeconds,
        durationCountdown,
        resetAlarmRingDateForSelectedTime,
        saveAlarmSettings,
        startGlobalAlarmWatcher,
        stopGlobalAlarmWatcher,
        stopAlarmSound,
        unlockAlarmAudio,
        playAlarmChime,
        alarmSounds,
    };
}
