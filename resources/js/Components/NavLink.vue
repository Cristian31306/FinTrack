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
        ? 'inline-flex items-center px-4 pt-1 rounded-xl text-sm font-semibold leading-5 text-brand-700 bg-brand-50/50 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-4 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-slate-500 hover:text-brand-600 hover:bg-slate-50 rounded-xl transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
