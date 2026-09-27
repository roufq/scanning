<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, ScanSearch } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
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

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Scan Screenshot"
                description="Riwayat pengecekan screenshot kerja per karyawan"
            />

            <Button as-child>
                <Link :href="create(teamSlug)"> <Plus /> Scan baru </Link>
            </Button>
        </div>

        <div
            v-if="scans.data.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <ScanSearch class="size-10 text-muted-foreground" />
            <p class="font-medium">Belum ada scan</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Mulai dengan mengisi nama karyawan lalu unggah screenshot hasil
                aplikasi pemantau kerja.
            </p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
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
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="
                                    show({
                                        current_team: teamSlug,
                                        scan: scan.id,
                                    })
                                "
                                class="hover:underline"
                            >
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
                                :class="
                                    scan.findingsCount > 0
                                        ? 'font-semibold text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ scan.findingsCount }}
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
