<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 -left-32 size-[28rem] rounded-full bg-primary/15 blur-3xl"
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-32 -bottom-40 size-[28rem] rounded-full bg-emerald-400/15 blur-3xl"
        />

        <div class="relative w-full max-w-md">
            <Link
                :href="home()"
                class="mb-6 flex items-center justify-center gap-3"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/30"
                >
                    <AppLogoIcon class="size-6 fill-current" />
                </div>
                <div class="text-left">
                    <p class="leading-tight font-bold">{{ appName }}</p>
                    <p class="text-xs text-muted-foreground">
                        Cek screenshot kerja jadi lebih cepat
                    </p>
                </div>
            </Link>

            <div
                class="rounded-3xl border bg-card p-6 shadow-xl shadow-primary/5 sm:p-8"
            >
                <div class="mb-6 space-y-1.5 text-center">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
