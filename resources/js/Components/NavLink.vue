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

// AQUI ESTÁ A CORREÇÃO
const classes = computed(() =>
    props.active
        
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-text-primary focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-text-secondary hover:text-text-primary hover:border-accent focus:outline-none focus:text-text-primary focus:border-accent transition duration-150 ease-in-out'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
