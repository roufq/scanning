<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/scan-categories';
import type { ScanCategoryItem } from '@/types';

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    category: ScanCategoryItem | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const form = useForm({
    name: '',
    prompt: 'a screenshot of ',
    keywords: '',
    is_productive: true,
    is_enabled: true,
});

// Load the selected category (or a blank one) every time the dialog opens.
watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.defaults({
            name: props.category?.name ?? '',
            prompt: props.category?.prompt ?? 'a screenshot of ',
            keywords: props.category?.keywords.join(', ') ?? '',
            is_productive: props.category?.isProductive ?? true,
            is_enabled: props.category?.isEnabled ?? true,
        });
        form.reset();
    },
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.category) {
        form.put(
            update.url({
                current_team: props.teamSlug,
                scan_category: props.category.id,
            }),
            options,
        );
    } else {
        form.post(store.url(props.teamSlug), options);
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ category ? 'Ubah kategori' : 'Tambah kategori' }}
                </DialogTitle>
                <DialogDescription>
                    Screenshot dicocokkan dulu dengan kata kunci di judul
                    jendela/tab. Jika tidak ada yang cocok, AI memilih kategori
                    berdasarkan deskripsi.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="category-name">Nama kategori</Label>
                    <Input
                        id="category-name"
                        v-model="form.name"
                        placeholder="Contoh: Video / streaming"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>Jenis</Label>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                form.is_productive ? 'default' : 'outline'
                            "
                            @click="form.is_productive = true"
                        >
                            Kerja
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                form.is_productive ? 'outline' : 'destructive'
                            "
                            @click="form.is_productive = false"
                        >
                            Non-kerja
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Screenshot di kategori non-kerja menjadi temuan
                        "Aplikasi non-kerja".
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="category-prompt">
                        Deskripsi untuk AI (bahasa Inggris)
                    </Label>
                    <Input
                        id="category-prompt"
                        v-model="form.prompt"
                        placeholder="a screenshot of a YouTube video player"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Tulis seperti keterangan gambar, misalnya "a screenshot
                        of an online shopping website".
                    </p>
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-keywords">
                        Kata kunci (pisahkan dengan koma)
                    </Label>
                    <Input
                        id="category-keywords"
                        v-model="form.keywords"
                        placeholder="youtube, netflix, vidio"
                    />
                    <InputError :message="form.errors.keywords" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="form.is_enabled" />
                    Aktif
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="emit('update:open', false)"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Simpan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
