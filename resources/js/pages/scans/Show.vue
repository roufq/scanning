<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    FileSpreadsheet,
    FileText,
    LoaderCircle,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ScanTimeline from '@/components/scans/ScanTimeline.vue';
import ScreenshotCompareDialog from '@/components/scans/ScreenshotCompareDialog.vue';
import type { ComparedImage } from '@/components/scans/ScreenshotCompareDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    activityBorders,
    activityColors,
    exportUrl,
    formatDate,
    formatPercent,
    formatTime,
    screenshotUrl,
    statusVariants,
} from '@/lib/scans';
import { destroy, index, show } from '@/routes/scans';
import { store as startAnalysis } from '@/routes/scans/analysis';
import type {
    Box,
    FindingItem,
    FindingType,
    ScanDetail,
    ScanLabels,
    ScreenshotActivity,
    ScreenshotItem,
    Team,
} from '@/types';

const props = defineProps<{
    scan: ScanDetail;
    screenshots: ScreenshotItem[];
    findings: FindingItem[];
    labels: ScanLabels;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null; scan?: ScanDetail }) => ({
        breadcrumbs: [
            {
                title: 'Scan Screenshot',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: props.scan?.employee ?? 'Hasil scan',
                href:
                    props.currentTeam && props.scan
                        ? show({
                              current_team: props.currentTeam.slug,
                              scan: props.scan.id,
                          })
                        : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

// Refresh while the queue is working, stop as soon as the analysis is done.
const { start, stop } = usePoll(
    2000,
    { only: ['scan', 'screenshots', 'findings'] },
    { autoStart: props.scan.isRunning },
);
watch(
    () => props.scan.isRunning,
    (running) => (running ? start() : stop()),
);

const FINDING_TYPES: FindingType[] = [
    'exact_duplicate',
    'recycled',
    'idle',
    'mouse_jiggler',
    'clock_mismatch',
    'non_work',
    'time_gap',
];
const ACTIVITIES: ScreenshotActivity[] = [
    'active',
    'low',
    'idle',
    'cursor_only',
    'duplicate',
    'unknown',
];

const screenshotsById = computed(
    () =>
        new Map(
            props.screenshots.map((screenshot) => [screenshot.id, screenshot]),
        ),
);
const positionById = computed(
    () =>
        new Map(
            props.screenshots.map((screenshot, position) => [
                screenshot.id,
                position,
            ]),
        ),
);

const activityCounts = computed(() => {
    const counts = Object.fromEntries(
        ACTIVITIES.map((activity) => [activity, 0]),
    ) as Record<ScreenshotActivity, number>;
    props.screenshots.forEach((screenshot) => counts[screenshot.activity]++);

    return counts;
});

const findingCounts = computed(() => {
    const counts = Object.fromEntries(
        FINDING_TYPES.map((type) => [type, 0]),
    ) as Record<FindingType, number>;
    props.findings.forEach((finding) => counts[finding.type]++);

    return counts;
});

const gapFindings = computed(() =>
    props.findings.filter((finding) => finding.type === 'time_gap'),
);
const gapMinutes = computed(() =>
    gapFindings.value.reduce(
        (sum, finding) => sum + Number(finding.details?.minutes ?? 0),
        0,
    ),
);

const activeShare = computed(() => {
    const counts = activityCounts.value;
    const measured = props.screenshots.length - counts.unknown;

    return measured === 0 ? null : (counts.active + counts.low) / measured;
});

const summaryCards = computed(() => {
    const counts = findingCounts.value;
    const alertCard = (label: string, value: number) => ({
        label,
        value,
        alert: value > 0,
        suffix: '',
    });

    return [
        {
            label: 'Total screenshot',
            value: props.screenshots.length,
            alert: false,
            suffix: '',
        },
        {
            label: 'Layar berubah (aktif)',
            value: formatPercent(activeShare.value),
            alert: false,
            suffix: '',
        },
        alertCard('Layar diam', counts.idle),
        alertCard('Hanya kursor bergerak', counts.mouse_jiggler),
        alertCard('File identik', counts.exact_duplicate),
        alertCard('Daur ulang (scan lama)', counts.recycled),
        alertCard('Jam tidak cocok', counts.clock_mismatch),
        alertCard('Aplikasi non-kerja', counts.non_work),
        {
            label: 'Celah waktu',
            value: counts.time_gap,
            alert: false,
            suffix: `(${gapMinutes.value} mnt)`,
        },
    ];
});

const categoryBreakdown = computed(() => {
    const byName = new Map<
        string,
        { name: string; isProductive: boolean; count: number }
    >();

    for (const screenshot of props.screenshots) {
        if (screenshot.category) {
            const entry = byName.get(screenshot.category.name) ?? {
                name: screenshot.category.name,
                isProductive: screenshot.category.isProductive,
                count: 0,
            };
            entry.count++;
            byName.set(entry.name, entry);
        }
    }

    const rows = [...byName.values()].sort((a, b) => b.count - a.count);
    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const productive = rows
        .filter((row) => row.isProductive)
        .reduce((sum, row) => sum + row.count, 0);

    return { rows, total, productiveShare: total ? productive / total : null };
});

function describeCategory(screenshot: ScreenshotItem): string {
    const category = screenshot.category;

    if (!category) {
        return '';
    }

    return category.source === 'keyword'
        ? ` Kategori: ${category.name} (kata kunci "${category.keyword}").`
        : ` Kategori: ${category.name} (AI ${formatPercent(category.confidence)}).`;
}

const timeRange = computed(() => {
    const times = props.screenshots
        .map((screenshot) => screenshot.takenAt)
        .filter((time): time is string => time !== null);

    return times.length
        ? { from: times[0], to: times[times.length - 1] }
        : null;
});

const groups = computed(() => {
    const byGroup = new Map<number, ScreenshotItem[]>();

    for (const screenshot of props.screenshots) {
        if (screenshot.similarityGroup !== null) {
            byGroup.set(screenshot.similarityGroup, [
                ...(byGroup.get(screenshot.similarityGroup) ?? []),
                screenshot,
            ]);
        }
    }

    return [...byGroup.values()].sort((a, b) => b.length - a.length);
});

const tab = ref<'findings' | 'groups' | 'all'>('findings');
const findingFilter = ref<FindingType | null>(null);
const activityFilter = ref<ScreenshotActivity | null>(null);

const visibleFindings = computed(() =>
    findingFilter.value
        ? props.findings.filter(
              (finding) => finding.type === findingFilter.value,
          )
        : props.findings,
);
const visibleScreenshots = computed(() =>
    activityFilter.value
        ? props.screenshots.filter(
              (screenshot) => screenshot.activity === activityFilter.value,
          )
        : props.screenshots,
);

// Comparison dialog
const dialogOpen = ref(false);
const dialogTitle = ref('');
const dialogDescription = ref('');
const dialogCurrent = ref<ComparedImage | null>(null);
const dialogComparison = ref<ComparedImage | null>(null);
const dialogBoxes = ref<Box[]>([]);

function changeBoxes(screenshot: ScreenshotItem): Box[] {
    if (screenshot.changeRegions.length > 0) {
        return screenshot.changeRegions;
    }

    return screenshot.changeBbox ? [screenshot.changeBbox] : [];
}

function asImage(screenshot: ScreenshotItem, caption: string): ComparedImage {
    return {
        scanId: props.scan.id,
        id: screenshot.id,
        name: screenshot.name,
        caption,
    };
}

function openScreenshot(screenshot: ScreenshotItem) {
    const finding =
        props.findings.find(
            (item) =>
                item.screenshotId === screenshot.id &&
                item.related &&
                item.type !== 'time_gap',
        ) ?? null;

    if (finding) {
        openFinding(finding);

        return;
    }

    const previous =
        props.screenshots[(positionById.value.get(screenshot.id) ?? 0) - 1] ??
        null;

    dialogTitle.value = `${formatTime(screenshot.takenAt)} · ${props.labels.activity[screenshot.activity]}`;
    dialogDescription.value =
        screenshot.changeRatio === null
            ? 'Tidak ada screenshot sebelumnya untuk dibandingkan.'
            : `Perubahan layar ${formatPercent(screenshot.changeRatio)} dibanding screenshot sebelumnya.`;
    if (screenshot.screenClock) {
        dialogDescription.value += ` Jam di layar: ${screenshot.screenClock}.`;
    }

    dialogDescription.value += describeCategory(screenshot);

    dialogCurrent.value = asImage(
        screenshot,
        `Screenshot ${formatTime(screenshot.takenAt)}`,
    );
    dialogComparison.value = previous
        ? asImage(previous, `Sebelumnya ${formatTime(previous.takenAt)}`)
        : null;
    dialogBoxes.value = changeBoxes(screenshot);
    dialogOpen.value = true;
}

function openFinding(finding: FindingItem) {
    const screenshot = screenshotsById.value.get(finding.screenshotId);

    if (!screenshot) {
        return;
    }

    const related = finding.related;

    dialogTitle.value = props.labels.finding[finding.type];
    dialogDescription.value = finding.description;
    dialogCurrent.value = asImage(
        screenshot,
        `Screenshot ${formatTime(screenshot.takenAt)}`,
    );
    dialogComparison.value = related
        ? {
              scanId: related.scanId,
              id: related.id,
              name: related.name,
              caption:
                  related.scanId === props.scan.id
                      ? `Pembanding ${formatTime(related.takenAt)}`
                      : `Dari scan ${formatDate(related.scanDate)} · ${formatTime(related.takenAt)}`,
          }
        : null;
    dialogBoxes.value =
        finding.type === 'idle' || finding.type === 'mouse_jiggler'
            ? changeBoxes(screenshot)
            : [];
    dialogOpen.value = true;
}

function rerun() {
    router.post(
        startAnalysis.url({
            current_team: teamSlug.value,
            scan: props.scan.id,
        }),
    );
}

function remove() {
    if (
        confirm(
            `Hapus scan ${props.scan.employee} beserta semua screenshot-nya?`,
        )
    ) {
        router.delete(
            destroy.url({ current_team: teamSlug.value, scan: props.scan.id }),
        );
    }
}
</script>

<template>
    <Head :title="`Scan ${scan.employee}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ scan.employee }}
                    </h1>
                    <Badge :variant="statusVariants[scan.status]">{{
                        scan.statusLabel
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{
                        scan.workDate
                            ? formatDate(scan.workDate)
                            : 'Tanggal kerja tidak diisi'
                    }}
                    · {{ scan.screenshotsCount }} screenshot
                    <template v-if="timeRange">
                        · {{ timeRange.from.slice(0, 16) }} –
                        {{ timeRange.to.slice(11, 16) }}
                    </template>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-if="scan.status === 'completed'">
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="exportUrl(teamSlug, scan.id, 'xlsx')"
                            download
                        >
                            <FileSpreadsheet /> Excel
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="exportUrl(teamSlug, scan.id, 'pdf')" download>
                            <FileText /> PDF
                        </a>
                    </Button>
                </template>
                <Button
                    v-if="!scan.isRunning"
                    variant="outline"
                    size="sm"
                    @click="rerun"
                >
                    <RefreshCw />
                    {{
                        scan.status === 'uploading'
                            ? 'Mulai analisis'
                            : 'Analisis ulang'
                    }}
                </Button>
                <Button
                    v-if="!scan.isRunning"
                    variant="ghost"
                    size="sm"
                    @click="remove"
                >
                    <Trash2 /> Hapus
                </Button>
            </div>
        </div>

        <!-- Running -->
        <div
            v-if="scan.isRunning"
            class="flex flex-col gap-3 rounded-xl border p-6"
        >
            <div class="flex items-center gap-2 font-medium">
                <LoaderCircle class="size-4 animate-spin" />
                {{
                    scan.processedCount < scan.screenshotsCount
                        ? 'Membaca screenshot…'
                        : 'Membandingkan screenshot…'
                }}
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full bg-primary transition-all"
                    :style="{
                        width: `${scan.screenshotsCount ? (scan.processedCount / scan.screenshotsCount) * 100 : 0}%`,
                    }"
                />
            </div>
            <p class="text-sm text-muted-foreground tabular-nums">
                {{ scan.processedCount }} /
                {{ scan.screenshotsCount }} screenshot diproses. Halaman ini
                diperbarui otomatis.
            </p>
        </div>

        <!-- Failed / not started -->
        <div
            v-else-if="scan.status === 'failed' || scan.status === 'uploading'"
            class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">
                    {{
                        scan.status === 'failed'
                            ? 'Analisis gagal'
                            : 'Analisis belum dimulai'
                    }}
                </p>
                <p>
                    {{
                        scan.error ??
                        'Unggahan mungkin terputus. Klik "Mulai analisis" untuk menganalisis screenshot yang sudah terunggah.'
                    }}
                </p>
                <p v-if="scan.status === 'failed'" class="mt-1">
                    Pastikan layanan analyzer Python berjalan (<code
                        >php artisan analyzer:serve</code
                    >), lalu klik "Analisis ulang".
                </p>
            </div>
        </div>

        <!-- Completed -->
        <template v-if="scan.status === 'completed'">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-xl border p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p
                        class="text-2xl font-semibold tabular-nums"
                        :class="
                            card.alert ? 'text-red-600 dark:text-red-400' : ''
                        "
                    >
                        {{ card.value }}
                        <span
                            v-if="card.suffix"
                            class="text-sm font-normal text-muted-foreground"
                        >
                            {{ card.suffix }}
                        </span>
                    </p>
                </div>
            </div>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-medium">Timeline</h2>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span
                            v-for="activity in ACTIVITIES"
                            :key="activity"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="size-3 rounded-sm"
                                :class="activityColors[activity]"
                            />
                            {{ labels.activity[activity] }} ({{
                                activityCounts[activity]
                            }})
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span
                                class="size-3 rounded-sm bg-[repeating-linear-gradient(45deg,transparent_0_2px,rgb(113_113_122/0.6)_2px_4px)]"
                            />
                            Celah waktu
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-red-600" />
                            Aplikasi non-kerja
                        </span>
                    </div>
                </div>
                <ScanTimeline
                    :screenshots="screenshots"
                    :gaps="gapFindings"
                    :labels="labels"
                    @select="openScreenshot"
                />
            </section>

            <section
                v-if="categoryBreakdown.total > 0"
                class="flex flex-col gap-3 rounded-xl border p-4"
            >
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 class="font-medium">Kategori aplikasi</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ formatPercent(categoryBreakdown.productiveShare) }}
                        dari {{ categoryBreakdown.total }} screenshot yang
                        dikenali berada di aplikasi kerja
                    </p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="row in categoryBreakdown.rows"
                        :key="row.name"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="w-36 shrink-0 truncate" :title="row.name">
                            {{ row.name }}
                        </span>
                        <div
                            class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full"
                                :class="
                                    row.isProductive
                                        ? 'bg-emerald-500'
                                        : 'bg-red-500'
                                "
                                :style="{
                                    width: `${(row.count / categoryBreakdown.total) * 100}%`,
                                }"
                            />
                        </div>
                        <span
                            class="w-8 text-right text-muted-foreground tabular-nums"
                        >
                            {{ row.count }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Hijau = kategori kerja, merah = non-kerja. Atur kategori dan
                    kata kunci di halaman Pengaturan Scan.
                </p>
            </section>

            <section class="flex flex-col gap-4">
                <div class="flex gap-1 border-b">
                    <button
                        v-for="[key, label] in [
                            ['findings', `Temuan (${findings.length})`],
                            ['groups', `Grup mirip (${groups.length})`],
                            ['all', `Semua gambar (${screenshots.length})`],
                        ] as const"
                        :key="key"
                        type="button"
                        class="-mb-px border-b-2 px-4 py-2 text-sm font-medium"
                        :class="
                            tab === key
                                ? 'border-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="tab = key"
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Findings -->
                <div v-if="tab === 'findings'" class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="
                                findingFilter === null ? 'default' : 'outline'
                            "
                            @click="findingFilter = null"
                        >
                            Semua
                        </Button>
                        <Button
                            v-for="type in FINDING_TYPES"
                            :key="type"
                            size="sm"
                            :variant="
                                findingFilter === type ? 'default' : 'outline'
                            "
                            @click="findingFilter = type"
                        >
                            {{ labels.finding[type] }} ({{
                                findingCounts[type]
                            }})
                        </Button>
                    </div>

                    <p
                        v-if="visibleFindings.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada temuan. 🎉
                    </p>

                    <button
                        v-for="finding in visibleFindings.slice(0, 300)"
                        :key="finding.id"
                        type="button"
                        class="flex items-center gap-4 rounded-lg border p-3 text-left hover:bg-muted/40"
                        @click="openFinding(finding)"
                    >
                        <img
                            v-if="
                                finding.related && finding.type !== 'time_gap'
                            "
                            :src="
                                screenshotUrl(
                                    teamSlug,
                                    finding.related.scanId,
                                    finding.related.id,
                                )
                            "
                            class="hidden h-14 w-24 shrink-0 rounded border object-cover sm:block"
                            loading="lazy"
                            alt=""
                        />
                        <img
                            :src="
                                screenshotUrl(
                                    teamSlug,
                                    scan.id,
                                    finding.screenshotId,
                                )
                            "
                            class="h-14 w-24 shrink-0 rounded border object-cover"
                            loading="lazy"
                            alt=""
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="
                                        finding.type === 'time_gap'
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    {{ labels.finding[finding.type] }}
                                </Badge>
                                <span class="text-sm font-medium tabular-nums">
                                    {{
                                        formatTime(
                                            screenshotsById.get(
                                                finding.screenshotId,
                                            )?.takenAt ?? null,
                                        )
                                    }}
                                </span>
                            </div>
                            <p
                                class="mt-1 truncate text-sm text-muted-foreground"
                            >
                                {{ finding.description }}
                            </p>
                        </div>
                    </button>

                    <p
                        v-if="visibleFindings.length > 300"
                        class="text-center text-sm text-muted-foreground"
                    >
                        Menampilkan 300 dari
                        {{ visibleFindings.length }} temuan. Gunakan filter
                        untuk mempersempit.
                    </p>
                </div>

                <!-- Similar groups -->
                <div v-if="tab === 'groups'" class="flex flex-col gap-4">
                    <p class="text-sm text-muted-foreground">
                        Screenshot yang tampilannya mirip satu sama lain. Grup
                        besar wajar jika karyawan bekerja di aplikasi yang sama
                        seharian; perhatikan grup yang berisi screenshot dari
                        jam yang berjauhan.
                    </p>
                    <p
                        v-if="groups.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada grup gambar mirip.
                    </p>
                    <div
                        v-for="(group, groupIndex) in groups"
                        :key="groupIndex"
                        class="rounded-xl border p-3"
                    >
                        <p class="mb-2 text-sm font-medium">
                            {{ group.length }} screenshot mirip ·
                            {{ formatTime(group[0].takenAt) }} –
                            {{ formatTime(group[group.length - 1].takenAt) }}
                        </p>
                        <div class="flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="screenshot in group.slice(0, 24)"
                                :key="screenshot.id"
                                type="button"
                                class="shrink-0"
                                @click="openScreenshot(screenshot)"
                            >
                                <img
                                    :src="
                                        screenshotUrl(
                                            teamSlug,
                                            scan.id,
                                            screenshot.id,
                                        )
                                    "
                                    class="h-20 w-32 rounded border-2 object-cover"
                                    :class="
                                        activityBorders[screenshot.activity]
                                    "
                                    loading="lazy"
                                    alt=""
                                />
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ formatTime(screenshot.takenAt) }}</span
                                >
                            </button>
                            <span
                                v-if="group.length > 24"
                                class="self-center px-2 text-sm text-muted-foreground"
                            >
                                +{{ group.length - 24 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- All screenshots -->
                <div v-if="tab === 'all'" class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="
                                activityFilter === null ? 'default' : 'outline'
                            "
                            @click="activityFilter = null"
                        >
                            Semua
                        </Button>
                        <Button
                            v-for="activity in ACTIVITIES"
                            :key="activity"
                            size="sm"
                            :variant="
                                activityFilter === activity
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="activityFilter = activity"
                        >
                            {{ labels.activity[activity] }} ({{
                                activityCounts[activity]
                            }})
                        </Button>
                    </div>
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6"
                    >
                        <button
                            v-for="screenshot in visibleScreenshots"
                            :key="screenshot.id"
                            type="button"
                            class="flex flex-col gap-1 text-left"
                            @click="openScreenshot(screenshot)"
                        >
                            <img
                                v-if="screenshot.hasThumbnail"
                                :src="
                                    screenshotUrl(
                                        teamSlug,
                                        scan.id,
                                        screenshot.id,
                                    )
                                "
                                class="aspect-video w-full rounded border-2 object-cover"
                                :class="activityBorders[screenshot.activity]"
                                loading="lazy"
                                alt=""
                            />
                            <div
                                v-else
                                class="flex aspect-video w-full items-center justify-center rounded border-2 border-dashed p-2 text-center text-xs text-muted-foreground"
                            >
                                {{ screenshot.error ?? 'Tidak ada pratinjau' }}
                            </div>
                            <span
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-medium tabular-nums">
                                    {{ formatTime(screenshot.takenAt) }}
                                    <span
                                        v-if="screenshot.screenClock"
                                        class="font-normal text-muted-foreground"
                                        title="Jam yang terbaca di layar"
                                    >
                                        · layar {{ screenshot.screenClock }}
                                    </span>
                                </span>
                                <span class="text-muted-foreground">{{
                                    formatPercent(screenshot.changeRatio)
                                }}</span>
                            </span>
                            <span
                                v-if="screenshot.category"
                                class="w-fit truncate rounded px-1.5 py-0.5 text-xs"
                                :class="
                                    screenshot.category.isProductive
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                                "
                            >
                                {{ screenshot.category.name }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                                :title="screenshot.name"
                                >{{ screenshot.name }}</span
                            >
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </div>

    <ScreenshotCompareDialog
        v-model:open="dialogOpen"
        :team-slug="teamSlug"
        :title="dialogTitle"
        :description="dialogDescription"
        :current="dialogCurrent"
        :comparison="dialogComparison"
        :boxes="dialogBoxes"
    />
</template>
