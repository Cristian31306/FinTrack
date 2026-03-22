<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-950 px-4 py-10 sm:px-6"
    >
        <div
            class="pointer-events-none fixed inset-0 -z-10"
            aria-hidden="true"
        >
            <div
                class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-emerald-500/20 blur-[90px]"
            />
            <div
                class="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-indigo-600/15 blur-[100px]"
            />
        </div>

        <div class="mb-8">
            <Link
                href="/"
                class="flex items-center gap-2.5 transition opacity-90 hover:opacity-100"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-lg font-black text-slate-950 shadow-lg shadow-emerald-500/25"
                    >F</span
                >
                <span class="text-xl font-bold tracking-tight text-white"
                    >FinTrack</span
                >
            </Link>
        </div>

        <div
            class="w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-white/[0.97] px-6 py-8 shadow-2xl shadow-black/40 backdrop-blur-xl sm:px-8"
        >
            <slot />
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            <Link
                href="/"
                class="text-slate-400 underline-offset-2 hover:text-emerald-400 hover:underline"
                >Volver al inicio</Link
            >
        </p>
    </div>
</template>
