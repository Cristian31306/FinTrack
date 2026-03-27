<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const features = [
    {
        title: 'Gestión Unificada',
        description: 'Tus tarjetas, cupos y fechas de pago en un solo vistazo. Soporte para múltiples franquicias y periodos de corte.',
        icon: 'credit-card',
        number: '1'
    },
    {
        title: 'Automatización',
        description: 'Registra compras por WhatsApp con BOOTs que categoriza tus gastos y detecta patrones de ahorro automáticamente.',
        icon: 'trending-up',
        number: '2'
    },
    {
        title: 'Control de Deudas',
        description: 'Seguimiento exacto de cuotas, saldos reales y división de gastos compartidos con notificaciones inteligentes.',
        icon: 'bell',
        number: '3'
    }
];

// Estado para animar la tarjeta flotante
const isHovered = ref(false);
const showingMobileMenu = ref(false);

const recentPurchases = [
    { name: 'Suscripción Digital', date: 'Hoy, 10:15 AM', amount: '$15.99', installments: '1 Cuota', icon: 'zap' },
    { name: 'Mercado Premium', date: 'Ayer, 6:45 PM', amount: '$124.50', installments: '1 Cuota', icon: 'shopping-cart' },
    { name: 'Restaurante Elite', date: '24 Oct, 9:30 PM', amount: '$85.00', installments: '3 Cuotas', icon: 'coffee' }
];
</script>

<template>

    <Head title="FinTrack - Domina tus Tarjetas" />

    <div
        class="min-h-screen bg-white text-[#111111] selection:bg-[#C8B07D]/30 selection:text-[#C8B07D] font-sans antialiased">
        <!-- Background Decorators -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-[-10%] right-[-10%] w-[50%] h-[50%] bg-[#C8B07D]/10 blur-[120px] rounded-full">
            </div>
            <div class="absolute bottom-[-10%] left-[-10%] w-[40%] h-[40%] bg-[#C8B07D]/5 blur-[100px] rounded-full">
            </div>
            <!-- Subtle Grid Pattern -->
            <div class="absolute inset-0 opacity-[0.05]"
                style="background-image: radial-gradient(#C8B07D 0.5px, transparent 0.5px); background-size: 24px 24px;">
            </div>
        </div>

        <!-- Navigation -->
        <nav class="relative z-50 flex items-center justify-between px-6 py-4 mx-auto max-w-7xl lg:px-12 lg:py-6">
            <div class="flex items-center gap-3">
                <img src="/logo-full.png" alt="FinTrack Logo"
                    class="h-20 lg:h-32 w-auto hover:scale-105 transition-transform duration-300">
            </div>

            <div
                class="hidden md:flex items-center gap-10 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400">
                <a href="#" class="hover:text-[#C8B07D] transition-colors duration-300">Inicio</a>
                <a href="#features" class="hover:text-[#C8B07D] transition-colors duration-300">Características</a>
                <a href="#whatsapp" class="hover:text-[#C8B07D] transition-colors duration-300">Asistente</a>
                <a href="#security" class="hover:text-[#C8B07D] transition-colors duration-300">Seguridad</a>
            </div>

            <div class="flex items-center gap-4">
                <template v-if="canLogin">
                    <Link v-if="$page.props.auth?.user" :href="route('dashboard')"
                        class="px-5 py-2 text-[10px] lg:px-6 lg:py-2.5 lg:text-xs font-black uppercase tracking-widest bg-gray-100 border border-black/5 rounded-md hover:bg-gray-200 transition-all text-[#111111]">
                        Ir al Panel
                    </Link>
                    <template v-else>
                        <Link :href="route('login')"
                            class="hidden sm:inline-block px-6 py-2.5 text-xs font-black uppercase tracking-widest border border-[#C8B07D]/30 rounded-md hover:bg-[#C8B07D] hover:text-[#111111] transition-all duration-500 shadow-xl shadow-[#C8B07D]/5">
                            Iniciar Sesión
                        </Link>
                    </template>
                </template>

                <!-- Mobile Hamburger -->
                <button
                    @click="showingMobileMenu = !showingMobileMenu"
                    class="md:hidden p-2 text-gray-500 hover:text-[#C8B07D] transition-colors"
                >
                    <svg v-if="!showingMobileMenu" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Mobile Menu Overlay -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-4"
        >
            <div v-if="showingMobileMenu" class="fixed inset-0 z-40 bg-white md:hidden">
                <div class="flex flex-col items-center justify-center h-full space-y-8 p-6">
                    <img src="/logo-full.png" alt="FinTrack" class="h-24 w-auto mb-8">
                    <a @click="showingMobileMenu = false" href="#" class="text-xl font-black uppercase tracking-widest text-[#111111]">Inicio</a>
                    <a @click="showingMobileMenu = false" href="#features" class="text-xl font-black uppercase tracking-widest text-[#111111]">Características</a>
                    <a @click="showingMobileMenu = false" href="#whatsapp" class="text-xl font-black uppercase tracking-widest text-[#111111]">Asistente</a>
                    <a @click="showingMobileMenu = false" href="#security" class="text-xl font-black uppercase tracking-widest text-[#111111]">Seguridad</a>
                    
                    <div class="pt-8 w-full space-y-3">
                        <Link v-if="!$page.props.auth?.user" :href="route('login')" @click="showingMobileMenu = false"
                            class="block w-full text-center py-4 text-sm font-black uppercase tracking-widest border border-[#C8B07D]/30 rounded-xl">
                            Iniciar Sesión
                        </Link>
                        <Link v-if="canRegister && !$page.props.auth?.user" :href="route('register')" @click="showingMobileMenu = false"
                            class="block w-full text-center py-4 text-sm font-black uppercase tracking-widest bg-[#C8B07D] text-[#111111] rounded-xl">
                            Registrarme
                        </Link>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Hero Section -->
        <main class="relative z-10 px-6 pt-16 pb-24 mx-auto max-w-7xl lg:px-12 lg:pt-24">
            <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-24">
                <!-- Hero Content -->
                <div class="max-w-2xl space-y-10">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#C8B07D]/10 border border-[#C8B07D]/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#C8B07D] animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-[#C8B07D]">Evolución
                            Financiera
                            Inteligente</span>
                    </div>

                    <h1 class="text-4xl font-black leading-[1.1] tracking-tight sm:text-5xl lg:text-7xl">
                        Domina tus <span
                            class="text-transparent bg-clip-text bg-gradient-to-r from-[#C8B07D] via-[#E5D5B0] to-[#C8B07D]">Tarjetas</span>,
                        Optimiza tu Futuro.
                    </h1>

                    <p class="text-base sm:text-xl leading-relaxed text-gray-600 font-medium max-w-xl">
                        Toma el control absoluto de tus finanzas personales con la plataforma elegante e inteligente
                        para
                        gestionar todas tus tarjetas de crédito en un solo lugar. Simple, Seguro y Sofisticado.
                    </p>

                    <div class="flex flex-wrap items-center gap-6 pt-6">
                        <Link v-if="canRegister && !$page.props.auth?.user" :href="route('register')"
                            class="group relative flex items-center gap-3 px-10 py-5 bg-[#C8B07D] text-[#111111] font-black uppercase tracking-widest text-xs rounded-full hover:shadow-[0_0_40px_rgba(200,176,125,0.4)] transition-all duration-500">
                            Empieza Gratis Hoy
                            <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-300"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Hero Mockup (Desktop) -->
                <div class="relative hidden lg:block group" @mouseenter="isHovered = true"
                    @mouseleave="isHovered = false">
                    <!-- Dashboard Mockup -->
                    <div
                        class="relative overflow-hidden border border-black/5 rounded-3xl bg-white shadow-[0_40px_100px_rgba(0,0,0,0.1)] transform transition-all duration-700 group-hover:scale-[1.02]">
                        <!-- Browser Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 bg-gray-50">
                            <div class="flex items-center gap-3">
                                <img src="/logo-primario.png" alt="" class="h-6 w-auto opacity-30">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-red-500/10 border border-red-500/20"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500/10 border border-yellow-500/20">
                                    </div>
                                    <div class="w-3 h-3 rounded-full bg-green-500/10 border border-green-500/20"></div>
                                </div>
                            </div>
                            <div
                                class="px-4 py-1 text-[9px] font-black tracking-[0.3em] uppercase text-gray-400 border border-black/5 rounded-full">
                                FinTrack Dashboard</div>
                        </div>

                        <!-- Dashboard UI Content -->
                        <div class="p-10 space-y-8 bg-gradient-to-b from-white to-gray-50">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="p-6 rounded-2xl bg-gray-50 border border-black/5 space-y-4 shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div class="text-[9px] text-gray-500 uppercase font-black tracking-widest">
                                            Gastos por
                                            Categoría</div>
                                        <div class="w-2 h-2 rounded-full bg-[#C8B07D]"></div>
                                    </div>
                                    <div class="flex items-end gap-3 h-20">
                                        <div class="flex-1 bg-[#C8B07D]/20 rounded-t-lg h-[40%]"></div>
                                        <div class="flex-1 bg-[#C8B07D]/40 rounded-t-lg h-[70%]"></div>
                                        <div class="flex-1 bg-[#C8B07D] rounded-t-lg h-[55%]"></div>
                                        <div class="flex-1 bg-[#C8B07D]/60 rounded-t-lg h-[90%]"></div>
                                    </div>
                                </div>
                                <div
                                    class="p-6 rounded-2xl bg-gray-50 border border-black/5 flex flex-col justify-between shadow-sm">
                                    <div class="text-[9px] text-gray-500 uppercase font-black tracking-widest">Próximo
                                        Pago
                                    </div>
                                    <div>
                                        <div class="text-3xl font-black text-[#111111]">$450.00</div>
                                        <div
                                            class="text-[10px] font-bold text-[#C8B07D] mt-2 bg-[#C8B07D]/10 px-2 py-0.5 rounded-md inline-block">
                                            15 OCT · VISA GOLD</div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 rounded-2xl bg-gray-50 border border-black/5 space-y-6 shadow-sm">
                                <div class="text-[9px] text-gray-500 uppercase font-black tracking-widest">Gastos
                                    Recientes
                                </div>
                                <div class="space-y-4">
                                    <div v-for="purchase in recentPurchases" :key="purchase.name"
                                        class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/5">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center border border-white/5">
                                                <svg v-if="purchase.icon === 'zap'" class="w-5 h-5 text-[#C8B07D]"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <svg v-else-if="purchase.icon === 'shopping-cart'"
                                                    class="w-5 h-5 text-[#C8B07D]" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                <svg v-else class="w-5 h-5 text-[#C8B07D]" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-xs font-black uppercase tracking-tight">{{
                                                    purchase.name }}</div>
                                                <div class="text-[10px] font-bold text-gray-600">{{ purchase.date }} ·
                                                    {{ purchase.installments }}</div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-black text-[#111111]">{{ purchase.amount }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Premium Credit Card -->
                        <div class="absolute top-[20%] -right-16 w-80 aspect-[1.58] transform transition-all duration-1000 ease-out z-20 shadow-[0_30px_60px_rgba(0,0,0,0.15)]"
                            :class="isHovered ? '-translate-x-8 -translate-y-8 rotate-3 scale-110' : 'translate-x-0 translate-y-0 rotate-0 scale-100'">
                            <div
                                class="relative w-full h-full p-8 rounded-2xl bg-gradient-to-br from-white via-gray-50 to-gray-100 border border-black/10 overflow-hidden group/card">
                                <!-- Card Texture/Pattern -->
                                <div class="absolute inset-0 opacity-5 card-pattern"></div>

                                <div class="relative flex flex-col h-full justify-between">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-3">
                                            <img src="/logo-primario.png" alt="" class="h-10 w-auto">
                                            <span
                                                class="text-sm font-black tracking-tighter uppercase text-[#C8B07D]">FinTrack</span>
                                        </div>
                                        <!-- Chip -->
                                        <div
                                            class="w-10 h-8 bg-gradient-to-br from-[#A68F5B] to-[#C8B07D] rounded-md opacity-40">
                                        </div>
                                    </div>

                                    <div class="space-y-6">
                                        <div class="text-2xl font-mono tracking-[0.2em] text-[#111111]/80">1234 5678
                                            9823
                                            4919</div>
                                        <div class="flex justify-between items-end">
                                            <div>
                                                <div
                                                    class="text-[8px] text-gray-500 uppercase tracking-widest font-black mb-1">
                                                    Card Holder</div>
                                                <div
                                                    class="text-[11px] font-black uppercase tracking-widest text-[#111111]/70">
                                                    ALEJANDRO S.</div>
                                            </div>
                                            <div class="flex -space-x-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-red-500/80 backdrop-blur-sm shadow-sm">
                                                </div>
                                                <div
                                                    class="w-8 h-8 rounded-full bg-yellow-500/80 backdrop-blur-sm shadow-sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card Shine -->
                                <div
                                    class="absolute inset-0 bg-gradient-to-tr from-white/[0.02] via-transparent to-white/[0.05] pointer-events-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Glow Behind Mockup -->
                    <div
                        class="absolute -z-10 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[110%] h-[110%] bg-[#C8B07D]/10 blur-[120px] rounded-full">
                    </div>
                </div>
            </div>

            <!-- Features Bento Grid -->
            <div id="features" class="mt-40 space-y-16 scroll-mt-24">
                <div class="flex items-end justify-between border-b border-white/10 pb-8">
                    <div class="space-y-2">
                        <div class="text-[10px] font-black text-[#C8B07D] uppercase tracking-[0.3em]">Características
                            Elite
                        </div>
                        <h2 class="text-4xl font-black tracking-tighter uppercase">Control Total en Tus Manos</h2>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3 md:gap-8">
                    <div v-for="feature in features" :key="feature.title"
                        class="group relative p-8 md:p-10 rounded-3xl bg-gray-50 border border-black/5 hover:border-[#C8B07D]/30 transition-all duration-700 hover:bg-white overflow-hidden shadow-sm hover:shadow-xl">

                        <!-- Feature Number Background -->
                        <div
                            class="absolute -right-4 -bottom-4 text-[80px] md:text-[120px] font-black text-white/[0.02] leading-none select-none group-hover:text-[#C8B07D]/[0.03] transition-colors">
                            {{ feature.number }}
                        </div>

                        <div class="relative space-y-8">
                            <div
                                class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-[#C8B07D] transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-500">
                                <svg v-if="feature.icon === 'credit-card'" class="w-7 h-7" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <svg v-else-if="feature.icon === 'trending-up'" class="w-7 h-7" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                <svg v-else class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>

                            <div class="space-y-4">
                                <h3
                                    class="text-2xl font-black tracking-tight group-hover:text-[#C8B07D] transition-colors">
                                    {{
                                        feature.title }}</h3>
                                <p
                                    class="text-[15px] leading-relaxed text-gray-600 group-hover:text-[#111111] transition-colors">
                                    {{ feature.description }}
                                </p>
                            </div>

                            <div class="w-8 h-1 bg-[#C8B07D]/30 group-hover:w-full transition-all duration-700"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <section
                class="mt-48 relative overflow-hidden rounded-[3rem] bg-gray-50 border border-black/5 p-16 text-center shadow-2xl">
                <div class="absolute inset-0 bg-[#C8B07D]/10 blur-[80px] rounded-full -z-10 animate-pulse"></div>
                <div class="relative z-10 space-y-8 max-w-3xl mx-auto">
                    <h2 class="text-4xl md:text-5xl font-black tracking-tighter uppercase">¿Listo para elevar tu control
                        financiero?</h2>
                    <p class="text-gray-600 text-lg font-medium">Únete a cientos de usuarios que ya dominan sus tarjetas
                        de
                        crédito
                        con
                        nosotros.</p>
                    <div class="pt-6">
                        <Link v-if="canRegister && !$page.props.auth?.user" :href="route('register')"
                            class="inline-flex items-center px-12 py-5 bg-[#C8B07D] text-[#111111] font-black uppercase tracking-[0.2em] text-xs rounded-full hover:scale-105 transition-all duration-300 shadow-2xl shadow-[#C8B07D]/20">
                            Empezar ahora
                        </Link>
                    </div>
                </div>
            </section>

            <!-- WhatsApp Assistant Section -->
            <section id="whatsapp" class="mt-24 md:mt-40 scroll-mt-24">
                <div
                    class="relative overflow-hidden rounded-[2.5rem] md:rounded-[3rem] bg-gray-50 border border-black/5 p-8 md:p-20 group shadow-xl">
                    <!-- Decorative background glow -->
                    <div
                        class="absolute -top-24 -right-24 w-64 h-64 md:w-96 md:h-96 bg-[#C8B07D]/10 rounded-full blur-[100px] group-hover:bg-[#C8B07D]/20 transition-colors duration-700">
                    </div>

                    <div class="grid items-center gap-16 md:grid-cols-2">
                        <div class="space-y-8">
                            <div
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#25D366]/10 border border-[#25D366]/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#25D366] animate-pulse"></span>
                                <span
                                    class="text-[10px] font-black uppercase tracking-widest text-[#25D366]">Integración
                                    Exclusiva</span>
                            </div>
                            <h2 class="text-4xl md:text-5xl font-black tracking-tighter uppercase leading-none">
                                Registra tus Gastos <span class="text-[#25D366]">vía WhatsApp</span>
                            </h2>
                            <p class="text-lg text-gray-600 leading-relaxed font-medium">
                                Olvídate de abrir complicadas apps. Registra cualquier compra enviando un simple mensaje
                                a nuestro Boot. El se encarga de categorizar y procesar todo por ti.
                            </p>
                            <div class="flex flex-wrap gap-4 pt-4">
                                <div
                                    class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white border border-black/5 shadow-sm">
                                    <svg class="w-5 h-5 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12c0 1.74.45 3.38 1.23 4.81L2 22l5.35-1.4c1.42.74 3.03 1.17 4.65 1.17 5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.25c-1.49 0-2.95-.39-4.22-1.12l-.3-.17-3.14.82.84-3.07-.19-.3c-.79-1.27-1.2-2.73-1.2-4.23 0-4.52 3.68-8.2 8.2-8.2s8.2 3.68 8.2 8.2-3.68 8.2-8.2 8.2z" />
                                    </svg>
                                    <span class="text-xs font-black uppercase tracking-widest text-[#111111]">Sin
                                        Fricción</span>
                                </div>
                                <div
                                    class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white border border-black/5 shadow-sm">
                                    <svg class="w-5 h-5 text-[#C8B07D]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span class="text-xs font-black uppercase tracking-widest text-[#111111]">Boot en
                                        Tiempo
                                        Real</span>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Simulation -->
                        <div class="relative">
                            <div class="space-y-4 p-6 rounded-3xl bg-white shadow-lg border border-black/5">
                                <div class="flex justify-start">
                                    <div
                                        class="p-4 rounded-2xl rounded-tl-none bg-gray-50 text-sm text-gray-600 max-w-[80%] border border-black/5">
                                        "Hola! Acabo de comprar un café en Starbucks por $5.50 con mi tarjeta Platinum."
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <div
                                        class="p-4 rounded-2xl rounded-tr-none bg-[#C8B07D]/10 text-[#A68F5B] text-sm font-bold max-w-[80%] border border-[#C8B07D]/20">
                                        "¡Entendido! Acabo de registrar tu gasto de $5.50 en la categoría 'Restaurante'
                                        ☕️.
                                        Tienes $450.00 de cupo restante."
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Security Section -->
            <section id="security" class="mt-40 space-y-16 scroll-mt-24">
                <div class="flex flex-col items-center text-center space-y-4">
                    <div class="text-[10px] font-black text-[#C8B07D] uppercase tracking-[0.3em]">Seguridad y Privacidad
                        Total</div>
                    <h2 class="text-3xl md:text-5xl font-black tracking-tighter uppercase text-[#111111]">Tu
                        Tranquilidad es
                        Nuestra
                        Prioridad</h2>
                    <p class="text-gray-600 text-sm md:text-base max-w-2xl mx-auto font-medium">
                        FinTrack está diseñado con la seguridad y la privacidad como núcleos fundamentales. No
                        conectamos con
                        tus bancos, tú tienes el
                        control total de la información que registras.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <div class="p-10 rounded-3xl bg-gray-50 border border-black/5 space-y-6 shadow-sm">
                        <div
                            class="w-12 h-12 rounded-xl bg-[#C8B07D]/10 flex items-center justify-center text-[#C8B07D]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black uppercase tracking-tight text-[#111111]">Encriptación AES-256</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">
                            Todos tus datos se almacenan utilizando los estándares de cifrado más avanzados de la
                            industria.
                        </p>
                    </div>

                    <div class="p-10 rounded-3xl bg-gray-50 border border-black/5 space-y-6 shadow-sm">
                        <div
                            class="w-12 h-12 rounded-xl bg-[#C8B07D]/10 flex items-center justify-center text-[#C8B07D]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black uppercase tracking-tight text-[#111111]">Sin Enlaces Bancarios
                        </h3>
                        <p class="text-sm text-gray-500 leading-relaxed">
                            No te pedimos credenciales bancarias. FinTrack funciona de forma aislada para garantizar tu
                            tranquilidad.
                        </p>
                    </div>

                    <div class="p-10 rounded-3xl bg-gray-50 border border-black/5 space-y-6 shadow-sm">
                        <div
                            class="w-12 h-12 rounded-xl bg-[#C8B07D]/10 flex items-center justify-center text-[#C8B07D]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black uppercase tracking-tight text-[#111111]">Privacidad por Diseño
                        </h3>
                        <p class="text-sm text-gray-500 leading-relaxed">
                            Tú eres el único dueño de tu información. Nunca compartimos ni vendemos tus datos a
                            terceros.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer id="footer"
            class="relative z-10 px-8 py-20 mt-20 border-t border-black/5 bg-gray-50/80 backdrop-blur-3xl">
            <div class="flex flex-col items-center justify-between gap-12 mx-auto max-w-7xl md:flex-row">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-3">
                        <img src="/logo-full.png" alt="FinTrack Logo"
                            class="h-32 w-auto opacity-70 hover:opacity-100 transition-opacity duration-300">
                    </div>
                    <p class="text-xs text-gray-600 font-bold max-w-[200px]">Simplicidad y poder en cada transacción.
                    </p>
                </div>

                <div class="flex flex-col items-center gap-6">
                    <div
                        class="flex flex-wrap justify-center gap-8 text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <a href="#" class="hover:text-[#C8B07D] transition-colors">Inicio</a>
                        <a href="#features" class="hover:text-[#C8B07D] transition-colors">Características</a>
                        <a href="#whatsapp" class="hover:text-[#C8B07D] transition-colors">Asistente</a>
                        <a href="#security" class="hover:text-[#C8B07D] transition-colors">Seguridad & Privacidad</a>
                    </div>
                    <p
                        class="text-[9px] font-black uppercase tracking-[0.4em] text-gray-700 bg-white/5 px-6 py-2 rounded-full border border-white/5">
                        © {{ new Date().getFullYear() }} FinTrack. Todos los derechos reservados.
                    </p>
                </div>

                <div class="flex gap-8 text-gray-600">
                    <a href="#" class="hover:text-[#C8B07D] transition-colors"><svg class="w-6 h-6" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" />
                        </svg></a>
                    <a href="#" class="hover:text-[#C8B07D] transition-colors"><svg class="w-6 h-6" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 1.17.054 1.805.249 2.227.412.558.217.957.477 1.377.896.42.419.68.818.897 1.377.163.422.358 1.057.412 2.227.058 1.266.07 1.646.07 4.85s-.012 3.584-.07 4.85c-.054 1.17-.249 1.805-.412 2.227-.217.558-.477.957-.896 1.377-.419.42-.818.68-1.377.897-.422.163-1.057.358-2.227.412-1.266.058-1.646.07-4.85.07s-3.584-.012-4.85-.07c-1.17-.054-1.805-.249-2.227-.412-.558-.217-.957-.477-1.377-.896-.42-.419-.68-.818-.897-1.377-.163-.422-.358-1.057-.412-2.227-.058-1.266-.07-1.646-.07-4.85s.012-3.584.07-4.85c.054-1.17.249-1.805.412-2.227.217-.558.477-.957.896-1.377.419-.42.818-.68 1.377-.897.422-.163 1.057-.358 2.227-.412 1.266-.058 1.646-.07 4.85-.07zm0-2.163c-3.259 0-3.667.014-4.947.072-1.277.057-2.148.258-2.911.556-.788.307-1.458.717-2.126 1.385s-1.078 1.338-1.385 2.126c-.297.763-.499 1.634-.556 2.911-.058 1.28-.071 1.688-.071 4.947s.013 3.667.071 4.947c.057 1.277.259 2.148.556 2.911.307.788.717 1.458 1.385 2.126s1.338 1.078 2.126 1.385c.763.297 1.634.499 2.911.556 1.28.058 1.688.071 4.947.071s3.667-.013 4.947-.071c1.277-.057 2.148-.259 2.911-.556.788-.307 1.459-.717 2.126-1.385s1.078-1.338 1.385-2.126c.297-.763.499-1.634.556-2.911.058-1.28.071-1.688.071-4.947s-.013-3.667-.071-4.947c-.057-1.277-.259-2.148-.556-2.911-.307-.788-.717-1.458-1.385-2.126s-1.338-1.078-2.126-1.385c-.763-.297-1.634-.499-2.911-.556-1.28-.058-1.688-.071-4.947-.071z" />
                            <path
                                d="M12 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.162 6.162 6.162 6.162-2.759 6.162-6.162-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg></a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Card Pattern */
.card-pattern {
    background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h20L0 20z' fill='%23C8B07D' fill-rule='evenodd'/%3E%3C/svg%3E");
}

/* Custom Animations */
@keyframes fade-in {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes slide-up {
    from {
        transform: translateY(20px);
        opacity: 0;
    }

    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.animate-fade-in {
    animation: fade-in 1s ease-out forwards;
}

.animate-slide-up {
    animation: slide-up 1s ease-out forwards;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #0a0a0a;
}

::-webkit-scrollbar-thumb {
    background: #222;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #333;
}

/* Font Smoothing */
.antialiased {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Card Shine Effect */
.group\/card:hover .shine {
    transform: translateX(100%);
    transition: transform 0.8s ease-in-out;
}
</style>
