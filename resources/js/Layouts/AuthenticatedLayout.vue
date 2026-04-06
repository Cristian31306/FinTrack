<script setup>
import { ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AiAssistant from '@/Components/AiAssistant.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const flashSuccess = ref(page.props.flash?.success ?? null);

let flashTimeout = null;
watch(
    () => page.props.flash?.success,
    (v) => {
        flashSuccess.value = v ?? null;
        if (v) {
            clearTimeout(flashTimeout);
            flashTimeout = setTimeout(() => {
                flashSuccess.value = null;
            }, 4000);
        }
    },
    { immediate: true }
);

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <!-- Toast Notification -->
        <transition
            enter-active-class="transition ease-out duration-300 transform"
            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 sm:scale-100"
            leave-to-class="opacity-0 sm:scale-95"
        >
            <div
                v-if="flashSuccess"
                class="fixed bottom-24 right-5 sm:top-24 sm:right-6 sm:bottom-auto z-[100] w-full max-w-sm overflow-hidden rounded-xl bg-green-50 border border-green-200 shadow-xl ring-1 ring-black/5"
            >
                <div class="p-4 flex items-start gap-4">
                    <div class="flex-shrink-0 mt-0.5">
                        <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="w-0 flex-1">
                        <p class="text-[15px] font-semibold text-green-900 font-outfit">¡Operación exitosa!</p>
                        <p class="mt-1 text-[13px] text-green-700 leading-snug">{{ flashSuccess }}</p>
                    </div>
                    <div class="ml-4 flex flex-shrink-0">
                        <button @click="flashSuccess = null" type="button" class="inline-flex rounded-lg bg-green-50 p-1.5 text-green-500 hover:bg-green-100 hover:text-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-green-50 transition-colors">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        <div class="min-h-screen bg-gray-50/50">
            <!-- Navbar -->
            <nav class="sticky top-0 z-50 border-b border-black/5 bg-white/80 backdrop-blur-md">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="group flex items-center gap-2">
                                    <img src="/logo-full.png" alt="FinTrack" class="h-16 w-auto transition-transform group-hover:scale-105">
                                </Link>
                            </div>

                            <!-- Desktop Navigation (Flat & Premium) -->
                            <div class="hidden md:flex items-center gap-8 text-[11px] font-black uppercase tracking-[0.2em] text-gray-400 sm:ms-12">
                                <Link :href="route('dashboard')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('dashboard') }">Panel</Link>
                                <Link :href="route('credit-cards.index')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('credit-cards.*') }">Tarjetas</Link>
                                <Link :href="route('purchases.index')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('purchases.*') }">Compras</Link>
                                <Link :href="route('responsible-people.index')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('responsible-people.*') }">Responsables</Link>
                                <Link :href="route('categories.index')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('categories.*') }">Categorías</Link>
                                <Link :href="route('cuts.index')" class="hover:text-[#C8B07D] transition-colors duration-300" :class="{ 'text-[#111111]': route().current('cuts.*') }">Cortes</Link>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-black/5 bg-white px-4 py-2 text-xs font-black uppercase tracking-widest text-gray-500 transition-all hover:bg-gray-50 hover:text-[#C8B07D] focus:outline-none focus:ring-2 focus:ring-[#C8B07D]/20"
                                            >
                                                <template v-if="$page.props.auth.user.avatar_url">
                                                    <img :src="$page.props.auth.user.avatar_url" :alt="$page.props.auth.user.name" class="h-6 w-6 rounded-full object-cover">
                                                </template>
                                                <template v-else>
                                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-[10px] font-black text-gray-400">
                                                        {{ $page.props.auth.user.name.charAt(0) }}
                                                    </div>
                                                </template>
                                                {{ $page.props.auth.user.name }}
                                                <svg
                                                    class="-me-0.5 ms-1 h-3 w-3 opacity-50"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Perfil </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Cerrar sesión
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Mobile hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                type="button"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-gray-400 transition-all hover:bg-gray-100 hover:text-[#C8B07D] focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Navigation Menu -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-1 opacity-0"
                >
                    <div v-show="showingNavigationDropdown" class="sm:hidden">
                        <div class="space-y-1 pb-3 pt-2">
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Panel
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('credit-cards.index')" :active="route().current('credit-cards.*')">
                                Tarjetas
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('purchases.index')" :active="route().current('purchases.*')">
                                Compras
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('responsible-people.index')" :active="route().current('responsible-people.*')">
                                Responsables
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('categories.index')" :active="route().current('categories.*')">
                                Categorías
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('cuts.index')" :active="route().current('cuts.*')">
                                Cortes Facturación
                            </ResponsiveNavLink>

                            <div class="mt-4 pt-4 border-t border-black/5">
                                <ResponsiveNavLink :href="route('profile.edit')" :active="route().current('profile.edit')">
                                    Seguridad
                                </ResponsiveNavLink>
                            </div>
                        </div>

                        <!-- Mobile User Info -->
                        <div class="border-t border-black/5 pb-1 pt-4">
                            <div class="px-4 flex items-center gap-3">
                                <template v-if="$page.props.auth.user.avatar_url">
                                    <img :src="$page.props.auth.user.avatar_url" :alt="$page.props.auth.user.name" class="h-10 w-10 rounded-full object-cover shrink-0">
                                </template>
                                <template v-else>
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-lg font-black text-gray-400">
                                        {{ $page.props.auth.user.name.charAt(0) }}
                                    </div>
                                </template>
                                <div>
                                    <div class="text-sm font-black uppercase tracking-tight text-[#111111]">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="text-xs font-medium text-gray-400">
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 space-y-1">
                                <ResponsiveNavLink :href="route('profile.edit')"> Perfil </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                    Cerrar sesión
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </transition>
            </nav>

            <!-- Page Header -->
            <header v-if="$slots.header" class="relative z-10 border-b border-black/5 bg-white/50">
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <transition
                        appear
                        enter-active-class="transition duration-700 ease-out"
                        enter-from-class="-translate-y-4 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                    >
                        <div v-if="$slots.header">
                            <slot name="header" />
                        </div>
                    </transition>
                </div>
            </header>

            <!-- Page Content -->
            <main class="relative z-10 bg-white">
                <transition
                    appear
                    enter-active-class="transition duration-1000 ease-out"
                    enter-from-class="translate-y-4 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                >
                    <div key="slot-content">
                        <slot />
                    </div>
                </transition>
            </main>

            <!-- Footer Decorative -->
            <div class="fixed bottom-0 left-0 -z-10 h-64 w-full bg-gradient-to-t from-[#C8B07D]/5 to-transparent opacity-50"></div>
            
            <!-- Global AI Assistant Chat -->
            <AiAssistant />

            <!-- Floating Action Button (FAB) for Mobile Quick Purchase -->
            <Link 
                :href="route('purchases.create')"
                class="fixed bottom-6 right-6 z-[60] flex h-14 w-14 items-center justify-center rounded-full bg-[#111111] text-white shadow-2xl transition-all hover:scale-105 active:scale-95 md:hidden"
            >
                <div class="absolute -z-10 h-full w-full animate-ping rounded-full bg-[#C8B07D]/20"></div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
            </Link>
        </div>
    </div>
</template>
