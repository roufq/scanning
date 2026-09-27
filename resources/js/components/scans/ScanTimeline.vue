<script setup lang="ts">
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { activityColors, formatDate, formatPercent } from '@/lib/scans';
import type { FindingItem, ScanLabels, ScreenshotItem } from '@/types';

const props = defineProps<{
    screenshots: ScreenshotItem[];
    gaps: FindingItem[];
    labels: ScanLabels;
}>();

const emit = defineEmits<{
    select: [screenshot: ScreenshotItem];
}>();

type Row = {
    key: string;
    date: string;
    hour: string;
    cells: { screenshot: ScreenshotItem; left: number }[];
    gaps: { left: number; width: number; minutes: number }[];
};

function minuteOfHour(dateTime: string): number {
    return Number(dateTime.slice(14, 16)) + Number(dateTime.slice(17, 19)) / 60;
}

function hourKey(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}`;
}

const rows = computed<Row[]>(() => {
    const byHour = new Map<string, Row>();

    const rowFor = (key: string): Row => {
        if (!byHour.has(key)) {
            byHour.set(key, {
                key,
                date: key.slice(0, 10),
                hour: `${key.slice(11, 13)}:00`,
                cells: [],
                gaps: [],
            });
        }

        return byHour.get(key)!;
    };

    for (const screenshot of props.screenshots) {
        if (screenshot.takenAt) {
            rowFor(screenshot.takenAt.slice(0, 13)).cells.push({
                screenshot,
                left: (minuteOfHour(screenshot.takenAt) / 60) * 100,
            });
        }
    }

    // Split each gap into per-hour segments so it can be drawn across rows.
    for (const gap of props.gaps) {
        const from = new Date(String(gap.details?.from).replace(' ', 'T'));
        const to = new Date(String(gap.details?.to).replace(' ', 'T'));
        const cursor = new Date(from);

        while (cursor < to) {
            const hourEnd = new Date(cursor);
            hourEnd.setMinutes(60, 0, 0);
            const end = hourEnd < to ? hourEnd : to;
            const startMinute = cursor.getMinutes() + cursor.getSeconds() / 60;
            const length = (end.getTime() - cursor.getTime()) / 60000;

            rowFor(hourKey(cursor)).gaps.push({
                left: (startMinute / 60) * 100,
                width: (length / 60) * 100,
                minutes: Number(gap.details?.minutes ?? 0),
            });

            cursor.setTime(end.getTime());
        }
    }

    return [...byHour.values()].sort((a, b) => a.key.localeCompare(b.key));
});

const undated = computed(() =>
    props.screenshots.filter((screenshot) => !screenshot.takenAt),
);
</script>

<template>
    <TooltipProvider :delay-duration="100">
        <div class="flex flex-col gap-1">
            <div
                class="ml-24 flex justify-between text-xs text-muted-foreground"
            >
                <span>:00</span><span>:15</span><span>:30</span><span>:45</span
                ><span>:60</span>
            </div>

            <template v-for="(row, index) in rows" :key="row.key">
                <div
                    v-if="index === 0 || rows[index - 1].date !== row.date"
                    class="mt-2 text-xs font-medium text-muted-foreground"
                >
                    {{ formatDate(row.date) }}
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="w-22 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                    >
                        {{ row.hour }}
                    </span>
                    <div class="relative h-7 flex-1 rounded bg-muted/60">
                        <div
                            v-for="(gap, gapIndex) in row.gaps"
                            :key="`gap-${gapIndex}`"
                            class="absolute inset-y-0 bg-[repeating-linear-gradient(45deg,transparent_0_4px,rgb(113_113_122/0.35)_4px_8px)]"
                            :style="{
                                left: `${gap.left}%`,
                                width: `${gap.width}%`,
                            }"
                            :title="`Tidak ada screenshot selama ${gap.minutes} menit`"
                        />
                        <Tooltip
                            v-for="cell in row.cells"
                            :key="cell.screenshot.id"
                        >
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="absolute inset-y-1 w-2 -translate-x-1/2 rounded-sm ring-offset-1 hover:ring-2 hover:ring-foreground focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none"
                                    :class="
                                        activityColors[cell.screenshot.activity]
                                    "
                                    :style="{
                                        left: `${Math.min(cell.left, 99)}%`,
                                    }"
                                    :aria-label="`${cell.screenshot.takenAt} ${labels.activity[cell.screenshot.activity]}`"
                                    @click="emit('select', cell.screenshot)"
                                />
                            </TooltipTrigger>
                            <TooltipContent>
                                <p class="font-medium">
                                    {{ cell.screenshot.takenAt?.slice(11) }}
                                </p>
                                <p>
                                    {{
                                        labels.activity[
                                            cell.screenshot.activity
                                        ]
                                    }}
                                </p>
                                <p v-if="cell.screenshot.changeRatio !== null">
                                    Perubahan layar
                                    {{
                                        formatPercent(
                                            cell.screenshot.changeRatio,
                                        )
                                    }}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>
            </template>

            <p
                v-if="rows.length === 0"
                class="py-6 text-center text-sm text-muted-foreground"
            >
                Tidak ada screenshot dengan informasi waktu.
            </p>

            <p
                v-if="undated.length > 0"
                class="mt-3 text-xs text-muted-foreground"
            >
                {{ undated.length }} screenshot tidak memiliki informasi waktu
                dan tidak tampil di timeline (lihat tab "Semua gambar").
            </p>
        </div>
    </TooltipProvider>
</template>
