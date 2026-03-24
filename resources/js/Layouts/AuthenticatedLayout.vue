<script setup>
import { ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const flashSuccess = ref(page.props.flash?.success ?? null);

watch(
    () => page.props.flash?.success,
    (v) => {
        flashSuccess.value = v ?? null;
    },
);

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div
            v-if="flashSuccess"
            class="border-b border-green-200 bg-green-50 px-4 py-2 text-center text-sm text-green-800"
        >
            {{ flashSuccess }}
        </div>
        <div class="min-h-screen">
            <!-- Navbar -->
            <nav class="sticky top-0 z-50 border-b border-brand-100 bg-white/80 backdrop-blur-md">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')" class="group flex items-center gap-2">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-400 text-white shadow-lg transition-transform group-hover:scale-105">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="font-outfit text-2xl font-bold tracking-tight text-slate-900">
                                        Fin<span class="text-brand-600">Track</span>
                                    </span>
                                </Link>
                            </div>

                            <!-- Desktop Navigation -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-12 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Panel
                                </NavLink>
                                <NavLink :href="route('credit-cards.index')" :active="route().current('credit-cards.*')">
                                    Tarjetas
                                </NavLink>
                                <NavLink :href="route('purchases.index')" :active="route().current('purchases.*')">
                                    Compras
                                </NavLink>
                                <NavLink :href="route('responsible-people.index')" :active="route().current('responsible-people.*')">
                                    Responsables
                                </NavLink>
                                <NavLink :href="route('cuts.index')" :active="route().current('cuts.*')">
                                    Cortes
                                </NavLink>
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
                                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                            >
                                                <template v-if="$page.props.auth.user.avatar_url">
                                                    <img :src="$page.props.auth.user.avatar_url" :alt="$page.props.auth.user.name" class="h-6 w-6 rounded-full object-cover">
                                                </template>
                                                <template v-else>
                                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                                        {{ $page.props.auth.user.name.charAt(0) }}
                                                    </div>
                                                </template>
                                                {{ $page.props.auth.user.name }}
                                                <svg
                                                    class="-me-0.5 ms-1 h-4 w-4 opacity-50"
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
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-900 focus:outline-none"
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
                            <ResponsiveNavLink :href="route('cuts.index')" :active="route().current('cuts.*')">
                                Cortes
                            </ResponsiveNavLink>
                        </div>

                        <!-- Mobile User Info -->
                        <div class="border-t border-slate-100 pb-1 pt-4">
                            <div class="px-4 flex items-center gap-3">
                                <template v-if="$page.props.auth.user.avatar_url">
                                    <img :src="$page.props.auth.user.avatar_url" :alt="$page.props.auth.user.name" class="h-10 w-10 rounded-full object-cover shrink-0">
                                </template>
                                <template v-else>
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-lg font-bold text-slate-600">
                                        {{ $page.props.auth.user.name.charAt(0) }}
                                    </div>
                                </template>
                                <div>
                                    <div class="text-base font-semibold text-slate-900">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div class="text-sm font-medium text-slate-500">
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
            <header v-if="$slots.header" class="relative z-10">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <transition
                        appear
                        enter-active-class="transition duration-700 ease-out"
                        enter-from-class="-translate-y-4 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                    >
                        <slot name="header" />
                    </transition>
                </div>
            </header>

            <!-- Page Content -->
            <main class="relative z-10">
                <transition
                    appear
                    enter-active-class="transition duration-1000 ease-out"
                    enter-from-class="translate-y-4 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                >
                    <slot />
                </transition>
            </main>

            <!-- Footer Decorative -->
            <div class="fixed bottom-0 left-0 -z-10 h-64 w-full bg-gradient-to-t from-brand-50/50 to-transparent opacity-50"></div>
        </div>
    </div>
</template>
