<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- JejakKerja mark: a monitor showing a timeline of screenshots (one paused). -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="6 8 36 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <rect
            x="8"
            y="10"
            width="32"
            height="22"
            rx="3"
            fill="none"
            stroke="currentColor"
            stroke-width="2.6"
        />
        <path
            d="M18 38h12M24 32v6"
            fill="none"
            stroke="currentColor"
            stroke-width="2.6"
            stroke-linecap="round"
        />
        <g fill="currentColor">
            <rect x="13" y="18" width="2.6" height="7" rx="1.3" />
            <rect x="18.2" y="18" width="2.6" height="7" rx="1.3" />
            <rect
                x="23.4"
                y="18"
                width="2.6"
                height="7"
                rx="1.3"
                opacity="0.45"
            />
            <rect x="28.6" y="18" width="2.6" height="7" rx="1.3" />
            <rect x="33.8" y="18" width="2.6" height="7" rx="1.3" />
        </g>
    </svg>
</template>
