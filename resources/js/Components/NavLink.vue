<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-3 py-2 rounded-lg border-b-2 border-brand text-sm font-medium leading-5 text-brand focus:outline-none transition duration-150 ease-in-out'
        : 'inline-flex items-center px-3 py-2 rounded-lg border-b-2 border-transparent text-sm font-medium leading-5 text-text-muted hover:text-text-main hover:border-border-main focus:outline-none focus:text-text-main focus:border-border-main transition duration-150 ease-in-out'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
