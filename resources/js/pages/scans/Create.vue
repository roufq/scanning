<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { ImageUp, LoaderCircle, Trash2, UploadCloud } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes, toLocalIso } from '@/lib/scans';
import { create, index, store } from '@/routes/scans';
import { store as startAnalysis } from '@/routes/scans/analysis';
import { store as storeScreenshots } from '@/routes/scans/screenshots';
import type { Team } from '@/types';

const props = defineProps<{
    employees: string[];
    limits: {
        maxScreenshots: number;
        maxFileSizeKb: number;
        filesPerRequest: number;
    };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Scan Screenshot',
                href: props.currentTeam ? index(props.currentTeam.slug) : '/',
            },
            {
                title: 'Scan baru',
                href: props.currentTeam ? create(props.currentTeam.slug) : '/',
            },
        ],
    }),
});

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const scanForm = useHttp<
    { employee_name: string; work_date: string },
    { id: number }
>({
    employee_name: '',
    work_date: '',
});

const upload = useHttp<
    { files: File[]; modified_at: string[] },
    { uploaded: number; total: number }
>({
    files: [],
    modified_at: [],
});

const files = ref<File[]>([]);
const rejected = ref<string[]>([]);
const isDragging = ref(false);
const phase = ref<'idle' | 'creating' | 'uploading' | 'starting'>('idle');
const scanId = ref<number | null>(null);
const uploadedCount = ref(0);
const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const totalSize = computed(() =>
    files.value.reduce((sum, file) => sum + file.size, 0),
);
const isBusy = computed(() => phase.value !== 'idle');
const progress = computed(() =>
    files.value.length === 0
        ? 0
        : Math.round((uploadedCount.value / files.value.length) * 100),
);

function addFiles(list: FileList | null) {
    if (!list || scanId.value !== null) {
        return;
    }

    const known = new Set(files.value.map((file) => file.name + file.size));
    const problems: string[] = [];

    for (const file of Array.from(list)) {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

        if (!ALLOWED_EXTENSIONS.includes(extension)) {
            problems.push(`${file.name}: bukan JPG/JPEG/PNG`);
        } else if (file.size > props.limits.maxFileSizeKb * 1024) {
            problems.push(
                `${file.name}: melebihi ${formatBytes(props.limits.maxFileSizeKb * 1024)}`,
            );
        } else if (files.value.length >= props.limits.maxScreenshots) {
            problems.push(
                `${file.name}: melebihi batas ${props.limits.maxScreenshots} screenshot`,
            );
        } else if (!known.has(file.name + file.size)) {
            known.add(file.name + file.size);
            files.value.push(file);
        }
    }

    files.value.sort((a, b) =>
        a.name.localeCompare(b.name, undefined, { numeric: true }),
    );
    rejected.value = problems;
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function clearFiles() {
    files.value = [];
    rejected.value = [];

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function firstError(errors: Record<string, string | undefined>): string {
    return Object.values(errors).find(Boolean) ?? 'Upload gagal, coba lagi.';
}

async function submit() {
    uploadError.value = null;

    if (files.value.length === 0) {
        uploadError.value = 'Pilih minimal satu screenshot.';

        return;
    }

    if (scanId.value === null) {
        phase.value = 'creating';

        try {
            const response = await scanForm.post(store.url(teamSlug.value));
            scanId.value = response.id;
        } catch {
            phase.value = 'idle';

            return;
        }
    }

    phase.value = 'uploading';

    // Resume from where a previous attempt stopped.
    for (
        let start = uploadedCount.value;
        start < files.value.length;
        start += props.limits.filesPerRequest
    ) {
        const chunk = files.value.slice(
            start,
            start + props.limits.filesPerRequest,
        );

        upload.files = chunk;
        upload.modified_at = chunk.map((file) => toLocalIso(file.lastModified));

        try {
            await upload.post(
                storeScreenshots.url({
                    current_team: teamSlug.value,
                    scan: scanId.value!,
                }),
            );
        } catch {
            uploadError.value = firstError(
                upload.errors as Record<string, string | undefined>,
            );
            phase.value = 'idle';

            return;
        }

        uploadedCount.value = start + chunk.length;
    }

    phase.value = 'starting';

    router.post(
        startAnalysis.url({
            current_team: teamSlug.value,
            scan: scanId.value!,
        }),
        {},
        { onFinish: () => (phase.value = 'idle') },
    );
}
</script>

<template>
    <Head title="Scan baru" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">Scan baru</h1>
            <p class="text-sm text-muted-foreground">
                Tiga langkah singkat. Analisis berjalan otomatis setelah
                screenshot terunggah.
            </p>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-3">
                    <span
                        class="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >1</span
                    >
                    <div>
                        <h2 class="font-semibold">Siapa karyawannya?</h2>
                        <p class="text-xs text-muted-foreground">
                            Ketik nama baru atau pilih nama yang pernah dipakai.
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <div class="grid gap-2">
                        <Label for="employee_name">Nama karyawan</Label>
                        <Input
                            id="employee_name"
                            v-model="scanForm.employee_name"
                            list="employee-names"
                            autocomplete="off"
                            placeholder="Contoh: Budi Santoso"
                            :disabled="isBusy || scanId !== null"
                            required
                        />
                        <datalist id="employee-names">
                            <option
                                v-for="name in employees"
                                :key="name"
                                :value="name"
                            />
                        </datalist>
                        <InputError :message="scanForm.errors.employee_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="work_date">Tanggal kerja (opsional)</Label>
                        <Input
                            id="work_date"
                            v-model="scanForm.work_date"
                            type="date"
                            :disabled="isBusy || scanId !== null"
                        />
                        <InputError :message="scanForm.errors.work_date" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-3">
                    <span
                        class="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                        >2</span
                    >
                    <div>
                        <h2 class="font-semibold">Pilih screenshot</h2>
                        <p class="text-xs text-muted-foreground">
                            Format JPG, JPEG, atau PNG. Jam diambil dari nama
                            file, misalnya <code>SS_20260927_081500.png</code>.
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed p-8 text-center transition-colors"
                    :class="
                        isDragging
                            ? 'border-primary bg-primary/5'
                            : 'border-primary/25 bg-accent/40'
                    "
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <UploadCloud class="size-7" />
                    </span>
                    <div>
                        <p class="font-semibold">
                            Tarik & lepas screenshot di sini
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Maksimal {{ limits.maxScreenshots }} file,
                            {{ formatBytes(limits.maxFileSizeKb * 1024) }} per
                            file
                        </p>
                    </div>
                    <Button
                        type="button"
                        class="rounded-full"
                        :disabled="isBusy || scanId !== null"
                        @click="fileInput?.click()"
                    >
                        <ImageUp /> Pilih dari komputer
                    </Button>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        multiple
                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                        @change="
                            addFiles(($event.target as HTMLInputElement).files)
                        "
                    />
                </div>

                <div
                    v-if="files.length > 0"
                    class="mt-3 flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                >
                    <span>
                        ✅ <strong>{{ files.length }}</strong> screenshot siap
                        diunggah · {{ formatBytes(totalSize) }}
                    </span>
                    <Button
                        v-if="scanId === null"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="isBusy"
                        @click="clearFiles"
                    >
                        <Trash2 /> Kosongkan
                    </Button>
                </div>

                <div
                    v-if="rejected.length > 0"
                    class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                >
                    <p class="font-medium">
                        {{ rejected.length }} file dilewati:
                    </p>
                    <ul
                        class="mt-1 max-h-32 list-inside list-disc overflow-y-auto"
                    >
                        <li v-for="problem in rejected" :key="problem">
                            {{ problem }}
                        </li>
                    </ul>
                </div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                            >3</span
                        >
                        <div>
                            <h2 class="font-semibold">Unggah & analisis</h2>
                            <p class="text-xs text-muted-foreground">
                                Anda akan dibawa ke halaman hasil, yang
                                diperbarui otomatis.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="rounded-xl"
                        :disabled="isBusy"
                    >
                        <LoaderCircle v-if="isBusy" class="animate-spin" />
                        <template v-if="phase === 'creating'"
                            >Membuat scan…</template
                        >
                        <template v-else-if="phase === 'uploading'"
                            >Mengunggah…</template
                        >
                        <template v-else-if="phase === 'starting'"
                            >Memulai analisis…</template
                        >
                        <template v-else-if="scanId !== null"
                            >Lanjutkan unggahan</template
                        >
                        <template v-else>Unggah & analisis</template>
                    </Button>
                </div>

                <div
                    v-if="phase === 'uploading' || uploadedCount > 0"
                    class="mt-4 grid gap-2"
                >
                    <div class="flex justify-between text-sm">
                        <span>Mengunggah screenshot…</span>
                        <span class="tabular-nums">
                            {{ uploadedCount }} / {{ files.length }}
                        </span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-all"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                </div>

                <InputError class="mt-3" :message="uploadError ?? undefined" />
            </section>
        </form>
    </div>
</template>
