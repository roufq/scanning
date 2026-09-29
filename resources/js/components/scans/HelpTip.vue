<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    text: string;
}>();
</script>

<template>
    <TooltipProvider :delay-duration="100">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex size-4 shrink-0 items-center justify-center rounded-full text-muted-foreground/70 hover:text-primary focus-visible:text-primary focus-visible:outline-none"
                    :aria-label="text"
                >
                    <CircleHelp class="size-4" />
                </button>
            </TooltipTrigger>
            <TooltipContent class="max-w-64 text-center leading-relaxed">
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
