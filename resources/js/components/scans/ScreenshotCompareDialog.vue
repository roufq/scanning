<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { screenshotUrl } from '@/lib/scans';

export type ComparedImage = {
    scanId: number;
    id: number;
    name: string;
    caption: string;
};

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    title: string;
    description: string;
    current: ComparedImage | null;
    comparison: ComparedImage | null;
    bbox: [number, number, number, number] | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[95vh] overflow-y-auto sm:max-w-[min(1400px,95vw)]"
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4" :class="comparison ? 'lg:grid-cols-2' : ''">
                <figure v-if="comparison" class="flex flex-col gap-2">
                    <figcaption class="text-sm text-muted-foreground">
                        {{ comparison.caption }}
                    </figcaption>
                    <a
                        :href="
                            screenshotUrl(
                                teamSlug,
                                comparison.scanId,
                                comparison.id,
                                'image',
                            )
                        "
                        target="_blank"
                    >
                        <img
                            :src="
                                screenshotUrl(
                                    teamSlug,
                                    comparison.scanId,
                                    comparison.id,
                                    'image',
                                )
                            "
                            :alt="comparison.name"
                            class="w-full rounded-md border"
                        />
                    </a>
                    <span class="truncate text-xs text-muted-foreground">{{
                        comparison.name
                    }}</span>
                </figure>

                <figure v-if="current" class="flex flex-col gap-2">
                    <figcaption class="text-sm text-muted-foreground">
                        {{ current.caption }}
                    </figcaption>
                    <a
                        :href="
                            screenshotUrl(
                                teamSlug,
                                current.scanId,
                                current.id,
                                'image',
                            )
                        "
                        target="_blank"
                        class="relative block"
                    >
                        <img
                            :src="
                                screenshotUrl(
                                    teamSlug,
                                    current.scanId,
                                    current.id,
                                    'image',
                                )
                            "
                            :alt="current.name"
                            class="w-full rounded-md border"
                        />
                        <span
                            v-if="bbox"
                            class="pointer-events-none absolute border-2 border-red-500 bg-red-500/10"
                            :style="{
                                left: `${bbox[0] * 100}%`,
                                top: `${bbox[1] * 100}%`,
                                width: `${(bbox[2] - bbox[0]) * 100}%`,
                                height: `${(bbox[3] - bbox[1]) * 100}%`,
                            }"
                        />
                    </a>
                    <span class="truncate text-xs text-muted-foreground">{{
                        current.name
                    }}</span>
                </figure>
            </div>
        </DialogContent>
    </Dialog>
</template>
