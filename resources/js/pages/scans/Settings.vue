<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryDialog from '@/components/scans/CategoryDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/scan-categories';
import { edit, update } from '@/routes/scan-settings';
import type { ScanCategoryItem, ScanSettingsValues, Team } from '@/types';

const props = defineProps<{
    settings: ScanSettingsValues;
    defaults: ScanSettingsValues;
    categories: ScanCategoryItem[];
    canEdit: boolean;
    analyzer?: { online: boolean; classifier: boolean };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Pengaturan Scan',
                href: props.currentTeam ? edit(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

// Ratios are edited as percentages and converted back on submit.
const toForm = (values: ScanSettingsValues) => ({
    ...values,
    idle_change_ratio: +(values.idle_change_ratio * 100).toFixed(3),
    low_change_ratio: +(values.low_change_ratio * 100).toFixed(3),
    ai_min_confidence: Math.round(values.ai_min_confidence * 100),
});

const form = useForm(toForm(props.settings));

function save() {
    form.transform((data) => ({
        ...data,
        idle_change_ratio: data.idle_change_ratio / 100,
        low_change_ratio: data.low_change_ratio / 100,
        ai_min_confidence: data.ai_min_confidence / 100,
    })).put(update.url(teamSlug.value), { preserveScroll: true });
}

function useDefaults() {
    Object.assign(form, toForm(props.defaults));
}

const numberFields = [
    {
        key: 'idle_change_ratio',
        help: 'Makin kecil angkanya, makin ketat: hanya layar yang benar-benar tidak berubah yang dianggap diam.',
        label: 'Layar diam jika perubahan kurang dari',
        unit: '% layar',
        step: '0.01',
    },
    {
        key: 'low_change_ratio',
        help: 'Layar yang berubah sedikit (mis. hanya mengetik satu baris) ditandai kuning sebagai aktivitas rendah.',
        label: 'Aktivitas rendah jika perubahan kurang dari',
        unit: '% layar',
        step: '0.1',
    },
    {
        key: 'time_gap_minutes',
        help: 'Sesuaikan dengan interval aplikasi pemantau. Jika screenshot diambil tiap 10 menit, 15 menit adalah batas wajar.',
        label: 'Celah waktu jika tidak ada screenshot lebih dari',
        unit: 'menit',
        step: '1',
    },
    {
        key: 'clock_mismatch_minutes',
        help: 'Selisih kecil wajar karena jam komputer bisa sedikit berbeda. Beri toleransi beberapa menit.',
        label: 'Jam tidak cocok jika selisih lebih dari',
        unit: 'menit',
        step: '1',
    },
    {
        key: 'jiggler_min_streak',
        help: 'Satu kali kursor bergerak masih wajar. Beberapa kali berturut-turut tanpa perubahan lain patut dicurigai.',
        label: 'Mouse jiggler jika hanya kursor bergerak minimal',
        unit: 'kali beruntun',
        step: '1',
    },
    {
        key: 'similar_distance',
        help: 'Angka lebih besar mengelompokkan screenshot yang lebih berbeda. Biarkan 4 jika ragu.',
        label: 'Grup mirip: jarak hash maksimal (0 = identik)',
        unit: 'bit',
        step: '1',
    },
    {
        key: 'ai_min_confidence',
        help: 'Di bawah angka ini AI dianggap ragu dan tidak membuat temuan. Naikkan untuk mengurangi temuan yang keliru.',
        label: 'Keyakinan AI minimal untuk menentukan kategori',
        unit: '%',
        step: '1',
    },
] as const;

const dialogOpen = ref(false);
const editing = ref<ScanCategoryItem | null>(null);

function openCategory(category: ScanCategoryItem | null) {
    editing.value = category;
    dialogOpen.value = true;
}

function removeCategory(category: ScanCategoryItem) {
    if (confirm(`Hapus kategori "${category.name}"?`)) {
        router.delete(
            destroy.url({
                current_team: teamSlug.value,
                scan_category: category.id,
            }),
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="Pengaturan Scan" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4">
        <Heading
            title="Pengaturan Scan"
            description="Atur ambang batas analisis dan kategori aplikasi untuk team ini."
        />

        <div
            v-if="!canEdit"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
        >
            Hanya pemilik atau admin team yang dapat mengubah pengaturan ini.
        </div>

        <section
            class="flex flex-wrap items-center gap-3 rounded-2xl border bg-card p-4 text-sm shadow-sm"
        >
            <span class="font-medium">Status analyzer:</span>
            <template v-if="analyzer">
                <Badge :variant="analyzer.online ? 'default' : 'destructive'">
                    {{ analyzer.online ? 'Berjalan' : 'Tidak terhubung' }}
                </Badge>
                <Badge
                    v-if="analyzer.online"
                    :variant="analyzer.classifier ? 'default' : 'secondary'"
                >
                    AI
                    {{
                        analyzer.classifier
                            ? 'aktif'
                            : 'tidak terpasang (hanya kata kunci)'
                    }}
                </Badge>
                <span v-if="!analyzer.online" class="text-muted-foreground">
                    Jalankan <code>composer run dev</code> atau
                    <code>php artisan analyzer:serve</code>.
                </span>
            </template>
            <span v-else class="h-5 w-40 animate-pulse rounded bg-muted" />
        </section>

        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Ambang batas"
                description='Berlaku untuk analisis berikutnya. Klik "Analisis ulang" pada scan lama untuk menerapkannya.'
            />

            <form
                class="grid gap-5 rounded-2xl border bg-card p-5 shadow-sm"
                @submit.prevent="save"
            >
                <div
                    v-for="field in numberFields"
                    :key="field.key"
                    class="grid gap-2 sm:grid-cols-[1fr_12rem] sm:items-center"
                >
                    <div class="space-y-0.5">
                        <Label :for="field.key">{{ field.label }}</Label>
                        <p class="text-xs text-muted-foreground">
                            {{ field.help }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Input
                            :id="field.key"
                            v-model.number="form[field.key]"
                            type="number"
                            min="0"
                            :step="field.step"
                            :disabled="!canEdit"
                            class="w-24"
                        />
                        <span class="text-sm text-muted-foreground">{{
                            field.unit
                        }}</span>
                    </div>
                    <InputError
                        :message="form.errors[field.key]"
                        class="sm:col-span-2"
                    />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        v-model="form.read_screen_clock"
                        :disabled="!canEdit"
                    />
                    Baca jam di taskbar (OCR) untuk mendeteksi jam yang tidak
                    cocok
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        v-model="form.classify_screenshots"
                        :disabled="!canEdit"
                    />
                    Kenali aplikasi di layar (kata kunci judul jendela + AI)
                </label>

                <div v-if="canEdit" class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="useDefaults">
                        Kembalikan default
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Simpan
                    </Button>
                </div>
            </form>
        </section>

        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    variant="small"
                    title="Kategori aplikasi"
                    description="Screenshot di kategori non-kerja dilaporkan sebagai temuan."
                />
                <Button v-if="canEdit" size="sm" @click="openCategory(null)">
                    <Plus /> Tambah kategori
                </Button>
            </div>

            <div
                class="flex flex-col divide-y rounded-2xl border bg-card shadow-sm"
            >
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="flex items-start justify-between gap-4 p-4"
                    :class="category.isEnabled ? '' : 'opacity-60'"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ category.name }}</span>
                            <Badge
                                :variant="
                                    category.isProductive
                                        ? 'secondary'
                                        : 'destructive'
                                "
                            >
                                {{
                                    category.isProductive
                                        ? 'Kerja'
                                        : 'Non-kerja'
                                }}
                            </Badge>
                            <Badge v-if="!category.isEnabled" variant="outline"
                                >Nonaktif</Badge
                            >
                        </div>
                        <p
                            class="mt-1 truncate text-xs text-muted-foreground"
                            :title="category.prompt"
                        >
                            AI: {{ category.prompt }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span
                                v-for="keyword in category.keywords"
                                :key="keyword"
                                class="rounded bg-muted px-1.5 py-0.5 text-xs"
                            >
                                {{ keyword }}
                            </span>
                        </div>
                    </div>
                    <div v-if="canEdit" class="flex shrink-0 gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="`Ubah ${category.name}`"
                            @click="openCategory(category)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="`Hapus ${category.name}`"
                            @click="removeCategory(category)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>

                <p
                    v-if="categories.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    Belum ada kategori.
                </p>
            </div>
        </section>
    </div>

    <CategoryDialog
        v-model:open="dialogOpen"
        :team-slug="teamSlug"
        :category="editing"
    />
</template>
