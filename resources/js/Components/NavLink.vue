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
        ? 'inline-flex items-center px-4 pt-1 rounded-xl text-xs font-black uppercase tracking-widest text-[#C8B07D] bg-[#C8B07D]/5 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-4 pt-1 border-b-2 border-transparent text-xs font-black uppercase tracking-widest text-gray-400 hover:text-[#C8B07D] hover:bg-gray-50 rounded-xl transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
