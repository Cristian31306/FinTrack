<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-white px-4 py-10 sm:px-6 antialiased"
    >
        <!-- Background Elements -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-[#C8B07D]/10 rounded-full blur-[120px]"></div>
            <div class="absolute top-[20%] -right-[5%] w-[30%] h-[30%] bg-[#C8B07D]/20 rounded-full blur-[100px] opacity-20">
            </div>

            <!-- Subtle Grid Pattern -->
            <div class="absolute inset-0 opacity-[0.05]"
                style="background-image: radial-gradient(#C8B07D 0.5px, transparent 0.5px); background-size: 24px 24px;">
            </div>
        </div>
 
        <div class="relative z-10 mb-8">
            <Link href="/">
                <img src="/logo-full.png" alt="FinTrack Logo" class="h-32 w-auto hover:scale-105 transition-transform duration-300">
            </Link>
        </div>
 
        <div
            class="relative z-10 w-full max-w-md overflow-hidden rounded-[2.5rem] border border-black/5 bg-gray-50/80 px-8 py-10 shadow-[0_40px_100px_rgba(0,0,0,0.08)] backdrop-blur-xl sm:px-10"
        >
            <slot />
        </div>
 
        <p class="relative z-10 mt-8 text-center text-[10px] font-black uppercase tracking-[0.2em]">
            <Link
                href="/"
                class="text-gray-400 hover:text-[#C8B07D] transition-colors"
                >← Volver al inicio</Link
            >
        </p>
    </div>
</template>
