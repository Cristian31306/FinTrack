<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 shadow-sm transition-all hover:bg-slate-50 hover:scale-[1.02] active:scale-95 disabled:opacity-25"
    >
        <slot />
    </button>
</template>
