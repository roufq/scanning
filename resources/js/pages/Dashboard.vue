<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck,
    Images,
    LoaderCircle,
    ScanSearch,
    TriangleAlert,
    Upload,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, statusVariants } from '@/lib/scans';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/scans';
import type { DashboardInvitation, ScanStatus, Team } from '@/types';

type Overview = {
    stats: {
        scansThisMonth: number;
        screenshotsThisMonth: number;
        findingsThisMonth: number;
        runningScans: number;
    };
    recentScans: {
        id: number;
        employee: string;
        workDate: string | null;
        status: ScanStatus;
        statusLabel: string;
        screenshotsCount: number;
        findingsCount: number;
    }[];
    employeesToReview: { name: string; findings: number; scans: number }[];
};

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    overview?: Overview | null;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Beranda',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const firstName = computed(
    () => page.props.auth.user?.name?.split(' ')[0] ?? '',
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    return hour < 19 ? 'Selamat sore' : 'Selamat malam';
});

const stats = computed(() => {
    const values = props.overview?.stats;

    return [
        {
            label: 'Scan bulan ini',
            value: values?.scansThisMonth ?? 0,
            icon: CalendarCheck,
            tone: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        },
        {
            label: 'Screenshot dicek',
            value: values?.screenshotsThisMonth ?? 0,
            icon: Images,
            tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        },
        {
            label: 'Temuan perlu ditinjau',
            value: values?.findingsThisMonth ?? 0,
            icon: TriangleAlert,
            tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        },
        {
            label: 'Sedang dianalisis',
            value: values?.runningScans ?? 0,
            icon: LoaderCircle,
            tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
        },
    ];
});

const steps = [
    {
        title: 'Isi nama karyawan',
        text: 'Pilih dari daftar atau ketik nama baru.',
    },
    {
        title: 'Unggah screenshot',
        text: 'Tarik 100–1000 file JPG/PNG sekaligus.',
    },
    {
        title: 'Baca hasilnya',
        text: 'Lihat timeline dan temuan yang perlu dicek.',
    },
];
</script>

<template>
    <Head title="Beranda" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <section
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary to-blue-500 p-6 text-primary-foreground shadow-lg shadow-primary/20 md:p-8"
        >
            <div
                aria-hidden="true"
                class="absolute -top-16 -right-16 size-64 rounded-full bg-white/10"
            />
            <div
                aria-hidden="true"
                class="absolute -right-4 -bottom-24 size-48 rounded-full bg-white/10"
            />
            <div
                class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-xl space-y-2">
                    <p class="text-sm font-medium text-white/80">
                        {{ greeting }}{{ firstName ? `, ${firstName}` : '' }} 👋
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                        Siap mengecek screenshot kerja hari ini?
                    </h1>
                    <p class="text-sm text-white/85">
                        Unggah screenshot dari aplikasi pemantau, lalu sistem
                        akan menandai layar diam, file salinan, aplikasi
                        non-kerja, dan jam yang janggal secara otomatis.
                    </p>
                </div>
                <Button
                    size="lg"
                    variant="secondary"
                    class="w-fit rounded-xl bg-white text-primary shadow-md hover:bg-white/90"
                    as-child
                >
                    <Link :href="create(teamSlug)">
                        <Upload /> Mulai scan baru
                    </Link>
                </Button>
            </div>

            <ol class="relative mt-6 grid gap-3 sm:grid-cols-3">
                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="flex gap-3 rounded-2xl bg-white/10 p-3 backdrop-blur-sm"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-primary"
                    >
                        {{ index + 1 }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold">{{ step.title }}</p>
                        <p class="text-xs text-white/80">{{ step.text }}</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex items-center gap-3 rounded-2xl border bg-card p-4 shadow-sm"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                    :class="stat.tone"
                >
                    <component :is="stat.icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stat.value.toLocaleString('id-ID') }}
                    </p>
                    <p class="text-xs leading-tight text-muted-foreground">
                        {{ stat.label }}
                    </p>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 class="font-semibold">Scan terbaru</h2>
                    <Link
                        :href="index(teamSlug)"
                        class="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                    >
                        Lihat semua <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div
                    v-if="!overview || overview.recentScans.length === 0"
                    class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-accent text-accent-foreground"
                    >
                        <ScanSearch class="size-6" />
                    </span>
                    <p class="font-medium">Belum ada scan</p>
                    <p class="max-w-xs text-sm text-muted-foreground">
                        Scan pertama Anda akan muncul di sini setelah screenshot
                        diunggah.
                    </p>
                </div>

                <ul v-else class="flex flex-col gap-2">
                    <li v-for="scan in overview.recentScans" :key="scan.id">
                        <Link
                            :href="
                                show({ current_team: teamSlug, scan: scan.id })
                            "
                            class="flex items-center gap-3 rounded-xl p-3 transition-colors hover:bg-accent/60"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-bold text-secondary-foreground"
                            >
                                {{ scan.employee.charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">
                                    {{ scan.employee }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDate(scan.workDate) }} ·
                                    {{ scan.screenshotsCount }} screenshot
                                </p>
                            </div>
                            <span
                                v-if="scan.status === 'completed'"
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    scan.findingsCount > 0
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                "
                            >
                                {{
                                    scan.findingsCount > 0
                                        ? `${scan.findingsCount} temuan`
                                        : 'Aman'
                                }}
                            </span>
                            <Badge
                                v-else
                                :variant="statusVariants[scan.status]"
                            >
                                {{ scan.statusLabel }}
                            </Badge>
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <h2 class="font-semibold">Perlu ditinjau</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Karyawan dengan temuan terbanyak dalam 30 hari terakhir
                </p>

                <p
                    v-if="!overview || overview.employeesToReview.length === 0"
                    class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    Belum ada temuan dalam 30 hari terakhir. 🎉
                </p>

                <ul v-else class="flex flex-col gap-3">
                    <li
                        v-for="employee in overview.employeesToReview"
                        :key="employee.name"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                        >
                            <UserRound class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ employee.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ employee.scans }} scan ·
                                {{ employee.findings }} temuan
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 tabular-nums dark:bg-amber-950 dark:text-amber-300"
                        >
                            {{ employee.findings }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
