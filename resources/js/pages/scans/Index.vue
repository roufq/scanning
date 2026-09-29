<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, ScanSearch } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, statusVariants } from '@/lib/scans';
import { create, index, show } from '@/routes/scans';
import type { Paginated, ScanListItem, Team } from '@/types';

defineProps<{
    scans: Paginated<ScanListItem>;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Scan Screenshot',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
</script>

<template>
    <Head title="Scan Screenshot" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight">
                    Scan Screenshot
                </h1>
                <p class="text-sm text-muted-foreground">
                    Riwayat pengecekan screenshot kerja per karyawan. Klik nama
                    untuk melihat hasilnya.
                </p>
            </div>

            <Button class="rounded-xl" as-child>
                <Link :href="create(teamSlug)"> <Plus /> Scan baru </Link>
            </Button>
        </div>

        <div
            v-if="scans.data.length === 0"
            class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-primary/25 bg-card p-12 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-2xl bg-accent text-accent-foreground"
            >
                <ScanSearch class="size-7" />
            </span>
            <p class="font-semibold">Belum ada scan</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Mulai dengan mengisi nama karyawan lalu unggah screenshot hasil
                aplikasi pemantau kerja.
            </p>
            <Button class="mt-2 rounded-xl" as-child>
                <Link :href="create(teamSlug)">
                    <Plus /> Buat scan pertama
                </Link>
            </Button>
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-2xl border bg-card shadow-sm"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/60 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Karyawan</th>
                        <th class="px-4 py-3 font-medium">Tanggal kerja</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Screenshot
                        </th>
                        <th class="px-4 py-3 text-right font-medium">Temuan</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="scan in scans.data"
                        :key="scan.id"
                        class="border-t transition-colors hover:bg-accent/50"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="
                                    show({
                                        current_team: teamSlug,
                                        scan: scan.id,
                                    })
                                "
                                class="flex items-center gap-3 hover:text-primary"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-bold text-secondary-foreground"
                                >
                                    {{ scan.employee.charAt(0).toUpperCase() }}
                                </span>
                                {{ scan.employee }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(scan.workDate ?? scan.createdAt) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ scan.screenshotsCount }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span
                                v-if="scan.status !== 'completed'"
                                class="text-muted-foreground"
                                >–</span
                            >
                            <span
                                v-else
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    scan.findingsCount > 0
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                "
                            >
                                {{
                                    scan.findingsCount > 0
                                        ? scan.findingsCount
                                        : 'Aman'
                                }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariants[scan.status]">
                                {{ scan.statusLabel }}
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="scans.last_page > 1"
            class="flex items-center justify-end gap-2 text-sm"
        >
            <span class="text-muted-foreground">
                Halaman {{ scans.current_page }} dari {{ scans.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!scans.prev_page_url"
                as-child
            >
                <Link :href="scans.prev_page_url ?? ''"><ChevronLeft /></Link>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!scans.next_page_url"
                as-child
            >
                <Link :href="scans.next_page_url ?? ''"><ChevronRight /></Link>
            </Button>
        </div>
    </div>
</template>
