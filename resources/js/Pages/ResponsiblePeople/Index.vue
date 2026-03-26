<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    people: Array,
});

function destroy(id) {
    if (confirm('¿Eliminar este responsable?')) {
        router.delete(route('responsible-people.destroy', id));
    }
}
</script>

<template>
    <Head title="Responsables" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-black tracking-tighter uppercase text-[#111111]">
                        Responsables
                    </h2>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Gestión de participaciones externas</p>
                </div>
                <Link :href="route('responsible-people.create')">
                    <button class="inline-flex items-center gap-2 rounded-xl bg-[#C8B07D] px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-[#111111] shadow-lg transition-all hover:bg-[#A68F5B] hover:shadow-[#C8B07D]/25">
                        Nuevo responsable
                    </button>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <ul
                    class="overflow-hidden rounded-[2.5rem] border border-black/5 bg-white shadow-premium"
                >
                    <li
                        v-for="p in people"
                        :key="p.id"
                        class="flex items-center justify-between px-4 py-4"
                    >
                        <div>
                            <p class="font-medium text-gray-900">{{ p.name }}</p>
                            <p
                                v-if="p.email"
                                class="text-sm text-gray-500"
                            >
                                {{ p.email }}
                            </p>
                        </div>
                        <div class="flex gap-4 text-[10px] font-black uppercase tracking-widest text-[#C8B07D]">
                            <Link
                                :href="
                                    route('responsible-people.edit', p.id)
                                "
                                class="hover:underline transition-all hover:tracking-widest"
                                >Editar</Link
                            >
                            <button
                                type="button"
                                class="text-red-500 hover:underline"
                                @click="destroy(p.id)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </li>
                    <li
                        v-if="!people?.length"
                        class="px-4 py-8 text-center text-gray-500"
                    >
                        No hay responsables. Créalos para dividir compras.
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
